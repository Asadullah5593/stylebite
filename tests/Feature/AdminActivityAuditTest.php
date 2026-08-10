<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminActivityAuditTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'status' => 'active']);
    }

    private function makePost(?User $author = null): \App\Models\Post
    {
        return \App\Models\Post::create([
            'user_id' => ($author ?? User::factory()->create())->id,
            'post_type' => 'outfit',
            'content_type' => 'fashion',
            'media_kind' => 'carousel',
            'feed_type' => 'style',
            'caption' => 'Audit trail fixture',
            'visibility' => 'public',
            'status' => 'published',
            'moderation_status' => 'clean',
            'allow_comments' => true,
            'allow_shares' => true,
            'rating_enabled' => true,
            'published_at' => now(),
        ]);
    }

    public function test_every_mutating_admin_request_is_audited_even_without_a_domain_log(): void
    {
        $admin = $this->admin();
        $post = $this->makePost();

        $this->actingAs($admin)
            ->patch(route('admin.posts.moderate', $post), [
                'status' => 'under_review',
                'moderation_status' => 'flagged',
                'reason' => 'Flagged for review after a report.',
            ])
            ->assertRedirect();

        $log = ActivityLog::query()->where('event_name', 'post_moderated')->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame('admin', $log->actor_type);
        $this->assertSame('admin', $log->actor_role);
        $this->assertSame('PATCH', $log->http_method);
        $this->assertSame('admin.posts.moderate', $log->route_name);
        $this->assertSame($post->id, $log->entity_id);
        $this->assertNotNull($log->ip_address);
        $this->assertNotNull($log->request_id);
        // Stamped by the middleware after the response.
        $this->assertSame(302, $log->response_status);
        $this->assertTrue($log->wasApplied());
    }

    public function test_action_without_its_own_log_still_produces_one_generic_row(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['status' => 'active']);

        // Streak restore/reset write their own logs, so use an endpoint that
        // has no bespoke log: toggling a device is logged, so pick the
        // engagement-free path of assigning a report instead.
        $report = \App\Models\Report::create([
            'reporter_user_id' => $target->id,
            'target_type' => 'user',
            'target_id' => $target->id,
            'reason' => 'spam',
            'status' => 'open',
        ]);

        $before = ActivityLog::count();

        $this->actingAs($admin)
            ->patch(route('admin.moderation.reports.assign', $report))
            ->assertRedirect();

        // Exactly one row per action — the domain log, not a duplicate.
        $this->assertSame($before + 1, ActivityLog::count());
        $this->assertSame('report_assigned', ActivityLog::latest('id')->first()->event_name);
    }

    public function test_blocked_attempt_is_audited_with_403(): void
    {
        $staff = User::factory()->create(['status' => 'active']);
        $staff->assignRole('support_agent');

        $target = User::factory()->create(['status' => 'active']);

        $this->actingAs($staff)
            ->patch(route('admin.users.status', $target), [
                'action' => 'ban',
                'reason' => 'Not allowed to do this',
            ])
            ->assertForbidden();

        $log = ActivityLog::query()->where('route_name', 'admin.users.status')->latest('id')->first();

        $this->assertNotNull($log, 'A blocked attempt must still be recorded.');
        $this->assertSame($staff->id, $log->user_id);
        $this->assertSame('support_agent', $log->actor_role);
        $this->assertSame(403, $log->response_status);
        $this->assertFalse($log->wasApplied());
        $this->assertStringContainsString('blocked', $log->description);
        // The account was not touched.
        $this->assertSame('active', $target->fresh()->status);
    }

    public function test_rejected_input_is_audited_and_credentials_are_never_stored(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'username' => 'not a valid username!',
                'email' => 'invalid-email',
                'password' => 'sup3r-secret-value',
                'password_confirmation' => 'sup3r-secret-value',
                'role' => 'user',
                'status' => 'active',
            ])
            ->assertSessionHasErrors();

        $log = ActivityLog::query()->where('route_name', 'admin.users.store')->latest('id')->first();

        $this->assertNotNull($log);
        // A refused web form redirects back with errors, so the outcome column
        // — not the HTTP status — is what records the refusal.
        $this->assertSame(ActivityLog::OUTCOME_REJECTED, $log->outcome);
        $this->assertFalse($log->wasApplied());
        $this->assertContains('email', $log->metadata_json['validation_errors']);

        $encoded = json_encode($log->metadata_json);
        $this->assertStringNotContainsString('sup3r-secret-value', $encoded);
        $this->assertStringNotContainsString('password', $encoded);
        // The attempted values are kept, so you can see what they tried.
        $this->assertStringContainsString('invalid-email', $encoded);
    }

    public function test_a_successful_action_after_a_rejected_one_is_not_mislabelled(): void
    {
        $admin = $this->admin();
        $post = $this->makePost();

        // Rejected: moderation_status is required.
        $this->actingAs($admin)
            ->patch(route('admin.posts.moderate', $post), ['status' => 'published'])
            ->assertSessionHasErrors();

        $this->assertSame(
            ActivityLog::OUTCOME_REJECTED,
            ActivityLog::query()->where('route_name', 'admin.posts.moderate')->latest('id')->first()->outcome
        );

        // Now a valid one — the stale flashed errors must not taint it.
        $this->actingAs($admin)
            ->patch(route('admin.posts.moderate', $post), [
                'status' => 'published',
                'moderation_status' => 'clean',
                'reason' => 'Reviewed and cleared.',
            ])
            ->assertRedirect();

        $log = ActivityLog::query()->where('event_name', 'post_moderated')->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame(ActivityLog::OUTCOME_APPLIED, $log->outcome);
        $this->assertTrue($log->wasApplied());
    }

    public function test_successful_and_failed_admin_sign_ins_are_audited(): void
    {
        // The audit trail is the subject; the second factor is covered
        // separately in AdminTwoFactorTest.
        config(['auth.admin_two_factor' => false]);

        $admin = User::factory()->create([
            'email' => 'boss@example.com',
            'password_hash' => Hash::make('secret-password'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->post('/admin/login', [
            'email' => 'boss@example.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $failed = ActivityLog::query()->where('event_name', 'admin_login_failed')->latest('id')->first();
        $this->assertNotNull($failed);
        $this->assertSame('system', $failed->actor_type);
        $this->assertSame('wrong_password', $failed->metadata_json['reason']);
        $this->assertSame('boss@example.com', $failed->metadata_json['email']);
        $this->assertStringNotContainsString('wrong-password', json_encode($failed->metadata_json));

        $this->post('/admin/login', [
            'email' => 'boss@example.com',
            'password' => 'secret-password',
        ])->assertRedirect(route('admin.dashboard'));

        $signedIn = ActivityLog::query()->where('event_name', 'admin_signed_in')->latest('id')->first();
        $this->assertNotNull($signedIn);
        $this->assertSame($admin->id, $signedIn->user_id);

        $this->post('/admin/logout')->assertRedirect(route('admin.login'));

        $signedOut = ActivityLog::query()->where('event_name', 'admin_signed_out')->latest('id')->first();
        $this->assertNotNull($signedOut);
        $this->assertSame($admin->id, $signedOut->user_id);
    }

    public function test_login_attempt_by_an_account_without_panel_access_is_audited(): void
    {
        User::factory()->create([
            'email' => 'plain@example.com',
            'password_hash' => Hash::make('secret-password'),
            'role' => 'user',
            'status' => 'active',
        ]);

        $this->post('/admin/login', [
            'email' => 'plain@example.com',
            'password' => 'secret-password',
        ])->assertSessionHasErrors('email');

        $log = ActivityLog::query()->where('event_name', 'admin_login_failed')->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame('no_panel_access', $log->metadata_json['reason']);
    }

    public function test_reading_private_conversations_is_audited_but_ordinary_page_views_are_not(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.messaging.messages'))->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'route_name' => 'admin.messaging.messages',
            'user_id' => $admin->id,
            'http_method' => 'GET',
        ]);

        $before = ActivityLog::count();

        // A normal listing page is not sensitive — no row.
        $this->actingAs($admin)->get(route('admin.posts.all_posts'))->assertOk();

        $this->assertSame($before, ActivityLog::count());
    }

    public function test_admin_only_sees_audit_page_with_permission_and_can_export_csv(): void
    {
        $admin = $this->admin();

        $support = User::factory()->create(['status' => 'active']);
        $support->assignRole('support_agent');

        $this->actingAs($admin)->get(route('admin.activity.activity_logs'))->assertOk();
        // Support Agent has no activity.view permission.
        $this->actingAs($support)->get(route('admin.activity.activity_logs'))->assertForbidden();

        $response = $this->actingAs($admin)->get(route('admin.activity.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();
        $this->assertStringContainsString('Actor Role', $csv);
        $this->assertStringContainsString('Outcome', $csv);

        // Exporting the audit trail is itself audited.
        $this->assertDatabaseHas('activity_logs', [
            'event_name' => 'activity_log_exported',
            'user_id' => $admin->id,
        ]);
    }

    public function test_audit_page_filters_by_outcome_and_staff_member(): void
    {
        $admin = $this->admin();

        $staff = User::factory()->create(['status' => 'active']);
        $staff->assignRole('support_agent');
        $target = User::factory()->create(['status' => 'active']);

        // One blocked attempt by the support agent.
        $this->actingAs($staff)
            ->patch(route('admin.users.status', $target), ['action' => 'ban', 'reason' => 'nope'])
            ->assertForbidden();

        $blocked = $this->actingAs($admin)
            ->get(route('admin.activity.activity_logs', ['outcome' => 'blocked']));

        $blocked->assertOk()->assertSee('Blocked');

        $filtered = $this->actingAs($admin)
            ->get(route('admin.activity.activity_logs', ['user_id' => $staff->id, 'outcome' => 'blocked']));

        $filtered->assertOk();

        $this->assertSame(
            1,
            ActivityLog::query()->where('user_id', $staff->id)->where('response_status', 403)->count()
        );
    }

    public function test_csv_export_contains_every_matching_row_not_just_the_first_chunk(): void
    {
        $admin = $this->admin();

        // More than the 500-row export chunk, so a broken keyset pagination
        // (which silently stops after one chunk) cannot pass. Inserted oldest
        // first, so newer rows carry higher ids exactly as they do in
        // production — the ordering that breaks naive id-based chunking.
        $rows = [];
        $total = 1200;

        for ($i = 0; $i < $total; $i++) {
            $rows[] = [
                'user_id' => null,
                'actor_type' => 'system',
                'event_name' => 'bulk_fixture_event',
                'created_at' => now()->subMinutes($total - $i),
            ];
        }

        ActivityLog::insert($rows);

        $expected = ActivityLog::where('event_name', 'bulk_fixture_event')->count();
        $this->assertSame(1200, $expected);

        $csv = $this->actingAs($admin)
            ->get(route('admin.activity.export', ['event_name' => 'bulk_fixture_event']))
            ->assertOk()
            ->streamedContent();

        $ids = [];

        foreach (preg_split('/\R/', trim($csv)) as $index => $line) {
            if ($index === 0 || $line === '') {
                continue; // header
            }

            $ids[] = str_getcsv($line)[0];
        }

        $this->assertCount($expected, $ids, 'Every filtered row must reach the CSV.');
        $this->assertCount($expected, array_unique($ids), 'The CSV must not repeat rows.');
    }

    public function test_prune_command_keeps_recent_rows_and_refuses_short_windows(): void
    {
        ActivityLog::create([
            'user_id' => null,
            'actor_type' => 'system',
            'event_name' => 'old_event',
            'created_at' => now()->subDays(400),
        ]);

        ActivityLog::create([
            'user_id' => null,
            'actor_type' => 'system',
            'event_name' => 'recent_event',
            'created_at' => now()->subDay(),
        ]);

        $this->artisan('stylebite:prune-activity-logs', ['--days' => 10])
            ->assertExitCode(1);

        $this->assertDatabaseHas('activity_logs', ['event_name' => 'old_event']);

        $this->artisan('stylebite:prune-activity-logs')->assertExitCode(0);

        $this->assertDatabaseMissing('activity_logs', ['event_name' => 'old_event']);
        $this->assertDatabaseHas('activity_logs', ['event_name' => 'recent_event']);
    }
}
