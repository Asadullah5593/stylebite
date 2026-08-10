<?php

namespace Tests\Feature;

use App\Jobs\ProcessNotificationCampaign;
use App\Models\DeviceToken;
use App\Models\Notification;
use App\Models\NotificationCampaign;
use App\Models\Post;
use App\Models\Profile;
use App\Models\PushNotificationLog;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationCampaignTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'status' => 'active']);
    }

    /**
     * A recipient with no device token never reaches the push transport — the
     * delivery layer marks it 'skipped'. That keeps most of these tests focused
     * on audience/chunking behaviour without needing Firebase at all.
     */
    private function recipients(int $count, array $attributes = []): void
    {
        for ($i = 0; $i < $count; $i++) {
            User::factory()->create($attributes + ['status' => 'active']);
        }
    }

    /**
     * Point Firebase at a throwaway service account with a real RSA key so the
     * JWT signs, then fake the token + FCM endpoints.
     */
    private function fakeFirebase(bool $succeed = true): string
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $privateKey);

        $path = storage_path('framework/testing/service_account_'.uniqid().'.json');
        @mkdir(dirname($path), 0755, true);
        file_put_contents($path, json_encode([
            'project_id' => 'stylebite-test',
            'client_email' => 'tester@stylebite-test.iam.gserviceaccount.com',
            'private_key' => $privateKey,
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]));

        config([
            'services.firebase.service_account_path' => $path,
            'services.firebase.project_id' => 'stylebite-test',
        ]);

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'test-token', 'expires_in' => 3600]),
            'fcm.googleapis.com/*' => $succeed
                ? Http::response(['name' => 'projects/stylebite-test/messages/1'])
                : Http::response(['error' => ['message' => 'Requested entity was not found.']], 404),
        ]);

        return $path;
    }

    public function test_sending_an_announcement_queues_a_campaign_instead_of_fanning_out_in_the_request(): void
    {
        Queue::fake();

        $admin = $this->admin();
        $this->recipients(5);

        $this->actingAs($admin)
            ->post(route('admin.notifications.announcements.send'), [
                'audience_type' => 'all_active',
                'title' => 'Season kickoff',
                'body' => 'The new contest is live.',
            ])
            ->assertRedirect();

        $campaign = NotificationCampaign::sole();
        $this->assertSame('all_active', $campaign->audience_type);
        $this->assertSame('pending', $campaign->status);
        $this->assertSame($admin->id, $campaign->created_by_user_id);
        $this->assertSame('All active users', $campaign->audience_label);

        // The request itself must not deliver anything.
        $this->assertSame(0, Notification::count());

        Queue::assertPushed(ProcessNotificationCampaign::class, fn ($job) => $job->campaignId === $campaign->id);

        $this->assertDatabaseHas('activity_logs', [
            'event_name' => 'notification_campaign_created',
            'entity_id' => $campaign->id,
        ]);
    }

    public function test_campaign_delivers_to_everyone_exactly_once_across_chunks(): void
    {
        config(['notifications.campaign_chunk_size' => 3]);

        $admin = $this->admin();
        $this->recipients(10);

        $campaign = NotificationCampaign::create([
            'created_by_user_id' => $admin->id,
            'audience_type' => 'all_active',
            'audience_label' => 'All active users',
            'title' => 'Chunked',
            'body' => 'Body',
            'status' => 'pending',
        ]);

        // 11 = 10 recipients + the admin, who is also an active user.
        $expected = User::where('status', 'active')->count();
        $this->assertSame(11, $expected);

        // Drain the self-redispatching job chain.
        for ($run = 0; $run < 10 && ! $campaign->fresh()->isFinished(); $run++) {
            (new ProcessNotificationCampaign($campaign->id))->handle(app(\App\Services\NotificationAudience::class));
        }

        $campaign->refresh();
        $this->assertSame('completed', $campaign->status);
        $this->assertSame($expected, $campaign->processed_count);
        $this->assertSame($expected, $campaign->total_recipients);
        $this->assertSame($expected, Notification::count());

        // Exactly once each — the keyset cursor must not re-send a chunk.
        $this->assertSame($expected, Notification::distinct('recipient_user_id')->count('recipient_user_id'));
        $this->assertNotNull($campaign->completed_at);
    }

    public function test_campaign_never_delivers_to_banned_or_suspended_users(): void
    {
        $admin = $this->admin();
        $active = User::factory()->create(['status' => 'active']);
        $banned = User::factory()->create(['status' => 'banned']);
        $suspended = User::factory()->create(['status' => 'suspended', 'suspended_until' => now()->addDay()]);

        $campaign = NotificationCampaign::create([
            'created_by_user_id' => $admin->id,
            'audience_type' => 'all_active',
            'title' => 'Only the living',
            'body' => 'Body',
            'status' => 'pending',
        ]);

        (new ProcessNotificationCampaign($campaign->id))->handle(app(\App\Services\NotificationAudience::class));

        $recipientIds = Notification::pluck('recipient_user_id')->all();

        $this->assertContains($active->id, $recipientIds);
        $this->assertContains($admin->id, $recipientIds);
        $this->assertNotContains($banned->id, $recipientIds);
        $this->assertNotContains($suspended->id, $recipientIds);
    }

    public function test_the_sending_admin_still_receives_their_own_campaign(): void
    {
        // Regression: campaigns used to pass the admin as the notification actor,
        // so the delivery layer's "don't notify yourself" guard silently skipped
        // the sender while still counting them as reached. The admin needs a real
        // device here, otherwise "skipped" would be legitimate (no device) and
        // the assertion would prove nothing.
        $this->fakeFirebase();

        $admin = $this->admin();

        DeviceToken::create([
            'user_id' => $admin->id,
            'device_id' => 'admin-device',
            'platform' => 'android',
            'push_token' => 'token-admin',
            'is_active' => true,
        ]);

        $campaign = NotificationCampaign::create([
            'created_by_user_id' => $admin->id,
            'audience_type' => 'specific',
            'audience_payload' => ['user_ids' => [$admin->id]],
            'title' => 'To myself',
            'body' => 'Body',
            'status' => 'pending',
        ]);

        (new ProcessNotificationCampaign($campaign->id))->handle(app(\App\Services\NotificationAudience::class));

        $notification = Notification::sole();
        $this->assertSame($admin->id, (int) $notification->recipient_user_id);
        $this->assertNull($notification->actor_user_id);
        $this->assertSame('sent', $notification->delivery_status);
        $this->assertSame(1, $campaign->fresh()->processed_count);
        $this->assertSame(1, $campaign->fresh()->sent_count);
    }

    public function test_city_audience_targets_only_matching_profiles(): void
    {
        $admin = $this->admin();

        $lahore = User::factory()->create(['status' => 'active']);
        Profile::create(['user_id' => $lahore->id, 'display_name' => 'L', 'city' => 'Lahore']);

        $karachi = User::factory()->create(['status' => 'active']);
        Profile::create(['user_id' => $karachi->id, 'display_name' => 'K', 'city' => 'Karachi']);

        $noCity = User::factory()->create(['status' => 'active']);
        Profile::create(['user_id' => $noCity->id, 'display_name' => 'N']);

        $campaign = NotificationCampaign::create([
            'created_by_user_id' => $admin->id,
            'audience_type' => 'city',
            'audience_payload' => ['cities' => ['Lahore']],
            'title' => 'Lahore only',
            'body' => 'Body',
            'status' => 'pending',
        ]);

        (new ProcessNotificationCampaign($campaign->id))->handle(app(\App\Services\NotificationAudience::class));

        $this->assertSame([$lahore->id], Notification::pluck('recipient_user_id')->map(fn ($id) => (int) $id)->all());
    }

    public function test_active_posters_audience_respects_the_day_window(): void
    {
        $admin = $this->admin();

        $recent = User::factory()->create(['status' => 'active']);
        Post::create([
            'user_id' => $recent->id,
            'post_type' => 'outfit',
            'content_type' => 'fashion',
            'feed_type' => 'style',
            'status' => 'published',
            'created_at' => now()->subDays(3),
        ]);

        $stale = User::factory()->create(['status' => 'active']);
        Post::create([
            'user_id' => $stale->id,
            'post_type' => 'outfit',
            'content_type' => 'fashion',
            'feed_type' => 'style',
            'status' => 'published',
            'created_at' => now()->subDays(90),
        ]);

        User::factory()->create(['status' => 'active']); // never posted

        $campaign = NotificationCampaign::create([
            'created_by_user_id' => $admin->id,
            'audience_type' => 'active_posters',
            'audience_payload' => ['poster_days' => 30],
            'title' => 'Posters',
            'body' => 'Body',
            'status' => 'pending',
        ]);

        (new ProcessNotificationCampaign($campaign->id))->handle(app(\App\Services\NotificationAudience::class));

        $this->assertSame([$recent->id], Notification::pluck('recipient_user_id')->map(fn ($id) => (int) $id)->all());
    }

    public function test_creator_role_audience_targets_designated_creators(): void
    {
        $admin = $this->admin();
        $creator = User::factory()->create(['status' => 'active', 'role' => 'creator']);
        User::factory()->create(['status' => 'active', 'role' => 'user']);

        $campaign = NotificationCampaign::create([
            'created_by_user_id' => $admin->id,
            'audience_type' => 'creator_role',
            'title' => 'Creators',
            'body' => 'Body',
            'status' => 'pending',
        ]);

        (new ProcessNotificationCampaign($campaign->id))->handle(app(\App\Services\NotificationAudience::class));

        $this->assertSame([$creator->id], Notification::pluck('recipient_user_id')->map(fn ($id) => (int) $id)->all());
    }

    public function test_recipient_with_push_disabled_is_counted_as_skipped_not_failed(): void
    {
        $admin = $this->admin();
        $optedOut = User::factory()->create(['status' => 'active']);
        UserSetting::create(['user_id' => $optedOut->id, 'push_notifications_enabled' => false]);

        $campaign = NotificationCampaign::create([
            'created_by_user_id' => $admin->id,
            'audience_type' => 'specific',
            'audience_payload' => ['user_ids' => [$optedOut->id]],
            'title' => 'Respect the setting',
            'body' => 'Body',
            'status' => 'pending',
        ]);

        (new ProcessNotificationCampaign($campaign->id))->handle(app(\App\Services\NotificationAudience::class));

        $campaign->refresh();
        $this->assertSame(1, $campaign->skipped_count);
        $this->assertSame(0, $campaign->failed_count);
        $this->assertSame('skipped', Notification::sole()->delivery_status);
    }

    public function test_cancelling_a_campaign_stops_further_delivery(): void
    {
        config(['notifications.campaign_chunk_size' => 2]);

        $admin = $this->admin();
        $this->recipients(6);

        $campaign = NotificationCampaign::create([
            'created_by_user_id' => $admin->id,
            'audience_type' => 'all_active',
            'title' => 'Stop me',
            'body' => 'Body',
            'status' => 'pending',
        ]);

        // One chunk goes out, then an admin stops it.
        (new ProcessNotificationCampaign($campaign->id))->handle(app(\App\Services\NotificationAudience::class));
        $delivered = Notification::count();
        $this->assertSame(2, $delivered);

        $this->actingAs($admin)
            ->patch(route('admin.notifications.campaigns.cancel', $campaign))
            ->assertRedirect();

        $campaign->refresh();
        $this->assertSame('cancelled', $campaign->status);

        // Any queued run that fires afterwards must be a no-op.
        (new ProcessNotificationCampaign($campaign->id))->handle(app(\App\Services\NotificationAudience::class));
        $this->assertSame($delivered, Notification::count());

        $this->assertDatabaseHas('activity_logs', [
            'event_name' => 'notification_campaign_cancelled',
            'entity_id' => $campaign->id,
        ]);
    }

    public function test_audience_preview_reports_the_recipient_count_before_sending(): void
    {
        $admin = $this->admin();
        $this->recipients(4);
        User::factory()->create(['status' => 'banned']);

        $this->actingAs($admin)
            ->postJson(route('admin.notifications.audience.preview'), [
                'audience_type' => 'all_active',
            ])
            ->assertOk()
            ->assertJsonPath('count', 5) // 4 recipients + the admin, banned excluded
            ->assertJsonPath('label', 'All active users');
    }

    public function test_push_is_delivered_to_every_active_device_in_one_batch(): void
    {
        $this->fakeFirebase();

        $admin = $this->admin();
        $user = User::factory()->create(['status' => 'active']);

        foreach (['device-a', 'device-b'] as $deviceId) {
            DeviceToken::create([
                'user_id' => $user->id,
                'device_id' => $deviceId,
                'platform' => 'android',
                'push_token' => 'token-'.$deviceId,
                'is_active' => true,
            ]);
        }

        $campaign = NotificationCampaign::create([
            'created_by_user_id' => $admin->id,
            'audience_type' => 'specific',
            'audience_payload' => ['user_ids' => [$user->id]],
            'title' => 'Real push',
            'body' => 'Body',
            'status' => 'pending',
        ]);

        (new ProcessNotificationCampaign($campaign->id))->handle(app(\App\Services\NotificationAudience::class));

        $campaign->refresh();
        $this->assertSame(1, $campaign->sent_count);
        $this->assertSame('sent', Notification::sole()->delivery_status);

        // One log row per device, both successful.
        $this->assertSame(2, PushNotificationLog::count());
        $this->assertSame(2, PushNotificationLog::where('status', 'sent')->count());
    }

    public function test_retrying_a_failed_push_actually_resends_and_records_the_outcome(): void
    {
        $this->fakeFirebase();

        $admin = $this->admin();
        $user = User::factory()->create(['status' => 'active']);

        $device = DeviceToken::create([
            'user_id' => $user->id,
            'device_id' => 'device-retry',
            'platform' => 'android',
            'push_token' => 'token-retry',
            'is_active' => true,
        ]);

        $notification = Notification::create([
            'recipient_user_id' => $user->id,
            'type' => 'system',
            'entity_type' => 'system',
            'title' => 'Retry me',
            'body' => 'Body',
            'delivery_status' => 'failed',
        ]);

        $failedLog = PushNotificationLog::create([
            'notification_id' => $notification->id,
            'user_id' => $user->id,
            'device_token_id' => $device->id,
            'provider' => 'fcm',
            'status' => 'failed',
            'provider_response' => 'Original failure.',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.notifications.push_logs.retry', $failedLog))
            ->assertRedirect();

        // A real send happened and its true outcome was recorded.
        $this->assertSame(2, PushNotificationLog::count());
        $this->assertSame(1, PushNotificationLog::where('status', 'sent')->count());
        $this->assertSame(0, PushNotificationLog::where('status', 'queued')->count());
        $this->assertSame('sent', $notification->fresh()->delivery_status);
        $this->assertNotNull($notification->fresh()->push_sent_at);
    }

    public function test_a_failed_retry_never_downgrades_an_already_sent_notification(): void
    {
        $this->fakeFirebase(succeed: false);

        $admin = $this->admin();
        $user = User::factory()->create(['status' => 'active']);

        $device = DeviceToken::create([
            'user_id' => $user->id,
            'device_id' => 'device-two',
            'platform' => 'android',
            'push_token' => 'token-two',
            'is_active' => true,
        ]);

        // Reached another device already.
        $notification = Notification::create([
            'recipient_user_id' => $user->id,
            'type' => 'system',
            'entity_type' => 'system',
            'title' => 'Already delivered',
            'body' => 'Body',
            'delivery_status' => 'sent',
            'push_sent_at' => now()->subHour(),
        ]);

        $failedLog = PushNotificationLog::create([
            'notification_id' => $notification->id,
            'user_id' => $user->id,
            'device_token_id' => $device->id,
            'provider' => 'fcm',
            'status' => 'failed',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.notifications.push_logs.retry', $failedLog))
            ->assertRedirect();

        // Regression: this used to reset delivery_status to 'pending' and claim
        // success without sending anything.
        $this->assertSame('sent', $notification->fresh()->delivery_status);
        $this->assertNotNull($notification->fresh()->push_sent_at);
        $this->assertSame(0, PushNotificationLog::where('status', 'queued')->count());
        $this->assertSame(2, PushNotificationLog::where('status', 'failed')->count());
    }

    public function test_retry_refuses_when_the_device_is_disabled(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['status' => 'active']);

        $device = DeviceToken::create([
            'user_id' => $user->id,
            'device_id' => 'device-off',
            'platform' => 'android',
            'push_token' => 'token-off',
            'is_active' => false,
        ]);

        $notification = Notification::create([
            'recipient_user_id' => $user->id,
            'type' => 'system',
            'entity_type' => 'system',
            'title' => 'Disabled device',
            'body' => 'Body',
            'delivery_status' => 'failed',
        ]);

        $log = PushNotificationLog::create([
            'notification_id' => $notification->id,
            'user_id' => $user->id,
            'device_token_id' => $device->id,
            'provider' => 'fcm',
            'status' => 'failed',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.notifications.push_logs.retry', $log))
            ->assertRedirect()
            ->assertSessionHas('status', fn ($message) => str_contains($message, 'device is disabled'));

        $this->assertSame(1, PushNotificationLog::count());
        $this->assertSame('failed', $notification->fresh()->delivery_status);
    }

    public function test_sender_uses_searchable_checkbox_pickers_with_readable_labels(): void
    {
        $admin = $this->admin();

        // Seed data reflects real rows where the display name *is* the email,
        // which the old native <select> rendered as "x@y.com - x@y.com".
        User::factory()->create([
            'status' => 'active',
            'username' => 'dupe_user',
            'full_name' => 'dupe@yopmail.com',
            'email' => 'dupe@yopmail.com',
        ]);

        $named = User::factory()->create([
            'status' => 'active',
            'username' => 'named_user',
            'full_name' => 'Real Person',
            'email' => 'real@example.com',
        ]);

        Profile::create(['user_id' => $named->id, 'display_name' => 'Real Person', 'city' => 'Lahore']);

        $response = $this->actingAs($admin)
            ->get(route('admin.notifications.notifications'))
            ->assertOk();

        // Checkboxes, not a native multi-select that needs Ctrl+click.
        $response->assertSee('name="user_ids[]"', false);
        $response->assertSee('name="cities[]"', false);
        $response->assertDontSee('<select name="user_ids[]"', false);
        $response->assertDontSee('Hold Ctrl/Cmd', false);

        // Readable labels: a name when there is one, the username when the
        // "name" is just the email again.
        $response->assertSee('Real Person');
        $response->assertSee('@dupe_user');
        $response->assertDontSee('dupe@yopmail.com - dupe@yopmail.com');

        // Cities come from real profile data.
        $response->assertSee('Lahore');
    }

    public function test_specific_audience_requires_user_ids(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->from(route('admin.notifications.notifications'))
            ->post(route('admin.notifications.announcements.send'), [
                'audience_type' => 'specific',
                'title' => 'Missing audience',
                'body' => 'Body',
            ])
            ->assertSessionHasErrors('user_ids');

        $this->assertSame(0, NotificationCampaign::count());
    }
}
