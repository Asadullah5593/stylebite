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
            ->assertJsonStructure([
                'status_code',
                'message',
                'token_type',
                'access_token',
                'bearer_token',
                'user' => ['id', 'name', 'email', 'username', 'role', 'status', 'email_verified_at', 'is_email_verified', 'profile' => ['display_name']],
            ])
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('token_type', 'Bearer');

        $this->assertDatabaseHas('users', [
            'email' => 'asif@example.com',
            'full_name' => 'Asif Younas',
        ]);

        $this->assertDatabaseHas('profiles', [
            'display_name' => 'Asif Younas',
        ]);

        $this->assertDatabaseHas('user_sessions', [
            'user_id' => $response->json('user.id'),
            'platform' => 'android',
            'device_id' => 'device-register-1',
        ]);

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $response->json('user.id'),
            'device_id' => 'device-register-1',
            'platform' => 'android',
            'push_token' => 'push-token-register-1',
        ]);

        Mail::assertSent(GlobalAppMail::class, function (GlobalAppMail $mail): bool {
            return $mail->subjectLine === 'Verify your Stylebite account'
                && $mail->actionText === 'Verify Email';
        });
    }

    public function test_user_can_login_via_api(): void
    {
        $user = User::factory()->create([
            'email' => 'asif@example.com',
            'password_hash' => bcrypt('password123'),
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'asif@example.com',
            'password' => 'password123',
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
                && preg_match('/\b\d{6}\b/', $mail->contentText) === 1;
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

        $sentMail = null;

        Mail::assertSent(GlobalAppMail::class, function (GlobalAppMail $mail) use (&$sentMail): bool {
            if ($mail->subjectLine !== 'Your Stylebite password reset code') {
                return false;
            }

            $sentMail = $mail;

            return true;
        });

        preg_match('/\b(\d{6})\b/', $sentMail->contentText, $matches);
        $code = $matches[1] ?? null;

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

    public function test_user_can_verify_email_from_mail_link(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Verify User',
            'email' => 'verify@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $mail = null;

        Mail::assertSent(GlobalAppMail::class, function (GlobalAppMail $sentMail) use (&$mail): bool {
            if ($sentMail->subjectLine !== 'Verify your Stylebite account') {
                return false;
            }

            $mail = $sentMail;

            return true;
        });

        $this->assertNotNull($mail?->actionUrl);

        $path = parse_url($mail->actionUrl, PHP_URL_PATH);
        $query = parse_url($mail->actionUrl, PHP_URL_QUERY);

        $verifyResponse = $this->getJson($path.($query ? '?'.$query : ''));

        $verifyResponse
            ->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('message', 'Email verified successfully.');

        $this->assertNotNull(User::query()->find($response->json('user.id'))->email_verified_at);
    }

    public function test_register_can_reuse_existing_push_token_without_returning_raw_database_error(): void
    {
        Mail::fake();

        $firstResponse = $this->postJson('/api/auth/register', [
            'name' => 'First User',
            'email' => 'first@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'device_id' => 'device-1',
            'platform' => 'android',
            'push_token' => 'push-token-shared',
            'app_version' => '1.0.0',
        ])->assertCreated();

        $secondResponse = $this->postJson('/api/auth/register', [
            'name' => 'Second User',
            'email' => 'second@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'device_id' => 'device-2',
            'platform' => 'android',
            'push_token' => 'push-token-shared',
            'app_version' => '1.0.1',
        ]);

        $secondResponse
            ->assertCreated()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('message', 'Registration successful.');

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
