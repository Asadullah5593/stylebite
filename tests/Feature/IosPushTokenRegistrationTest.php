<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Why this exists.
 *
 * The AWS device_tokens table has never held a single iOS row, even though the
 * iOS build logs in successfully every day. This pins down which of the two
 * possible explanations is true — the backend quietly dropping a well-formed
 * iOS registration, or the app never sending one — and locks the behaviour of
 * every rejection path an iOS client can hit so a future change cannot make the
 * failure silent again.
 *
 * The load-bearing findings pinned here:
 *  - POST /api/devices/push-token works for iOS; nothing platform-specific
 *    rejects it.
 *  - A login that sends push_token with no device_id fails the WHOLE login
 *    with a 422 — it is not a silent drop.
 *  - A login that sends NO push_token succeeds and stores nothing, silently.
 *    That is the only path that produces the state seen in production.
 *  - A push_token longer than 512 characters fails the whole login with a
 *    422 — also loud, not silent.
 *
 * It also pins the byte-size fingerprints of the 422 bodies. Production has no
 * request-body logging, so the only way to tell which validation error a logged
 * 422 was is its response size. nginx records body_bytes_sent over a chunked
 * response, which is the JSON length plus 11 bytes of chunk framing for any body
 * between 16 and 255 bytes. Pinning the JSON lengths here is what makes the
 * access log readable — and what proved that the production 422s were ordinary
 * wrong-password rejections, not push-token failures.
 */
class IosPushTokenRegistrationTest extends TestCase
{
    use RefreshDatabase;

    /** A real-shaped iOS FCM registration token: 163 characters. */
    private const IOS_FCM_TOKEN = 'cE9vX2lvc19kZXZpY2U6APA91bF'
        .'HxKq3nZ8mVtRyWpLdJcGfBsNhTvUxYzAeQiOoMkPlSrDgFbNuVcXwZaEtYrUiOpAsDfGhJkLzXcVbNmQwErTyUiOpAsDfGhJkLzXcVbNmQwErTyUiOpAsDfGhJkLzXcVbNmQwErT';

    /**
     * @return array{0: User, 1: string}
     */
    private function signedInIosUser(string $deviceId = 'ios-1788433179207433-generated'): array
    {
        $user = User::factory()->create(['status' => 'active']);
        $token = Str::random(80);

        UserSession::create([
            'user_id' => $user->id,
            'session_token_hash' => hash('sha256', $token),
            'device_id' => $deviceId,
            'platform' => 'ios',
            'last_seen_at' => now(),
            'expires_at' => now()->addDay(),
        ]);

        return [$user, $token];
    }

