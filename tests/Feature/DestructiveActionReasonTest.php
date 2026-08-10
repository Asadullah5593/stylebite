<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Comment;
use App\Models\EarningsWallet;
use App\Models\EarningTransaction;
use App\Models\ModerationAction;
use App\Models\Post;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The client requirement is that destructive actions capture a reason and are
 * confirmable. These tests cover the server half — that the reason is mandatory
 * and actually persisted — because a confirmation dialog is worthless if the
 * endpoint still accepts a bare request from anywhere.
 */
class DestructiveActionReasonTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'status' => 'active']);
    }

    private function makePost(?User $author = null): Post
    {
        return Post::create([
            'user_id' => ($author ?? User::factory()->create())->id,
            'post_type' => 'outfit',
            'content_type' => 'fashion',
            'media_kind' => 'image',
            'feed_type' => 'style',
            'caption' => 'Destructive action fixture',
            'visibility' => 'public',
            'status' => 'published',
            'moderation_status' => 'clean',
        ]);
    }

    private function walletWithCredit(User $user, float $amount = 50.0): EarningTransaction
    {
        $wallet = EarningsWallet::create([
            'user_id' => $user->id,
            'currency_code' => 'USD',
            'available_balance' => $amount,
            'pending_balance' => 0,
            'lifetime_earned' => $amount,
        ]);

        return EarningTransaction::create([
            'wallet_id' => $wallet->id,
            'user_id' => $user->id,
            'transaction_type' => 'credit',
            'source_type' => 'adjustment',
            'amount' => $amount,
            'currency_code' => 'USD',
            'status' => 'completed',
            'processed_at' => now(),
        ]);
    }

    public function test_reversing_a_transaction_requires_a_reason_and_records_it(): void
    {
        $admin = $this->admin();
        $creator = User::factory()->create(['status' => 'active']);
        $transaction = $this->walletWithCredit($creator);

        // Without a reason the money must not move at all.
        $this->actingAs($admin)
            ->post(route('admin.earnings.transactions.reverse', $transaction))
            ->assertSessionHasErrors('reason');

        $this->assertSame('completed', $transaction->fresh()->status);
        $this->assertSame(50.0, (float) $transaction->wallet->fresh()->available_balance);

        $this->actingAs($admin)
            ->post(route('admin.earnings.transactions.reverse', $transaction), [
                'reason' => 'Duplicate credit from a double-settled impression batch.',
            ])
            ->assertRedirect();

        $transaction->refresh();
        $this->assertSame('reversed', $transaction->status);
        $this->assertSame(0.0, (float) $transaction->wallet->fresh()->available_balance);

        // The admin's own words, not the old hardcoded 'Admin reversal'.
        $reversal = EarningTransaction::where('source_id', $transaction->id)
            ->where('source_type', 'adjustment')
            ->latest('id')
            ->first();

        $this->assertNotNull($reversal);
        $this->assertSame(
            'Duplicate credit from a double-settled impression batch.',
            $reversal->metadata_json['reason']
        );
        $this->assertSame($admin->id, (int) $reversal->metadata_json['reversed_by_user_id']);

        $log = ActivityLog::where('event_name', 'earning_transaction_reversed')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('Duplicate credit', $log->metadata_json['reason']);
    }

    public function test_a_short_reason_is_refused(): void
    {
        $admin = $this->admin();
        $transaction = $this->walletWithCredit(User::factory()->create(['status' => 'active']));

        $this->actingAs($admin)
            ->post(route('admin.earnings.transactions.reverse', $transaction), ['reason' => 'x'])
            ->assertSessionHasErrors('reason');

        $this->assertSame('completed', $transaction->fresh()->status);
    }

    public function test_deleting_a_user_requires_a_reason(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['status' => 'active']);

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $target))
            ->assertSessionHasErrors('reason');

        $this->assertNull($target->fresh()->deleted_at);

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $target), ['reason' => 'Confirmed spam account.'])
            ->assertRedirect();

        $this->assertNotNull($target->fresh()->deleted_at);

        $log = ActivityLog::where('event_name', 'user_deleted')->latest('id')->first();
        $this->assertSame('Confirmed spam account.', $log->metadata_json['reason']);
    }

    public function test_an_admin_cannot_delete_their_own_account(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $admin), ['reason' => 'Oops.'])
            ->assertSessionHasErrors('reason');

        $this->assertNull($admin->fresh()->deleted_at);
    }

    public function test_removing_a_block_requires_a_reason_and_keeps_the_users_own_reason(): void
    {
        $admin = $this->admin();
        $blocker = User::factory()->create(['status' => 'active']);
        $blocked = User::factory()->create(['status' => 'active']);

        $block = UserBlock::create([
            'blocker_user_id' => $blocker->id,
            'blocked_user_id' => $blocked->id,
            'reason' => 'They kept harassing me',
            'created_at' => now(),
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.social.blocks.delete', $block))
            ->assertSessionHasErrors('reason');

        $this->assertDatabaseHas('user_blocks', ['id' => $block->id]);

        $this->actingAs($admin)
            ->delete(route('admin.social.blocks.delete', $block), [
                'reason' => 'Both parties asked support to clear it.',
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('user_blocks', ['id' => $block->id]);

        $log = ActivityLog::where('event_name', 'social_block_deleted')->latest('id')->first();
        $this->assertSame('Both parties asked support to clear it.', $log->metadata_json['reason']);
        // The blocking user's own stated reason is preserved for context.
        $this->assertSame('They kept harassing me', $log->metadata_json['user_block_reason']);
    }

    public function test_moderating_a_post_requires_a_reason_and_writes_a_moderation_action(): void
    {
        $admin = $this->admin();
        $post = $this->makePost();

        $this->actingAs($admin)
            ->patch(route('admin.posts.moderate', $post), [
                'status' => 'removed',
                'moderation_status' => 'blocked',
            ])
            ->assertSessionHasErrors('reason');

        $this->assertSame('published', $post->fresh()->status);

        $this->actingAs($admin)
            ->patch(route('admin.posts.moderate', $post), [
                'status' => 'removed',
                'moderation_status' => 'blocked',
                'reason' => 'Nudity in the second image.',
            ])
            ->assertRedirect();

        $this->assertSame('removed', $post->fresh()->status);

        // Previously a takedown from the content list wrote nothing here, so the
        // moderation history was missing most real decisions.
        $action = ModerationAction::where('target_type', 'post')->where('target_id', $post->id)->latest('id')->first();
        $this->assertNotNull($action);
        $this->assertSame('remove', $action->action);
        $this->assertSame('Nudity in the second image.', $action->reason);
        $this->assertSame($admin->id, (int) $action->moderator_user_id);
    }

    public function test_moderating_a_comment_requires_a_reason_and_writes_a_moderation_action(): void
    {
        $admin = $this->admin();
        $post = $this->makePost();

        $comment = Comment::create([
            'post_id' => $post->id,
            'user_id' => $post->user_id,
            'body' => 'Abusive comment',
            'status' => 'active',
            'moderation_status' => 'clean',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.comments.update', $comment), [
                'status' => 'blocked',
                'moderation_status' => 'restricted',
            ])
            ->assertSessionHasErrors('reason');

        $this->actingAs($admin)
            ->patch(route('admin.comments.update', $comment), [
                'status' => 'blocked',
                'moderation_status' => 'restricted',
                'reason' => 'Personal attack on the author.',
            ])
            ->assertRedirect();

        $action = ModerationAction::where('target_type', 'comment')->where('target_id', $comment->id)->latest('id')->first();
        $this->assertNotNull($action);
        $this->assertSame('restrict', $action->action);
        $this->assertSame('Personal attack on the author.', $action->reason);
    }

    public function test_clearing_a_post_records_a_restore_action(): void
    {
        $admin = $this->admin();
        $post = $this->makePost();

        $this->actingAs($admin)
            ->patch(route('admin.posts.moderate', $post), [
                'status' => 'published',
                'moderation_status' => 'clean',
                'reason' => 'Reviewed — nothing wrong with it.',
            ])
            ->assertRedirect();

        $action = ModerationAction::where('target_type', 'post')->where('target_id', $post->id)->latest('id')->first();
        $this->assertSame('restore', $action->action);
    }
}
