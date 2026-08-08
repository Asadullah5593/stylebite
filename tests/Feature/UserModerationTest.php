<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserModerationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
    }

    /**
     * @return array{0: User, 1: string} the user and a live plain-text bearer token
     */
    private function userWithSession(array $attributes = []): array
    {
        $user = User::factory()->create($attributes + ['status' => 'active']);
        $token = Str::random(80);

        UserSession::create([
            'user_id' => $user->id,
            'session_token_hash' => hash('sha256', $token),
            'platform' => 'android',
            'last_seen_at' => now(),
            'expires_at' => now()->addHours(24),
        ]);

        return [$user, $token];
    }

    public function test_admin_can_ban_user_with_reason_and_it_revokes_sessions(): void
    {
        $admin = $this->admin();
        [$user, $token] = $this->userWithSession();

        $this->actingAs($admin)
            ->patch(route('admin.users.status', $user), [
                'action' => 'ban',
                'reason' => 'Spamming contest votes',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'User banned successfully.');

        $user->refresh();
        $this->assertSame('banned', $user->status);
        $this->assertSame('Spamming contest votes', $user->status_reason);

        $this->assertDatabaseHas('moderation_actions', [
            'moderator_user_id' => $admin->id,
            'target_type' => 'user',
            'target_id' => $user->id,
            'action' => 'ban',
            'reason' => 'Spamming contest votes',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'event_name' => 'user_lifecycle_updated',
            'entity_type' => 'user',
            'entity_id' => $user->id,
        ]);

        // The live token dies with the ban.
        $this->getJson('/api/profile/me', ['Authorization' => 'Bearer '.$token])
            ->assertStatus(401);
    }

    public function test_ban_without_reason_is_rejected(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($admin)
            ->from(route('admin.users.show', $user))
            ->patch(route('admin.users.status', $user), [
                'action' => 'ban',
            ])
            ->assertSessionHasErrors('reason');

        $this->assertSame('active', $user->fresh()->status);
    }

    public function test_banned_user_with_unrevoked_token_gets_403_with_code(): void
    {
        [$user, $token] = $this->userWithSession();

        // Simulate a stale state where the status flipped but the session
        // survived — the middleware must still block it.
        $user->forceFill(['status' => 'banned', 'status_reason' => 'Fraud'])->save();

        $this->getJson('/api/profile/me', ['Authorization' => 'Bearer '.$token])
            ->assertStatus(403)
            ->assertJsonPath('code', 'account_banned')
            ->assertJsonPath('reason', 'Fraud');
    }

    public function test_admin_can_suspend_user_with_duration(): void
    {
        $admin = $this->admin();
        [$user, $token] = $this->userWithSession();

        $until = now()->addDays(3)->startOfMinute();

        $this->actingAs($admin)
            ->patch(route('admin.users.status', $user), [
                'action' => 'suspend',
                'reason' => 'Harassment in comments',
                'suspended_until' => $until->toDateTimeString(),
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'User suspended successfully.');

        $user->refresh();
        $this->assertSame('suspended', $user->status);
        $this->assertTrue($user->suspended_until->equalTo($until));

        $this->assertDatabaseHas('moderation_actions', [
            'target_id' => $user->id,
            'action' => 'suspend',
            'reason' => 'Harassment in comments',
        ]);

        $this->getJson('/api/profile/me', ['Authorization' => 'Bearer '.$token])
            ->assertStatus(401);
    }

    public function test_suspend_preset_duration_hours_is_converted(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($admin)
            ->patch(route('admin.users.status', $user), [
                'action' => 'suspend',
                'reason' => 'Cooling off',
                'duration_hours' => 24,
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'User suspended successfully.');

        $user->refresh();
        $this->assertSame('suspended', $user->status);
        $this->assertTrue($user->suspended_until->between(now()->addHours(23), now()->addHours(25)));
    }

    public function test_suspended_user_gets_403_with_expiry_payload(): void
    {
        [$user, $token] = $this->userWithSession();

        $user->forceFill([
            'status' => 'suspended',
            'suspended_until' => now()->addDay(),
            'status_reason' => 'Repeated reports',
        ])->save();

        $this->getJson('/api/profile/me', ['Authorization' => 'Bearer '.$token])
            ->assertStatus(403)
            ->assertJsonPath('code', 'account_suspended')
            ->assertJsonPath('reason', 'Repeated reports')
            ->assertJsonStructure(['suspended_until']);
    }

    public function test_expired_suspension_is_lifted_lazily_on_api_request(): void
    {
        [$user, $token] = $this->userWithSession();

        $user->forceFill([
            'status' => 'suspended',
            'suspended_until' => now()->subMinute(),
            'status_reason' => 'Time served',
        ])->save();

        $this->getJson('/api/profile/me', ['Authorization' => 'Bearer '.$token])
            ->assertOk();

        $user->refresh();
        $this->assertSame('active', $user->status);
        $this->assertNull($user->suspended_until);
        $this->assertNull($user->status_reason);

        $this->assertDatabaseHas('activity_logs', [
            'event_name' => 'user_suspension_expired',
            'entity_id' => $user->id,
        ]);
    }

    public function test_suspended_unverified_user_cannot_get_a_session_via_email_otp(): void
    {
        $admin = $this->admin();

        $user = User::factory()->unverified()->create([
            'email' => 'sneaky@example.com',
            'status' => 'active',
        ]);

        // A pending registration OTP the user could redeem after being suspended.
        \App\Models\EmailVerificationToken::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'purpose' => 'register',
            'token_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(15),
            'created_at' => now(),
        ]);
        $code = '123456';

        $this->actingAs($admin)
            ->patch(route('admin.users.status', $user), [
                'action' => 'suspend',
                'reason' => 'Suspended before verification',
                'duration_hours' => 72,
            ])
            ->assertRedirect();

        $this->assertSame('suspended', $user->fresh()->status);

        // The old bug: this used to flip the account straight back to active.
        $this->postJson('/api/auth/verify-email-otp', [
            'email' => 'sneaky@example.com',
            'code' => $code,
        ])
            ->assertStatus(403)
            ->assertJsonPath('code', 'account_suspended');

        $fresh = $user->fresh();
        $this->assertSame('suspended', $fresh->status);
        $this->assertNotNull($fresh->email_verified_at);
        $this->assertDatabaseMissing('user_sessions', ['user_id' => $user->id]);
    }

    public function test_bulk_ban_skips_admins_and_self(): void
    {
        $admin = $this->admin();
        $otherAdmin = $this->admin();
        [$userA] = $this->userWithSession();
        [$userB] = $this->userWithSession();

        $this->actingAs($admin)
            ->patch(route('admin.users.bulk_lifecycle'), [
                'user_ids' => [$userA->id, $userB->id, $otherAdmin->id, $admin->id],
                'action' => 'ban',
                'reason' => 'Coordinated spam ring',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame('banned', $userA->fresh()->status);
        $this->assertSame('banned', $userB->fresh()->status);
        $this->assertSame('active', $otherAdmin->fresh()->status);
        $this->assertSame('active', $admin->fresh()->status);

        $this->assertSame(2, \App\Models\ModerationAction::query()
            ->where('action', 'ban')
            ->where('reason', 'Coordinated spam ring')
            ->count());

        $this->assertSame(0, UserSession::query()
            ->whereIn('user_id', [$userA->id, $userB->id])
            ->whereNull('revoked_at')
            ->count());

        $this->assertDatabaseHas('activity_logs', [
            'event_name' => 'user_bulk_lifecycle_updated',
        ]);
    }

    public function test_bulk_suspend_with_preset_duration(): void
    {
        $admin = $this->admin();
        $userA = User::factory()->create(['status' => 'active']);
        $userB = User::factory()->create(['status' => 'active']);

        $this->actingAs($admin)
            ->patch(route('admin.users.bulk_lifecycle'), [
                'user_ids' => [$userA->id, $userB->id],
                'action' => 'suspend',
                'reason' => 'Investigating reports',
                'duration_hours' => 168,
            ])
            ->assertRedirect();

        foreach ([$userA, $userB] as $user) {
            $fresh = $user->fresh();
            $this->assertSame('suspended', $fresh->status);
            $this->assertTrue($fresh->suspended_until->between(now()->addHours(167), now()->addHours(169)));
        }
    }

    public function test_admin_cannot_ban_own_account(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->patch(route('admin.users.status', $admin), [
                'action' => 'ban',
                'reason' => 'Oops',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'You cannot apply this lifecycle action to your own account.');

        $this->assertSame('active', $admin->fresh()->status);
    }

    public function test_report_queue_can_suspend_reported_user(): void
    {
        $admin = $this->admin();
        $reporter = User::factory()->create();
        [$target] = $this->userWithSession();

        $report = Report::create([
            'reporter_user_id' => $reporter->id,
            'target_type' => 'user',
            'target_id' => $target->id,
            'reason' => 'harassment',
            'status' => 'open',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.moderation.reports.target.update', $report), [
                'action' => 'suspend',
                'reason' => 'Suspended pending investigation',
                'duration_hours' => 72,
            ])
            ->assertRedirect();

        $fresh = $target->fresh();
        $this->assertSame('suspended', $fresh->status);
        $this->assertSame('Suspended pending investigation', $fresh->status_reason);

        $this->assertDatabaseHas('moderation_actions', [
            'target_type' => 'user',
            'target_id' => $target->id,
            'action' => 'suspend',
            'reason' => 'Suspended pending investigation',
        ]);

        $this->assertSame('under_review', $report->fresh()->status);
    }

    public function test_activate_reinstates_banned_user_and_logs_unban(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create([
            'status' => 'banned',
            'status_reason' => 'Old ban',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.users.status', $user), [
                'action' => 'activate',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'User activated successfully.');

        $fresh = $user->fresh();
        $this->assertSame('active', $fresh->status);
        $this->assertNull($fresh->status_reason);

        $this->assertDatabaseHas('moderation_actions', [
            'target_id' => $user->id,
            'action' => 'unban',
        ]);
    }
}