    private function headers(string $token): array
    {
        return ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$token];
    }

    // ---------------------------------------------------------------------
    // POST /api/devices/push-token — the endpoint the app should be calling
    // ---------------------------------------------------------------------

    public function test_an_ios_device_can_register_a_push_token(): void
    {
        [$user, $token] = $this->signedInIosUser();

        $response = $this->postJson('/api/devices/push-token', [
            'device_id' => 'ios-1788433179207433-generated',
            'platform' => 'ios',
            'push_token' => self::IOS_FCM_TOKEN,
            'app_version' => '1.4.2',
        ], $this->headers($token));

        $response->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('device.platform', 'ios');

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'device_id' => 'ios-1788433179207433-generated',
            'platform' => 'ios',
            'push_token' => self::IOS_FCM_TOKEN,
            'app_version' => '1.4.2',
            'is_active' => true,
        ]);
    }

    public function test_a_real_length_ios_fcm_token_is_well_inside_the_column_limit(): void
    {
        // 163 characters is the normal shape. The 512 cap is not what is
        // blocking a well-formed iOS registration.
        $this->assertSame(163, strlen(self::IOS_FCM_TOKEN));
        $this->assertLessThan(512, strlen(self::IOS_FCM_TOKEN));
    }

    public function test_the_app_generated_device_id_shape_is_accepted(): void
    {
        // Production sessions show the client inventing an id like
        // "ios-1788433179207433-generated" instead of the vendor identifier.
        // Whatever the client does there, the backend has no opinion.
        [$user, $token] = $this->signedInIosUser('ios-1788433179207433-generated');

        $this->postJson('/api/devices/push-token', [
            'device_id' => 'ios-1788433179207433-generated',
            'platform' => 'ios',
            'push_token' => self::IOS_FCM_TOKEN,
        ], $this->headers($token))->assertOk();

        $this->assertDatabaseCount('device_tokens', 1);
    }

    public function test_a_rotated_ios_token_replaces_the_old_row_for_the_same_device(): void
    {
        [$user, $token] = $this->signedInIosUser();

        $this->postJson('/api/devices/push-token', [
            'device_id' => 'ios-device-a',
            'platform' => 'ios',
            'push_token' => 'ios-token-original',
        ], $this->headers($token))->assertOk();

        $this->postJson('/api/devices/push-token', [
            'device_id' => 'ios-device-a',
            'platform' => 'ios',
            'push_token' => 'ios-token-rotated',
        ], $this->headers($token))->assertOk();

        $this->assertDatabaseCount('device_tokens', 1);
        $this->assertSame('ios-token-rotated', DeviceToken::query()->sole()->push_token);
    }

    public function test_the_same_ios_token_moving_to_a_new_owner_does_not_violate_the_unique_index(): void
    {
        // (platform, push_token) is UNIQUE across all users. A handset handed to
        // somebody else must be re-pointed rather than duplicated, or the second
        // registration would blow up with a 409 duplicate-entry.
        [$first, $firstToken] = $this->signedInIosUser('ios-shared-handset');
        [$second, $secondToken] = $this->signedInIosUser('ios-shared-handset');

        $this->postJson('/api/devices/push-token', [
            'device_id' => 'ios-shared-handset',
            'platform' => 'ios',
            'push_token' => self::IOS_FCM_TOKEN,
        ], $this->headers($firstToken))->assertOk();

        $this->postJson('/api/devices/push-token', [
            'device_id' => 'ios-shared-handset',
            'platform' => 'ios',
            'push_token' => self::IOS_FCM_TOKEN,
        ], $this->headers($secondToken))->assertOk();

        $this->assertDatabaseCount('device_tokens', 1);
        $this->assertSame($second->id, (int) DeviceToken::query()->sole()->user_id);
    }

    public function test_registration_without_a_bearer_token_is_rejected(): void
    {
        $this->postJson('/api/devices/push-token', [
            'device_id' => 'ios-device-a',
            'platform' => 'ios',
            'push_token' => self::IOS_FCM_TOKEN,
        ], ['Accept' => 'application/json'])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Authorization token is required.');
    }

    /**
     * Every field is required here, unlike on login where they are optional.
     * An app that omits any one of them gets a 422 and stores nothing.
     *
     */
    #[DataProvider('incompleteRegistrationPayloads')]
    public function test_an_incomplete_registration_is_rejected_with_422(array $payload, string $missingField): void
    {
        [$user, $token] = $this->signedInIosUser();

        $this->postJson('/api/devices/push-token', $payload, $this->headers($token))
            ->assertStatus(422)
            ->assertJsonValidationErrors([$missingField]);

        $this->assertDatabaseCount('device_tokens', 0);
    }

    public static function incompleteRegistrationPayloads(): array
    {
        return [
            'no device_id' => [
                ['platform' => 'ios', 'push_token' => self::IOS_FCM_TOKEN],
                'device_id',
            ],
            'no platform' => [
                ['device_id' => 'ios-device-a', 'push_token' => self::IOS_FCM_TOKEN],
                'platform',
            ],
            'no push_token' => [
                ['device_id' => 'ios-device-a', 'platform' => 'ios'],
                'push_token',
            ],
        ];
    }

    /**
     * The route validates platform against ios/android/web, but the login flow's
     * detectPlatform() also accepts 'desktop'. A client that sends 'desktop'
     * here is refused rather than written as something else.
     */
    public function test_the_desktop_platform_login_accepts_is_refused_by_this_endpoint(): void
    {
        [$user, $token] = $this->signedInIosUser();

        $this->postJson('/api/devices/push-token', [
            'device_id' => 'ios-device-a',
            'platform' => 'desktop',
            'push_token' => self::IOS_FCM_TOKEN,
        ], $this->headers($token))
            ->assertStatus(422)
            ->assertJsonPath('errors.platform.0', 'Push tokens are only supported for ios, android, or web.');
    }

    public function test_a_push_token_over_512_characters_is_refused(): void
    {
        [$user, $token] = $this->signedInIosUser();

        $this->postJson('/api/devices/push-token', [
            'device_id' => 'ios-device-a',
            'platform' => 'ios',
            'push_token' => str_repeat('a', 513),
        ], $this->headers($token))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['push_token']);

        $this->assertDatabaseCount('device_tokens', 0);
    }

    // ---------------------------------------------------------------------
    // The login path — is a dropped token loud or silent?
    // ---------------------------------------------------------------------

    private function loginUser(): User
    {
        return User::factory()->create([
            'status' => 'active',
            'email' => 'ios-user@example.com',
            'password_hash' => bcrypt('Sup3rSecret!'),
            'email_verified_at' => now(),
        ]);
    }

    public function test_a_login_carrying_a_push_token_and_a_device_id_stores_it(): void
    {
        config(['auth.login_two_factor' => false]);
        $user = $this->loginUser();

        $this->postJson('/api/auth/login', [
            'email' => 'ios-user@example.com',
            'password' => 'Sup3rSecret!',
            'device_id' => 'ios-1788433179207433-generated',
            'platform' => 'ios',
            'push_token' => self::IOS_FCM_TOKEN,
        ])->assertOk()->assertJsonPath('status_code', 1);

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'platform' => 'ios',
            'push_token' => self::IOS_FCM_TOKEN,
        ]);
    }

    /**
     * The answer to "silent or loud?" for a missing device_id: LOUD. The
     * required_with rule fires during request validation, before any session is
     * created, so the user cannot log in at all. This cannot be the production
     * cause — those logins are returning 200.
     */
    public function test_a_login_with_a_push_token_but_no_device_id_fails_the_whole_login(): void
    {
        config(['auth.login_two_factor' => false]);
        $this->loginUser();

        $response = $this->postJson('/api/auth/login', [
            'email' => 'ios-user@example.com',
            'password' => 'Sup3rSecret!',
            'platform' => 'ios',
            'push_token' => self::IOS_FCM_TOKEN,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Device ID is required when a push token is provided.')
            ->assertJsonMissingPath('access_token');

        // Not a partial success: no session was issued either.
        $this->assertDatabaseCount('user_sessions', 0);
        $this->assertDatabaseCount('device_tokens', 0);
    }

    /**
     * The answer for a missing push_token: SILENT. Login succeeds, a session is
     * issued, and nothing at all is written to device_tokens. This is the only
     * behaviour consistent with production — iOS logins returning 200 while the
     * table stays empty.
     */
    public function test_a_login_with_no_push_token_succeeds_and_silently_stores_nothing(): void
    {
        config(['auth.login_two_factor' => false]);
        $this->loginUser();

        $this->postJson('/api/auth/login', [
            'email' => 'ios-user@example.com',
            'password' => 'Sup3rSecret!',
            'device_id' => 'ios-1788433179207433-generated',
            'platform' => 'ios',
        ])->assertOk()->assertJsonPath('status_code', 1);

        $this->assertDatabaseCount('user_sessions', 1);
        $this->assertDatabaseCount('device_tokens', 0);
    }

    /** An empty-string push_token is treated as absent, not as an error. */
    public function test_an_empty_push_token_is_silently_ignored(): void
    {
        config(['auth.login_two_factor' => false]);
        $this->loginUser();

        $this->postJson('/api/auth/login', [
            'email' => 'ios-user@example.com',
            'password' => 'Sup3rSecret!',
            'device_id' => 'ios-device-a',
            'platform' => 'ios',
            'push_token' => '',
        ])->assertOk();

        $this->assertDatabaseCount('device_tokens', 0);
    }

    public function test_an_oversized_push_token_fails_the_whole_login(): void
    {
        config(['auth.login_two_factor' => false]);
        $this->loginUser();

        $response = $this->postJson('/api/auth/login', [
            'email' => 'ios-user@example.com',
            'password' => 'Sup3rSecret!',
            'device_id' => 'ios-1788433179207433-generated',
            'platform' => 'ios',
            'push_token' => str_repeat('a', 513),
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Push token is too long. Please try again.');

        // Loud, not silent: no session either.
        $this->assertDatabaseCount('user_sessions', 0);
        $this->assertDatabaseCount('device_tokens', 0);
    }

    /**
     * The forensic key to the production access log.
     *
     * Every 422 logged against POST /api/auth/login measured 143 bytes. With the
     * 11 bytes of chunked-transfer framing removed that is a 132-byte JSON body,
     * which is the wrong-password rejection — NOT the 141-byte oversized-push-token
     * rejection. This is the assertion that rules the backend out as the cause.
     */
    public function test_the_422_body_sizes_that_identify_a_logged_rejection(): void
    {
        config(['auth.login_two_factor' => false]);
        $this->loginUser();

        $wrongPassword = $this->postJson('/api/auth/login', [
            'email' => 'ios-user@example.com',
            'password' => 'not-the-password',
        ]);
        $wrongPassword->assertStatus(422);
        $this->assertSame(132, strlen($wrongPassword->getContent()), 'wrong-password 422 body');

        $oversized = $this->postJson('/api/auth/login', [
            'email' => 'ios-user@example.com',
            'password' => 'Sup3rSecret!',
            'device_id' => 'ios-device-a',
            'push_token' => str_repeat('a', 513),
        ]);
        $oversized->assertStatus(422);
        $this->assertSame(141, strlen($oversized->getContent()), 'oversized push_token 422 body');

        $noDeviceId = $this->postJson('/api/auth/login', [
            'email' => 'ios-user@example.com',
            'password' => 'Sup3rSecret!',
            'push_token' => 'a-token',
        ]);
        $noDeviceId->assertStatus(422);
        $this->assertSame(162, strlen($noDeviceId->getContent()), 'missing device_id 422 body');

        // The three are distinguishable from the access log alone, which is the
        // whole point.
        $this->assertCount(3, array_unique([132, 141, 162]));
    }

    /**
     * detectPlatform() silently downgrades anything it does not recognise to
     * 'web'. An iOS client that forgets the platform field therefore gets its
     * token filed under the wrong platform — and because (platform, push_token)
     * is the unique key, the same token can then exist twice.
     */
    public function test_a_login_without_a_platform_files_the_ios_token_as_web(): void
    {
        config(['auth.login_two_factor' => false]);
        $user = $this->loginUser();

        $this->postJson('/api/auth/login', [
            'email' => 'ios-user@example.com',
            'password' => 'Sup3rSecret!',
            'device_id' => 'ios-1788433179207433-generated',
            'push_token' => self::IOS_FCM_TOKEN,
        ])->assertOk();

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'platform' => 'web',
        ]);
        $this->assertDatabaseMissing('device_tokens', [
            'user_id' => $user->id,
            'platform' => 'ios',
        ]);
    }

    /**
     * A latent inconsistency found while tracing this, unrelated to iOS but on
     * the same code path: login validates platform against ios/android/web/desktop,
     * while DeviceTokenRegistrar accepts only ios/android/web. A client that sends
     * platform=desktop together with a push_token therefore passes request
     * validation, gets a UserSession row written, and only then hits the
     * registrar's throw — so the login answers 422 while leaving an orphaned
     * session behind. createSession() is not wrapped in a transaction, so the
     * session row survives the failed request.
     */
    public function test_a_desktop_login_with_a_push_token_422s_after_creating_an_orphan_session(): void
    {
        config(['auth.login_two_factor' => false]);
        $this->loginUser();

        $this->postJson('/api/auth/login', [
            'email' => 'ios-user@example.com',
            'password' => 'Sup3rSecret!',
            'device_id' => 'desktop-device-a',
            'platform' => 'desktop',
            'push_token' => self::IOS_FCM_TOKEN,
        ])->assertStatus(422);

        $this->assertDatabaseCount('device_tokens', 0);
        // The orphan. This is the bug: a rejected login still consumed a session row.
        $this->assertDatabaseCount('user_sessions', 1);
    }

    /**
     * With mandatory 2FA switched on, /api/auth/login never reaches
     * createSession — so a push_token sent on step one is validated, accepted,
     * and thrown away. It only survives if the app resends it on
     * /api/auth/login/verify-otp. Production currently runs with
     * LOGIN_TWO_FACTOR=false, but this is a live trap for the day it flips.
     */
    public function test_with_two_factor_on_a_push_token_sent_to_login_is_discarded(): void
    {
        config(['auth.login_two_factor' => true]);
        $this->loginUser();

        $this->postJson('/api/auth/login', [
            'email' => 'ios-user@example.com',
            'password' => 'Sup3rSecret!',
            'device_id' => 'ios-1788433179207433-generated',
            'platform' => 'ios',
            'push_token' => self::IOS_FCM_TOKEN,
        ])->assertOk()->assertJsonPath('requires_two_factor', true);

        $this->assertDatabaseCount('device_tokens', 0);
    }

    /**
     * Apple sign-in is the path the production iOS build actually uses most —
     * it goes straight to createSession, so a token sent here is stored.
     */
    public function test_apple_login_stores_an_ios_push_token(): void
    {
        $this->postJson('/api/auth/apple-login', [
            'provider_user_id' => '001234.abcdef.0000',
            'email' => 'apple-ios-user@example.com',
            'name' => 'Apple Tester',
            'device_id' => 'ios-1788433179207433-generated',
            'platform' => 'ios',
            'push_token' => self::IOS_FCM_TOKEN,
        ])->assertOk()->assertJsonPath('status_code', 1);

        $this->assertDatabaseHas('device_tokens', [
            'platform' => 'ios',
            'push_token' => self::IOS_FCM_TOKEN,
        ]);
    }

    /** And the same silent drop applies to Apple sign-in with no token. */
    public function test_apple_login_without_a_push_token_stores_nothing(): void
    {
        $this->postJson('/api/auth/apple-login', [
            'provider_user_id' => '001234.abcdef.0001',
            'email' => 'apple-ios-user2@example.com',
            'name' => 'Apple Tester',
            'device_id' => 'ios-1788433179207433-generated',
            'platform' => 'ios',
        ])->assertOk();

        $this->assertDatabaseCount('device_tokens', 0);
    }
}
