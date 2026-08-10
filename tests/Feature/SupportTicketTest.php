<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupportTicketTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: string}
     */
    private function appUser(array $attributes = []): array
    {
        $user = User::factory()->create($attributes + ['status' => 'active']);
        $token = Str::random(80);

        UserSession::create([
            'user_id' => $user->id,
            'session_token_hash' => hash('sha256', $token),
            'platform' => 'android',
            'last_seen_at' => now(),
            'expires_at' => now()->addDay(),
        ]);

        return [$user, $token];
    }

    private function headers(string $token): array
    {
        return ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$token];
    }

    private function staff(string $role = 'support_agent'): User
    {
        $user = User::factory()->create(['role' => 'user', 'status' => 'active']);

        // Panel access comes from the Spatie role, not the legacy enum column —
        // a Support Agent stays an ordinary `user` in the app.
        $user->syncRoles([$role]);

        return $user;
    }

    private function openTicket(User $user, array $attributes = []): SupportTicket
    {
        return app(\App\Services\SupportTicketService::class)->create($user, array_merge([
            'category' => 'bug',
            'subject' => 'App crashes on reels',
            'body' => 'It closes right after the first reel.',
        ], $attributes));
    }

    public function test_support_meta_lists_categories_and_statuses(): void
    {
        [, $token] = $this->appUser();

        $this->withHeaders($this->headers($token))
            ->getJson('/api/support/meta')
            ->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('categories.0.value', 'bug')
            ->assertJsonPath('max_attachments', 5);
    }

    public function test_a_user_can_open_a_bug_ticket_with_device_details(): void
    {
        [$user, $token] = $this->appUser();

        $response = $this->withHeaders($this->headers($token))
            ->postJson('/api/support/tickets', [
                'category' => 'bug',
                'subject' => 'Reels crash',
                'body' => 'Crashes after the first video.',
                'app_version' => '1.4.2',
                'platform' => 'android',
                'device_model' => 'Pixel 7',
                'os_version' => 'Android 14',
            ])
            ->assertCreated()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('ticket.status', 'open')
            ->assertJsonPath('ticket.category', 'bug')
            ->assertJsonCount(1, 'ticket.messages');

        $ticket = SupportTicket::sole();

        // Quotable reference, not the raw id.
        $this->assertSame('TK-'.str_pad((string) $ticket->id, 6, '0', STR_PAD_LEFT), $ticket->reference);
        $this->assertSame($response->json('ticket.reference'), $ticket->reference);
        $this->assertSame('Pixel 7', $ticket->device_model);
        $this->assertSame('1.4.2', $ticket->app_version);
        $this->assertSame(1, $ticket->messages_count);
        $this->assertSame('user', $ticket->last_reply_by);
        $this->assertSame($user->id, (int) $ticket->user_id);
    }

    public function test_a_ticket_can_carry_screenshot_attachments(): void
    {
        [, $token] = $this->appUser();

        $this->withHeaders($this->headers($token))
            ->post('/api/support/tickets', [
                'category' => 'bug',
                'subject' => 'Visual glitch',
                'body' => 'See screenshots.',
                'attachments' => [
                    UploadedFile::fake()->image('one.jpg'),
                    UploadedFile::fake()->image('two.png'),
                ],
            ])
            ->assertCreated()
            ->assertJsonCount(2, 'ticket.messages.0.attachments');

        $this->assertDatabaseCount('support_ticket_attachments', 2);
    }

    public function test_a_user_only_sees_their_own_tickets(): void
    {
        [$mine, $myToken] = $this->appUser();
        [$theirs] = $this->appUser();

        $myTicket = $this->openTicket($mine);
        $theirTicket = $this->openTicket($theirs, ['subject' => 'Not mine']);

        $this->withHeaders($this->headers($myToken))
            ->getJson('/api/support/tickets')
            ->assertOk()
            ->assertJsonCount(1, 'tickets')
            ->assertJsonPath('tickets.0.id', $myTicket->id);

        // Someone else's ticket id must be indistinguishable from a missing one.
        $this->withHeaders($this->headers($myToken))
            ->getJson('/api/support/tickets/'.$theirTicket->id)
            ->assertStatus(404);
    }

    public function test_internal_staff_notes_never_reach_the_mobile_api(): void
    {
        [$user, $token] = $this->appUser();
        $ticket = $this->openTicket($user);
        $agent = $this->staff();

        $service = app(\App\Services\SupportTicketService::class);
        $service->addMessage($ticket, $agent, 'staff', 'Public answer for the user.', false);
        $service->addMessage($ticket, $agent, 'staff', 'SECRET internal note about this account.', true);

        $response = $this->withHeaders($this->headers($token))
            ->getJson('/api/support/tickets/'.$ticket->id)
            ->assertOk();

        $body = $response->getContent();
        $this->assertStringContainsString('Public answer for the user.', $body);
        $this->assertStringNotContainsString('SECRET internal note', $body);

        // The internal note must not inflate the visible reply count either.
        $this->assertSame(2, $ticket->fresh()->messages_count);
    }

    public function test_a_staff_reply_moves_the_ticket_to_waiting_on_user_and_notifies_them(): void
    {
        [$user] = $this->appUser();
        $ticket = $this->openTicket($user);
        $agent = $this->staff();

        app(\App\Services\SupportTicketService::class)
            ->addMessage($ticket, $agent, 'staff', 'Can you try reinstalling?', false);

        $ticket->refresh();
        $this->assertSame('waiting_on_user', $ticket->status);
        $this->assertSame('staff', $ticket->last_reply_by);

        $notification = Notification::where('recipient_user_id', $user->id)->latest('id')->first();
        $this->assertNotNull($notification);
        $this->assertSame('support', $notification->type);
        $this->assertSame('support_ticket', $notification->entity_type);
        $this->assertSame($ticket->id, (int) $notification->entity_id);
    }

    public function test_an_internal_note_does_not_notify_or_move_the_ticket(): void
    {
        [$user] = $this->appUser();
        $ticket = $this->openTicket($user);
        $agent = $this->staff();

        app(\App\Services\SupportTicketService::class)
            ->addMessage($ticket, $agent, 'staff', 'Internal only.', true);

        $ticket->refresh();
        $this->assertSame('open', $ticket->status);
        $this->assertSame('user', $ticket->last_reply_by);
        $this->assertSame(0, Notification::where('recipient_user_id', $user->id)->count());
    }

    public function test_a_user_reply_reopens_a_resolved_ticket(): void
    {
        [$user, $token] = $this->appUser();
        $ticket = $this->openTicket($user);
        $agent = $this->staff();

        app(\App\Services\SupportTicketService::class)->changeStatus($ticket, 'resolved', $agent);
        $this->assertSame('resolved', $ticket->fresh()->status);

        $this->withHeaders($this->headers($token))
            ->postJson('/api/support/tickets/'.$ticket->id.'/messages', ['body' => 'Still broken.'])
            ->assertOk();

        // Evidently not resolved after all.
        $this->assertSame('open', $ticket->fresh()->status);
        $this->assertNull($ticket->fresh()->resolved_at);
    }

    public function test_a_closed_ticket_cannot_be_replied_to(): void
    {
        [$user, $token] = $this->appUser();
        $ticket = $this->openTicket($user);

        $this->withHeaders($this->headers($token))
            ->patchJson('/api/support/tickets/'.$ticket->id.'/close')
            ->assertOk();

        $this->assertSame('closed', $ticket->fresh()->status);

        $this->withHeaders($this->headers($token))
            ->postJson('/api/support/tickets/'.$ticket->id.'/messages', ['body' => 'One more thing.'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'This ticket is closed. Please open a new one.');
    }

    public function test_open_tickets_are_capped_per_user(): void
    {
        [$user, $token] = $this->appUser();

        for ($i = 0; $i < 5; $i++) {
            $this->openTicket($user, ['subject' => 'Ticket '.$i]);
        }

        $this->withHeaders($this->headers($token))
            ->postJson('/api/support/tickets', [
                'category' => 'other',
                'subject' => 'One too many',
                'body' => 'Body',
            ])
            ->assertStatus(429);

        $this->assertSame(5, SupportTicket::count());
    }

    public function test_unread_count_reflects_staff_replies_until_the_user_opens_the_ticket(): void
    {
        [$user, $token] = $this->appUser();
        $ticket = $this->openTicket($user);
        $agent = $this->staff();

        app(\App\Services\SupportTicketService::class)
            ->addMessage($ticket, $agent, 'staff', 'Answer one.', false);

        $this->withHeaders($this->headers($token))
            ->getJson('/api/support/tickets')
            ->assertOk()
            ->assertJsonPath('tickets.0.unread_count', 1);

        $this->withHeaders($this->headers($token))
            ->getJson('/api/support/tickets/'.$ticket->id)
            ->assertOk();

        $this->withHeaders($this->headers($token))
            ->getJson('/api/support/tickets')
            ->assertOk()
            ->assertJsonPath('tickets.0.unread_count', 0);
    }

    public function test_support_agent_can_work_a_ticket_in_the_panel(): void
    {
        [$user] = $this->appUser();
        $ticket = $this->openTicket($user);
        $agent = $this->staff();

        $this->actingAs($agent)->get(route('admin.support.index'))->assertOk()->assertSee($ticket->reference);
        $this->actingAs($agent)->get(route('admin.support.show', $ticket))->assertOk()->assertSee('App crashes on reels');

        $this->actingAs($agent)
            ->patch(route('admin.support.claim', $ticket))
            ->assertRedirect();

        $ticket->refresh();
        $this->assertSame($agent->id, (int) $ticket->assigned_to_user_id);
        $this->assertSame('in_progress', $ticket->status);

        $this->actingAs($agent)
            ->post(route('admin.support.reply', $ticket), ['body' => 'We are on it.'])
            ->assertRedirect();

        $this->assertSame('waiting_on_user', $ticket->fresh()->status);
    }

    public function test_a_content_moderator_can_read_but_not_answer_tickets(): void
    {
        [$user] = $this->appUser();
        $ticket = $this->openTicket($user);
        $moderator = $this->staff('content_moderator');

        $this->actingAs($moderator)->get(route('admin.support.index'))->assertOk();

        $this->actingAs($moderator)
            ->post(route('admin.support.reply', $ticket), ['body' => 'Not my job.'])
            ->assertForbidden();

        $this->actingAs($moderator)
            ->patch(route('admin.support.update', $ticket), ['status' => 'closed'])
            ->assertForbidden();
    }

    public function test_staff_without_ticket_permissions_cannot_see_the_queue(): void
    {
        $finance = $this->staff('finance_manager');

        $this->actingAs($finance)->get(route('admin.support.index'))->assertForbidden();
    }

    public function test_status_changes_leave_a_system_note_in_the_thread(): void
    {
        [$user] = $this->appUser();
        $ticket = $this->openTicket($user);
        $agent = $this->staff();

        app(\App\Services\SupportTicketService::class)->changeStatus($ticket, 'in_progress', $agent);

        $note = SupportTicketMessage::where('support_ticket_id', $ticket->id)
            ->where('author_type', 'system')
            ->latest('id')
            ->first();

        $this->assertNotNull($note);
        $this->assertTrue($note->is_internal);
        $this->assertStringContainsString('Open', $note->body);
        $this->assertStringContainsString('In progress', $note->body);
    }

    public function test_tickets_require_authentication(): void
    {
        $this->getJson('/api/support/tickets')->assertStatus(401);
        $this->postJson('/api/support/tickets', [])->assertStatus(401);
    }
}
