<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Push tokens rotate, and dead ones have to be cleared out.
 *
 * This pins the two halves of a real production failure: dashboard campaigns were
 * coming back "The registration token is not a valid FCM registration token" for
 * every recipient while chat pushes worked. Tokens were only ever written during
 * login, so a token FCM had since rotated stayed in the table forever, and nothing
 * removed it when FCM said it was dead.
 *
 * The dangerous half of the fix is the deleting: FCM also answers INVALID_ARGUMENT
 * for a malformed image URL, and treating that as a dead token would unregister a
 * working handset because an admin typed a bad URL. Those cases are pinned too.
 */
class DeviceTokenLifecycleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: string}
     */
    private function appUser(): array
    {
        $user = User::factory()->create(['status' => 'active']);
        $token = Str::random(80);

        UserSession::create([
            'user_id' => $user->id,
            'session_token_hash' => hash('sha256', $token),
            'device_id' => 'device-phone',
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

    private function deviceToken(User $user, string $deviceId, string $pushToken, string $platform = 'android'): DeviceToken
    {
        return DeviceToken::create([
            'user_id' => $user->id,
            'device_id' => $deviceId,
            'platform' => $platform,
            'push_token' => $pushToken,
            'is_active' => true,
            'last_used_at' => now(),
        ]);
    }

    /**
     * Point Firebase at a throwaway service account with a real RSA key so the JWT
     * signs, then fake the OAuth and FCM endpoints separately. Faking '*' instead
     * would swallow the token request, and the send would throw before FCM's
     * verdict on the token was ever seen — making these tests pass vacuously.
     */
    private function fakeFirebase(mixed $fcmResponse): void
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
            'fcm.googleapis.com/*' => $fcmResponse,
        ]);
    }

    private function fcmError(string $status, ?string $field = null, ?string $errorCode = null): array
    {
        $details = [['@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError', 'errorCode' => $errorCode ?? $status]];

        if ($field !== null) {
            $details[] = [
                '@type' => 'type.googleapis.com/google.rpc.BadRequest',
                'fieldViolations' => [['field' => $field, 'description' => 'Bad field.']],
            ];
        }

        return ['error' => ['code' => 400, 'message' => 'Rejected.', 'status' => $status, 'details' => $details]];
    }

    // ---------------------------------------------------------------- refresh

    public function test_a_rotated_token_can_be_refreshed_without_logging_in_again(): void
    {
        [$user, $token] = $this->appUser();
        $original = $this->deviceToken($user, 'device-phone', 'fcm-old');

        $this->withHeaders($this->headers($token))
            ->postJson('/api/devices/push-token', [
                'device_id' => 'device-phone',
                'platform' => 'android',
                'push_token' => 'fcm-new',
                'app_version' => '1.5.0',
            ])
            ->assertOk()
            ->assertJsonPath('status_code', 1);

        // Same row, moved onto the new token — not a second row for one handset.
        $this->assertDatabaseCount('device_tokens', 1);
        $this->assertSame('fcm-new', $original->fresh()->push_token);
        $this->assertSame('1.5.0', $original->fresh()->app_version);
    }

    public function test_refreshing_does_not_disturb_the_users_other_device(): void
    {
        [$user, $token] = $this->appUser();
        $this->deviceToken($user, 'device-phone', 'fcm-phone');
        $this->deviceToken($user, 'device-tablet', 'fcm-tablet', 'ios');

        $this->withHeaders($this->headers($token))
            ->postJson('/api/devices/push-token', [
                'device_id' => 'device-phone',
                'platform' => 'android',
                'push_token' => 'fcm-phone-v2',
            ])
            ->assertOk();

        $this->assertDatabaseHas('device_tokens', ['push_token' => 'fcm-phone-v2']);
        $this->assertDatabaseHas('device_tokens', ['push_token' => 'fcm-tablet']);
        $this->assertDatabaseCount('device_tokens', 2);
    }

    public function test_a_handset_that_changes_owner_is_repointed_not_duplicated(): void
    {
        // The (platform, push_token) unique constraint means the old owner's row
        // has to move rather than a second row being inserted.
        $previousOwner = User::factory()->create(['status' => 'active']);
        $this->deviceToken($previousOwner, 'shared-handset', 'fcm-shared');

        [$newOwner, $token] = $this->appUser();

        $this->withHeaders($this->headers($token))
            ->postJson('/api/devices/push-token', [
                'device_id' => 'shared-handset',
                'platform' => 'android',
                'push_token' => 'fcm-shared',
            ])
            ->assertOk();

        $this->assertDatabaseCount('device_tokens', 1);
        $this->assertDatabaseHas('device_tokens', ['push_token' => 'fcm-shared', 'user_id' => $newOwner->id]);
    }

    public function test_refresh_requires_authentication_and_validates_platform(): void
    {
        $this->postJson('/api/devices/push-token', [
            'device_id' => 'd', 'platform' => 'android', 'push_token' => 't',
        ])->assertUnauthorized();

        [, $token] = $this->appUser();

        $this->withHeaders($this->headers($token))
            ->postJson('/api/devices/push-token', [
                'device_id' => 'device-phone', 'platform' => 'symbian', 'push_token' => 'fcm-x',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('platform');

        $this->withHeaders($this->headers($token))
            ->postJson('/api/devices/push-token', ['platform' => 'android'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['device_id', 'push_token']);
    }

    public function test_a_user_can_unregister_a_device_without_ending_the_session(): void
    {
        [$user, $token] = $this->appUser();
        $this->deviceToken($user, 'device-phone', 'fcm-phone');

        $this->withHeaders($this->headers($token))
            ->deleteJson('/api/devices/push-token', ['device_id' => 'device-phone'])
            ->assertOk()
            ->assertJsonPath('push_token_removed', true);

        $this->assertDatabaseCount('device_tokens', 0);

        // Still signed in — this is "turn notifications off", not "log out".
        $this->withHeaders($this->headers($token))->getJson('/api/profile/me')->assertOk();
    }

    public function test_a_user_cannot_unregister_another_users_device(): void
    {
        [, $token] = $this->appUser();
        $victim = User::factory()->create(['status' => 'active']);
        $this->deviceToken($victim, 'victim-device', 'fcm-victim');

        $this->withHeaders($this->headers($token))
            ->deleteJson('/api/devices/push-token', ['device_id' => 'victim-device'])
            ->assertOk()
            ->assertJsonPath('push_token_removed', false);

        $this->assertDatabaseHas('device_tokens', ['push_token' => 'fcm-victim']);
    }

    // ------------------------------------------------------- dead-token pruning

    public function test_a_token_fcm_rejects_as_invalid_is_deleted(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $this->deviceToken($user, 'device-phone', 'fcm-dead');

        $this->fakeFirebase(Http::response($this->fcmError('INVALID_ARGUMENT', 'message.token'), 400));

        $notification = stylebite_notify_user($user->id, null, 'system', 'system', null, 'Hi', 'There');

        $this->assertSame('failed', $notification->delivery_status);
        $this->assertDatabaseCount('device_tokens', 0);

        // The push log outlives the token, so the evidence is still there.
        $this->assertDatabaseHas('push_notification_logs', ['user_id' => $user->id, 'status' => 'failed']);
    }

    public function test_an_unregistered_token_is_deleted(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $this->deviceToken($user, 'device-phone', 'fcm-uninstalled');

        $this->fakeFirebase(Http::response($this->fcmError('UNREGISTERED', null, 'UNREGISTERED'), 404));

        stylebite_notify_user($user->id, null, 'system', 'system', null, 'Hi', 'There');

        $this->assertDatabaseCount('device_tokens', 0);
    }

    public function test_a_bad_image_url_does_not_cost_the_user_their_push_token(): void
    {
        // FCM answers INVALID_ARGUMENT for a malformed image too. Deleting the
        // token here would unregister a working handset because an admin typed a
        // bad URL into the campaign form.
        $user = User::factory()->create(['status' => 'active']);
        $this->deviceToken($user, 'device-phone', 'fcm-good');

        $this->fakeFirebase(Http::response($this->fcmError('INVALID_ARGUMENT', 'message.notification.image'), 400));

        $notification = stylebite_notify_user($user->id, null, 'system', 'system', null, 'Hi', 'There');

        $this->assertSame('failed', $notification->delivery_status);
        $this->assertDatabaseHas('device_tokens', ['push_token' => 'fcm-good']);
    }

    public function test_a_transient_server_error_does_not_delete_the_token(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $this->deviceToken($user, 'device-phone', 'fcm-good');

        $this->fakeFirebase(Http::response($this->fcmError('UNAVAILABLE', null, 'UNAVAILABLE'), 503));

        stylebite_notify_user($user->id, null, 'system', 'system', null, 'Hi', 'There');

        $this->assertDatabaseHas('device_tokens', ['push_token' => 'fcm-good']);
    }

    public function test_only_the_dead_token_is_removed_when_a_user_has_several_devices(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $this->deviceToken($user, 'device-dead', 'fcm-dead');
        $this->deviceToken($user, 'device-live', 'fcm-live', 'ios');

        // First device rejected, second accepted — order matches the token order.
        $this->fakeFirebase(Http::sequence()
            ->push($this->fcmError('INVALID_ARGUMENT', 'message.token'), 400)
            ->push(['name' => 'projects/x/messages/1'], 200));

        $notification = stylebite_notify_user($user->id, null, 'system', 'system', null, 'Hi', 'There');

        // One device did receive it, so the notification counts as sent.
        $this->assertSame('sent', $notification->delivery_status);
        $this->assertDatabaseMissing('device_tokens', ['push_token' => 'fcm-dead']);
        $this->assertDatabaseHas('device_tokens', ['push_token' => 'fcm-live']);
    }

    public function test_the_dead_token_check_is_conservative_about_unparseable_responses(): void
    {
        $this->assertFalse(stylebite_push_token_is_dead(null));
        $this->assertFalse(stylebite_push_token_is_dead(''));
        $this->assertFalse(stylebite_push_token_is_dead('cURL error 28: connection timed out'));
        $this->assertFalse(stylebite_push_token_is_dead('{"name":"projects/x/messages/1"}'));
        $this->assertFalse(stylebite_push_token_is_dead(json_encode($this->fcmError('INTERNAL'))));
        $this->assertFalse(stylebite_push_token_is_dead(json_encode($this->fcmError('INVALID_ARGUMENT'))));

        $this->assertTrue(stylebite_push_token_is_dead(json_encode($this->fcmError('INVALID_ARGUMENT', 'message.token'))));
        $this->assertTrue(stylebite_push_token_is_dead(json_encode($this->fcmError('UNREGISTERED', null, 'UNREGISTERED'))));
    }

    public function test_a_refreshed_token_delivers_where_the_stale_one_failed(): void
    {
        // End to end: the production symptom, then the fix.
        [$user, $token] = $this->appUser();
        $this->deviceToken($user, 'device-phone', 'fcm-stale');

        // One harness for both sends: Http::fake merges stubs rather than
        // replacing them, so a second fake would never be reached.
        $this->fakeFirebase(Http::sequence()
            ->push($this->fcmError('INVALID_ARGUMENT', 'message.token'), 400)
            ->push(['name' => 'projects/x/messages/2'], 200));

        $this->assertSame(
            'failed',
            stylebite_notify_user($user->id, null, 'system', 'system', null, 'Before', 'Body')->delivery_status
        );
        $this->assertDatabaseCount('device_tokens', 0);

        $this->withHeaders($this->headers($token))
            ->postJson('/api/devices/push-token', [
                'device_id' => 'device-phone',
                'platform' => 'android',
                'push_token' => 'fcm-fresh',
            ])
            ->assertOk();

        $this->assertSame(
            'sent',
            stylebite_notify_user($user->id, null, 'system', 'system', null, 'After', 'Body')->delivery_status
        );

        $this->assertSame(
            2,
            Notification::where('recipient_user_id', $user->id)->count()
        );
    }
}
