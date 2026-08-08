<?php

namespace Tests\Feature;

use App\Mail\GlobalAppMail;
use App\Models\User;
use App\Models\UserAuthProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_via_api(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Asif Younas',
            'email' => 'asif@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'device_id' => 'device-register-1',
            'platform' => 'android',
            'push_token' => 'push-token-register-1',
            'app_version' => '1.0.0',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('requires_verification', true)
            ->assertJsonMissingPath('access_token')
            ->assertJsonStructure([
                'status_code',
                'message',
                'user' => ['id', 'name', 'email', 'username', 'role', 'status', 'email_verified_at', 'is_email_verified', 'profile' => ['display_name']],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'asif@example.com',
            'full_name' => 'Asif Younas',
            'status' => 'inactive',
        ]);

        $this->assertDatabaseHas('profiles', [
            'display_name' => 'Asif Younas',
        ]);

        // No session or push token until the email OTP is verified.
        $this->assertDatabaseMissing('user_sessions', [
            'user_id' => $response->json('user.id'),
        ]);

        Mail::assertSent(GlobalAppMail::class, function (GlobalAppMail $mail): bool {
            return $mail->subjectLine === 'Your Stylebite verification code'
                && $mail->highlightCode !== null
                && preg_match('/^\d{6}$/', $mail->highlightCode) === 1;
        });
    }

    public function test_user_can_login_via_api_with_email_two_factor(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'asif@example.com',
            'password_hash' => bcrypt('password123'),
            'status' => 'active',
        ]);

        // Step 1: password login → 2FA challenge, no token yet.
        $this->postJson('/api/auth/login', [
            'email' => 'asif@example.com',
            'password' => 'password123',
        ])
            ->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('requires_two_factor', true)
            ->assertJsonMissingPath('access_token');

        $code = null;

        Mail::assertSent(GlobalAppMail::class, function (GlobalAppMail $mail) use (&$code): bool {
            if ($mail->subjectLine !== 'Your Stylebite login code') {
                return false;
            }

            $code = $mail->highlightCode;

            return true;
        });

        $this->assertNotNull($code);

        // Step 2: emailed code → bearer token.
        $response = $this->postJson('/api/auth/login/verify-otp', [
            'email' => 'asif@example.com',
            'code' => $code,
            'device_id' => 'device-login-1',
            'platform' => 'ios',
            'push_token' => 'push-token-login-1',
            'app_version' => '2.0.0',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonStructure(['status_code', 'message', 'token_type', 'access_token', 'bearer_token', 'user']);

        $this->assertDatabaseHas('user_sessions', [
            'user_id' => $user->id,
            'platform' => 'ios',
            'device_id' => 'device-login-1',
        ]);

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'device_id' => 'device-login-1',
            'platform' => 'ios',
            'push_token' => 'push-token-login-1',
        ]);
    }

    public function test_user_can_login_with_google_from_frontend_payload(): void
    {
        $response = $this->postJson('/api/auth/google-login', [
            'provider_user_id' => 'google-user-123',
            'email' => 'google@example.com',
            'name' => 'Google User',
            'id_token' => 'frontend-google-id-token',
            'access_token' => 'frontend-google-access-token',
            'device_id' => 'device-google-1',
            'platform' => 'android',
            'push_token' => 'push-token-google-1',
            'app_version' => '2.1.0',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('message', 'Google login successful.')
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.email', 'google@example.com')
            ->assertJsonStructure(['status_code', 'message', 'token_type', 'access_token', 'bearer_token', 'user']);

        $this->assertDatabaseHas('users', [
            'email' => 'google@example.com',
            'full_name' => 'Google User',
        ]);

        $this->assertDatabaseHas('user_auth_providers', [
            'provider' => 'google',
            'provider_user_id' => 'google-user-123',
            'provider_email' => 'google@example.com',
        ]);

        $this->assertDatabaseHas('user_sessions', [
            'user_id' => $response->json('user.id'),
            'platform' => 'android',
            'device_id' => 'device-google-1',
        ]);
    }

    public function test_first_apple_login_requires_email_when_provider_is_not_linked(): void
    {
        $response = $this->postJson('/api/auth/apple-login', [
            'provider_user_id' => 'apple-user-123',
            'identity_token' => 'frontend-apple-identity-token',
            'platform' => 'ios',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('status_code', 0)
            ->assertJsonPath('message', 'Email is required for first-time Apple login.');
    }

    public function test_existing_apple_provider_can_login_without_email(): void
    {
        $user = User::factory()->create([
            'email' => 'apple@example.com',
            'status' => 'active',
        ]);

        UserAuthProvider::create([
            'user_id' => $user->id,
            'provider' => 'apple',
            'provider_user_id' => 'apple-user-123',
            'provider_email' => 'apple@example.com',
        ]);

        $response = $this->postJson('/api/auth/apple-login', [
            'provider_user_id' => 'apple-user-123',
            'id_token' => 'frontend-apple-identity-token',
            'device_id' => 'device-apple-1',
            'platform' => 'ios',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('message', 'Apple login successful.')
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('token_type', 'Bearer');
    }

    public function test_forgot_password_sends_six_digit_code_to_existing_user(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'reset@example.com',
        ]);

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'reset@example.com',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('message', 'Reset code sent successfully.');

        $this->assertDatabaseHas('password_resets', [
            'user_id' => $user->id,
            'email' => 'reset@example.com',
        ]);

        Mail::assertSent(GlobalAppMail::class, function (GlobalAppMail $mail): bool {
            return $mail->subjectLine === 'Your Stylebite password reset code'
                && $mail->highlightCode !== null
                && preg_match('/^\d{6}$/', $mail->highlightCode) === 1;
        });
    }

    public function test_user_can_reset_password_with_email_and_code(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'reset@example.com',
            'password_hash' => bcrypt('oldpassword'),
        ]);

        $this->postJson('/api/auth/forgot-password', [
            'email' => 'reset@example.com',
        ])->assertOk();

        $code = null;

        Mail::assertSent(GlobalAppMail::class, function (GlobalAppMail $mail) use (&$code): bool {
            if ($mail->subjectLine !== 'Your Stylebite password reset code') {
                return false;
            }

            $code = $mail->highlightCode;

            return true;
        });

        $this->assertNotNull($code);

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => 'reset@example.com',
            'code' => $code,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('status_code', 1);

        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password_hash));
    }

    public function test_user_can_verify_email_with_otp_and_receives_token(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Verify User',
            'email' => 'verify@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $code = null;

        Mail::assertSent(GlobalAppMail::class, function (GlobalAppMail $mail) use (&$code): bool {
            if ($mail->subjectLine !== 'Your Stylebite verification code') {
                return false;
            }

            $code = $mail->highlightCode;

            return true;
        });

        $this->assertNotNull($code);

        $verifyResponse = $this->postJson('/api/auth/verify-email-otp', [
            'email' => 'verify@example.com',
            'code' => $code,
        ]);

        $verifyResponse
            ->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('message', 'Email verified successfully.')
            ->assertJsonStructure(['access_token', 'bearer_token', 'user']);

        $user = User::query()->find($response->json('user.id'));
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame('active', $user->status);
    }

    public function test_verification_can_reuse_existing_push_token_without_returning_raw_database_error(): void
    {
        Mail::fake();

        $verify = function (string $name, string $email, string $deviceId) {
            $this->postJson('/api/auth/register', [
                'name' => $name,
                'email' => $email,
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])->assertCreated();

            $code = null;

            Mail::assertSent(GlobalAppMail::class, function (GlobalAppMail $mail) use (&$code, $email): bool {
                if ($mail->subjectLine !== 'Your Stylebite verification code' || ! $mail->hasTo($email)) {
                    return false;
                }

                $code = $mail->highlightCode;

                return true;
            });

            return $this->postJson('/api/auth/verify-email-otp', [
                'email' => $email,
                'code' => $code,
                'device_id' => $deviceId,
                'platform' => 'android',
                'push_token' => 'push-token-shared',
            ]);
        };

        $firstResponse = $verify('First User', 'first@example.com', 'device-1')->assertOk();
        $secondResponse = $verify('Second User', 'second@example.com', 'device-2');

        $secondResponse
            ->assertOk()
            ->assertJsonPath('status_code', 1);

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $secondResponse->json('user.id'),
            'device_id' => 'device-2',
            'platform' => 'android',
            'push_token' => 'push-token-shared',
        ]);

        $this->assertDatabaseMissing('device_tokens', [
            'user_id' => $firstResponse->json('user.id'),
            'device_id' => 'device-1',
            'platform' => 'android',
            'push_token' => 'push-token-shared',
        ]);
    }

    public function test_register_returns_readable_validation_message_for_duplicate_email(): void
    {
        User::factory()->create([
            'email' => 'asif@example.com',
        ]);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Asif Younas',
            'email' => 'asif@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('status_code', 0)
            ->assertJsonPath('message', 'An account with this email already exists.');
    }

    public function test_login_returns_readable_message_for_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'asif@example.com',
            'password_hash' => bcrypt('password123'),
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'asif@example.com',
            'password' => 'wrong-password',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('status_code', 0)
            ->assertJsonPath('message', 'The provided credentials are incorrect.');
    }

    public function test_forgot_password_returns_readable_message_for_invalid_email(): void
    {
        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'invalid-email',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('status_code', 0)
            ->assertJsonPath('message', 'Please enter a valid email address.');
    }

    public function test_reset_password_returns_readable_message_for_invalid_code_length(): void
    {
        $response = $this->postJson('/api/auth/reset-password', [
            'email' => 'reset@example.com',
            'code' => '123',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('status_code', 0)
            ->assertJsonPath('message', 'The reset code must be 6 digits.');
    }
}
