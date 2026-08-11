<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\User;
use App\Mail\GlobalAppMail;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Logout: kill this session's token, and stop push going to this handset.
 *
 * The property worth defending is that logout is **per device**. A user signed in
 * on a phone and a tablet who logs out of the phone must keep the tablet's
 * session and its notifications — so the tests below always set up a second
 * device and assert it survived. The other half is that a push token can only be
 * removed by the account that owns it; matching on a client-supplied token
 * without scoping to the user would let anyone unregister someone else's phone.
 */
class LogoutApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: UserSession, 1: string}
     */
    private function issueSession(User $user, ?string $deviceId = 'device-phone'): array
    {
        $token = Str::random(80);

        $session = UserSession::create([
            'user_id' => $user->id,
            'session_token_hash' => hash('sha256', $token),
            'device_id' => $deviceId,
            'platform' => 'android',
            'last_seen_at' => now(),
            'expires_at' => now()->addDay(),
        ]);

        return [$session, $token];
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

    private function headers(string $token): array
    {
        return ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$token];
    }

    public function test_logout_revokes_the_session_and_deletes_that_devices_push_token(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        [$session, $token] = $this->issueSession($user);
        $this->deviceToken($user, 'device-phone', 'fcm-phone');

        $this->withHeaders($this->headers($token))
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('push_token_removed', true);

        $this->assertNotNull($session->fresh()->revoked_at);
        $this->assertDatabaseMissing('device_tokens', ['push_token' => 'fcm-phone']);
    }

    public function test_the_token_stops_working_immediately_after_logout(): void
    {
        // Without revocation the session would stay valid for the rest of its 24
        // hours, so "logged out" would still hold a working token.
        $user = User::factory()->create(['status' => 'active']);
        [, $token] = $this->issueSession($user);

        $this->withHeaders($this->headers($token))->postJson('/api/auth/logout')->assertOk();

        $this->withHeaders($this->headers($token))->getJson('/api/profile/me')->assertUnauthorized();
        $this->withHeaders($this->headers($token))->postJson('/api/auth/logout')->assertUnauthorized();
    }

    public function test_logging_out_one_device_leaves_the_other_device_signed_in_and_pushable(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        [, $phoneToken] = $this->issueSession($user, 'device-phone');
        [$tabletSession, $tabletToken] = $this->issueSession($user, 'device-tablet');

        $this->deviceToken($user, 'device-phone', 'fcm-phone');
        $this->deviceToken($user, 'device-tablet', 'fcm-tablet', 'ios');

        $this->withHeaders($this->headers($phoneToken))->postJson('/api/auth/logout')->assertOk();

        $this->assertDatabaseMissing('device_tokens', ['push_token' => 'fcm-phone']);
        $this->assertDatabaseHas('device_tokens', ['push_token' => 'fcm-tablet']);

        $this->assertNull($tabletSession->fresh()->revoked_at);
        $this->withHeaders($this->headers($tabletToken))->getJson('/api/profile/me')->assertOk();
    }

    public function test_the_device_is_identified_from_the_session_not_the_request_body(): void
    {
        // A client that posts the wrong push_token must not be able to unregister
        // a different device of its own by accident.
        $user = User::factory()->create(['status' => 'active']);
        [, $token] = $this->issueSession($user, 'device-phone');

        $this->deviceToken($user, 'device-phone', 'fcm-phone');
        $this->deviceToken($user, 'device-tablet', 'fcm-tablet', 'ios');

        $this->withHeaders($this->headers($token))
            ->postJson('/api/auth/logout', ['push_token' => 'fcm-tablet', 'device_id' => 'device-tablet'])
            ->assertOk();

        $this->assertDatabaseMissing('device_tokens', ['push_token' => 'fcm-phone']);
        $this->assertDatabaseHas('device_tokens', ['push_token' => 'fcm-tablet']);
    }

    public function test_a_user_cannot_delete_another_users_push_token(): void
    {
        $attacker = User::factory()->create(['status' => 'active']);
        $victim = User::factory()->create(['status' => 'active']);

        // The attacker's session has no device_id, so the request body is the
        // fallback — and that is exactly where scoping to the owner matters.
        [, $attackerToken] = $this->issueSession($attacker, null);
        $this->deviceToken($victim, 'victim-device', 'fcm-victim');

        $this->withHeaders($this->headers($attackerToken))
            ->postJson('/api/auth/logout', ['push_token' => 'fcm-victim'])
            ->assertOk()
            ->assertJsonPath('push_token_removed', false);

        $this->assertDatabaseHas('device_tokens', ['push_token' => 'fcm-victim', 'user_id' => $victim->id]);
    }

    public function test_a_session_without_a_device_id_falls_back_to_the_posted_push_token(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        [$session, $token] = $this->issueSession($user, null);
        $this->deviceToken($user, 'device-phone', 'fcm-phone');

        $this->withHeaders($this->headers($token))
            ->postJson('/api/auth/logout', ['push_token' => 'fcm-phone'])
            ->assertOk()
            ->assertJsonPath('push_token_removed', true);

        $this->assertDatabaseMissing('device_tokens', ['push_token' => 'fcm-phone']);
        $this->assertNotNull($session->fresh()->revoked_at);
    }

    public function test_logout_with_nothing_identifying_the_device_still_logs_out_but_removes_no_token(): void
    {
        // Guessing which token to delete could silently kill push on the user's
        // other handset, so it deletes none — but the session must still die.
        $user = User::factory()->create(['status' => 'active']);
        [$session, $token] = $this->issueSession($user, null);
        $this->deviceToken($user, 'device-phone', 'fcm-phone');
        $this->deviceToken($user, 'device-tablet', 'fcm-tablet', 'ios');

        $this->withHeaders($this->headers($token))
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('push_token_removed', false);

        $this->assertNotNull($session->fresh()->revoked_at);
        $this->assertDatabaseCount('device_tokens', 2);
    }

    public function test_logout_without_a_push_token_registered_is_not_an_error(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        [$session, $token] = $this->issueSession($user);

        $this->withHeaders($this->headers($token))
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('push_token_removed', false);

        $this->assertNotNull($session->fresh()->revoked_at);
    }

    public function test_logout_requires_authentication(): void
    {
        $this->postJson('/api/auth/logout')->assertUnauthorized();
    }

    public function test_a_logged_out_device_receives_no_further_push(): void
    {
        // The whole point of the endpoint: after logout the recipient has no
        // active push target left, so a notification is recorded but skipped
        // rather than delivered to a handset nobody is signed in on.
        $user = User::factory()->create(['status' => 'active']);
        [, $token] = $this->issueSession($user);
        $this->deviceToken($user, 'device-phone', 'fcm-phone');

        $this->withHeaders($this->headers($token))->postJson('/api/auth/logout')->assertOk();

        $notification = stylebite_notify_user(
            $user->id, null, 'system', 'system', null, 'Hello', 'Body'
        );

        $this->assertSame('skipped', $notification->delivery_status);
        $this->assertSame(0, DeviceToken::where('user_id', $user->id)->count());
    }

    public function test_logging_back_in_re_registers_the_push_token(): void
    {
        // Deleting on logout must not lock the device out of notifications for
        // good, so this drives the real login + OTP flow rather than asserting
        // on the delete alone.
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'logout-relogin@example.com',
            'status' => 'active',
            'email_verified_at' => now(),
            'password_hash' => Hash::make('Str0ng-Pass!1'),
        ]);

        [, $token] = $this->issueSession($user);
        $this->deviceToken($user, 'device-phone', 'fcm-phone');

        $this->withHeaders($this->headers($token))->postJson('/api/auth/logout')->assertOk();
        $this->assertDatabaseCount('device_tokens', 0);

        $this->postJson('/api/auth/login', [
            'email' => 'logout-relogin@example.com',
            'password' => 'Str0ng-Pass!1',
        ])->assertOk();

        $code = null;

        Mail::assertSent(GlobalAppMail::class, function (GlobalAppMail $mail) use (&$code): bool {
            if ($mail->subjectLine !== 'Your Stylebite login code') {
                return false;
            }

            $code = $mail->highlightCode;

            return true;
        });

        $this->postJson('/api/auth/login/verify-otp', [
            'email' => 'logout-relogin@example.com',
            'code' => $code,
            'device_id' => 'device-phone',
            'platform' => 'android',
            'push_token' => 'fcm-phone-fresh',
        ])->assertOk();

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'device_id' => 'device-phone',
            'push_token' => 'fcm-phone-fresh',
            'is_active' => true,
        ]);
    }
}
