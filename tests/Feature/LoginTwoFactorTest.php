<?php

namespace Tests\Feature;

use App\Mail\GlobalAppMail;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LoginTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private function activeUser(string $email = 'user@example.com'): User
    {
        return User::factory()->create([
            'email' => $email,
            'password_hash' => bcrypt('password123'),
            'status' => 'active',
        ]);
    }

    private function loginAndGrabCode(string $email = 'user@example.com'): string
    {
        $this->postJson('/api/auth/login', [
            'email' => $email,
            'password' => 'password123',
        ])->assertOk()->assertJsonPath('requires_two_factor', true);

        $code = null;

        Mail::assertSent(GlobalAppMail::class, function (GlobalAppMail $mail) use (&$code): bool {
            if ($mail->subjectLine !== 'Your Stylebite login code') {
                return false;
            }

            $code = $mail->highlightCode;

            return true;
        });

        $this->assertNotNull($code);

        return $code;
    }

    public function test_login_returns_two_factor_challenge_instead_of_token(): void
    {
        Mail::fake();
        $user = $this->activeUser();

        $response = $this->postJson('/api/auth/login', [
            'email' => 'user@example.com',
            'password' => 'password123',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('requires_two_factor', true)
            ->assertJsonPath('email', 'user@example.com')
            ->assertJsonMissingPath('access_token');

        $this->assertDatabaseHas('email_verification_tokens', [
            'user_id' => $user->id,
            'purpose' => 'login',
        ]);

        $this->assertDatabaseMissing('user_sessions', ['user_id' => $user->id]);

        Mail::assertSent(GlobalAppMail::class, fn (GlobalAppMail $mail) => $mail->subjectLine === 'Your Stylebite login code');
    }

    public function test_login_returns_a_token_directly_when_two_factor_is_switched_off(): void
    {
        // Rollout switch for app builds that predate the OTP screen: with 2FA
        // off, login must behave exactly as it did before — one step, token in
        // the response, no email sent.
        config(['auth.login_two_factor' => false]);
        Mail::fake();

        $user = $this->activeUser();

        $this->postJson('/api/auth/login', [
            'email' => 'user@example.com',
            'password' => 'password123',
            'device_id' => 'legacy-device',
            'platform' => 'android',
        ])
            ->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonStructure(['access_token', 'bearer_token', 'user'])
            ->assertJsonMissingPath('requires_two_factor');

        $this->assertDatabaseHas('user_sessions', [
            'user_id' => $user->id,
            'device_id' => 'legacy-device',
        ]);

        Mail::assertNothingSent();
    }

    public function test_banned_account_is_still_refused_when_two_factor_is_switched_off(): void
    {
        config(['auth.login_two_factor' => false]);

        User::factory()->create([
            'email' => 'banned2@example.com',
            'password_hash' => bcrypt('password123'),
            'status' => 'banned',
            'status_reason' => 'Fraud',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'banned2@example.com',
            'password' => 'password123',
        ])
            ->assertStatus(403)
            ->assertJsonPath('code', 'account_banned');

        $this->assertDatabaseCount('user_sessions', 0);
    }

    public function test_verify_login_otp_issues_token_with_24_hour_session(): void
    {
        Mail::fake();
        $user = $this->activeUser();
        $code = $this->loginAndGrabCode();

        $response = $this->postJson('/api/auth/login/verify-otp', [
            'email' => 'user@example.com',
            'code' => $code,
            'device_id' => 'device-2fa-1',
            'platform' => 'android',
            'push_token' => 'push-token-2fa-1',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonStructure(['access_token', 'bearer_token', 'user']);

        $session = UserSession::query()->where('user_id', $user->id)->latest('id')->first();
        $this->assertNotNull($session);
        $this->assertTrue(
            $session->expires_at->between(now()->addHours(23), now()->addHours(24)->addMinutes(1)),
            'Session should expire 24 hours after login, got '.$session->expires_at
        );

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'device_id' => 'device-2fa-1',
            'push_token' => 'push-token-2fa-1',
        ]);

        // The token works against the API.
        $token = $response->json('access_token');
        $this->getJson('/api/profile/me', ['Authorization' => 'Bearer '.$token])->assertOk();
    }

    public function test_login_code_is_single_use(): void
    {
        Mail::fake();
        $this->activeUser();
        $code = $this->loginAndGrabCode();

        $this->postJson('/api/auth/login/verify-otp', [
            'email' => 'user@example.com',
            'code' => $code,
        ])->assertOk();

        $this->postJson('/api/auth/login/verify-otp', [
            'email' => 'user@example.com',
            'code' => $code,
        ])->assertStatus(422);
    }

    public function test_wrong_code_locks_after_five_attempts_even_for_correct_code(): void
    {
        Mail::fake();
        $this->activeUser();
        $code = $this->loginAndGrabCode();

        $wrong = $code === '000000' ? '111111' : '000000';

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login/verify-otp', [
                'email' => 'user@example.com',
                'code' => $wrong,
            ])->assertStatus(422);
        }

        $this->postJson('/api/auth/login/verify-otp', [
            'email' => 'user@example.com',
            'code' => $code,
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Too many incorrect attempts. Please log in again to get a new code.');
    }

    public function test_second_login_within_cooldown_does_not_send_second_email(): void
    {
        Mail::fake();
        $this->activeUser();

        $this->postJson('/api/auth/login', [
            'email' => 'user@example.com',
            'password' => 'password123',
        ])->assertOk();

        $second = $this->postJson('/api/auth/login', [
            'email' => 'user@example.com',
            'password' => 'password123',
        ]);

        $second
            ->assertOk()
            ->assertJsonPath('requires_two_factor', true);

        $this->assertLessThanOrEqual(60, $second->json('otp_resend_in'));

        Mail::assertSentCount(1);
    }

    public function test_resend_without_open_challenge_sends_nothing(): void
    {
        Mail::fake();
        $this->activeUser();

        $this->postJson('/api/auth/login/resend-otp', [
            'email' => 'user@example.com',
        ])
            ->assertOk()
            ->assertJsonPath('status_code', 1);

        Mail::assertNothingSent();
    }

    public function test_resend_after_cooldown_sends_a_new_code(): void
    {
        Mail::fake();
        $this->activeUser();
        $this->loginAndGrabCode();

        $this->travel(61)->seconds();

        $this->postJson('/api/auth/login/resend-otp', [
            'email' => 'user@example.com',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'A new login code has been sent to your email.');

        Mail::assertSentCount(2);
    }

    public function test_google_login_skips_two_factor_and_gets_24_hour_session(): void
    {
        $response = $this->postJson('/api/auth/google-login', [
            'provider_user_id' => 'google-user-2fa',
            'email' => 'google2fa@example.com',
            'name' => 'Google User',
            'id_token' => 'token',
            'platform' => 'android',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonStructure(['access_token']);

        $session = UserSession::query()
            ->where('user_id', $response->json('user.id'))
            ->latest('id')
            ->first();

        $this->assertTrue($session->expires_at->lte(now()->addHours(24)->addMinutes(1)));
    }

    public function test_banned_user_gets_distinct_login_error(): void
    {
        User::factory()->create([
            'email' => 'banned@example.com',
            'password_hash' => bcrypt('password123'),
            'status' => 'banned',
            'status_reason' => 'Chargeback fraud',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'banned@example.com',
            'password' => 'password123',
        ])
            ->assertStatus(403)
            ->assertJsonPath('code', 'account_banned')
            ->assertJsonPath('reason', 'Chargeback fraud');
    }

    public function test_suspended_user_login_includes_suspension_end(): void
    {
        $until = now()->addDays(2);

        User::factory()->create([
            'email' => 'suspended@example.com',
            'password_hash' => bcrypt('password123'),
            'status' => 'suspended',
            'suspended_until' => $until,
            'status_reason' => 'Cool down',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'suspended@example.com',
            'password' => 'password123',
        ]);

        $response
            ->assertStatus(403)
            ->assertJsonPath('code', 'account_suspended')
            ->assertJsonPath('reason', 'Cool down');

        $this->assertNotNull($response->json('suspended_until'));
    }

    public function test_expired_suspension_is_lifted_at_login(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'released@example.com',
            'password_hash' => bcrypt('password123'),
            'status' => 'suspended',
            'suspended_until' => now()->subHour(),
            'status_reason' => 'Time served',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'released@example.com',
            'password' => 'password123',
        ])
            ->assertOk()
            ->assertJsonPath('requires_two_factor', true);

        $this->assertSame('active', $user->fresh()->status);
    }

    public function test_forgot_password_sends_nothing_for_banned_account(): void
    {
        Mail::fake();

        User::factory()->create([
            'email' => 'banned@example.com',
            'status' => 'banned',
        ]);

        $this->postJson('/api/auth/forgot-password', [
            'email' => 'banned@example.com',
        ])
            ->assertOk()
            ->assertJsonPath('status_code', 1);

        Mail::assertNothingSent();
        $this->assertDatabaseMissing('password_resets', ['email' => 'banned@example.com']);
    }
}
