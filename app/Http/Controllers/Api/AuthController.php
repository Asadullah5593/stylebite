<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Models\EmailVerificationToken;
use App\Models\PasswordReset;
use App\Models\Profile;
use App\Models\User;
use App\Models\UserAuthProvider;
use App\Models\UserSession;
use App\Models\UserSetting;
use Carbon\Carbon;
use Dedoc\Scramble\Attributes\BodyParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:191', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'device_id' => ['nullable', 'string', 'max:191', 'required_with:push_token'],
            'platform' => ['nullable', 'string', 'in:ios,android,web,desktop'],
            'push_token' => ['nullable', 'string', 'max:512'],
            'app_version' => ['nullable', 'string', 'max:32'],
        ], $this->registerValidationMessages(), $this->authValidationAttributes());

        $user = DB::transaction(function () use ($validated): User {
            $user = User::create([
                'email' => Str::lower($validated['email']),
                'username' => $this->generateUniqueUsername($validated['name'], $validated['email']),
                'password_hash' => Hash::make($validated['password']),
                'full_name' => $validated['name'],
                'locale' => 'en',
                'timezone' => config('app.timezone', 'UTC'),
            ]);

            Profile::create([
                'user_id' => $user->id,
                'display_name' => $validated['name'],
            ]);

            UserSetting::create([
                'user_id' => $user->id,
                'timezone' => config('app.timezone', 'UTC'),
            ]);

            return $user;
        });

        $session = $this->createSession($user, $request);
        $this->sendVerificationEmail($user);

        return response()->json([
            'status_code' => 1,
            'message' => 'Registration successful.',
            'token_type' => 'Bearer',
            'access_token' => $session['plain_text_token'],
            'bearer_token' => 'Bearer '.$session['plain_text_token'],
            'user' => $this->userPayload($session['user']),
        ], 201);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:191'],
        ], $this->forgotPasswordValidationMessages(), $this->authValidationAttributes());

        $user = User::query()->where('email', Str::lower($validated['email']))->first();

        if ($user) {
            $code = $this->generateNumericCode();

            PasswordReset::query()
                ->where('user_id', $user->id)
                ->whereNull('used_at')
                ->delete();

            PasswordReset::create([
                'user_id' => $user->id,
                'email' => $user->email,
                'reset_token_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes(15),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent() ? Str::limit($request->userAgent(), 255, '') : null,
            ]);

            stylebite_send_email(
                $user->email,
                $user->full_name ?? $user->username,
                'Your Stylebite password reset code',
                'Password reset request',
                "We received a request to reset your Stylebite password.\n\nYour 6-digit reset code is: {$code}\n\nThis code will expire in 15 minutes."
            );
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'Reset code sent successfully.',
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:191'],
            'password' => ['required', 'string'],
            'device_id' => ['nullable', 'string', 'max:191', 'required_with:push_token'],
            'platform' => ['nullable', 'string', 'in:ios,android,web,desktop'],
            'push_token' => ['nullable', 'string', 'max:512'],
            'app_version' => ['nullable', 'string', 'max:32'],
        ], $this->loginValidationMessages(), $this->authValidationAttributes());

        $user = User::query()
            ->where('email', Str::lower($validated['email']))
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password_hash)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->status !== 'active') {
            throw ValidationException::withMessages([
                'email' => ['This account is not active.'],
            ]);
        }

        $session = $this->createSession($user, $request);

        return response()->json([
            'status_code' => 1,
            'message' => 'Login successful.',
            'token_type' => 'Bearer',
            'access_token' => $session['plain_text_token'],
            'bearer_token' => 'Bearer '.$session['plain_text_token'],
            'user' => $this->userPayload($session['user']),
        ]);
    }

    #[BodyParameter('provider_user_id', description: 'Unique user ID issued by Google.', required: true, type: 'string', example: '112233445566778899000')]
    #[BodyParameter('email', type: 'string', example: 'user@example.com')]
    #[BodyParameter('name', type: 'string', example: 'Asad Ullah')]
    #[BodyParameter('id_token', description: 'Google ID token (JWT).', type: 'string', example: 'eyJhbGciOiJSUzI1NiIs...')]
    #[BodyParameter('identity_token', type: 'string')]
    #[BodyParameter('access_token', type: 'string')]
    #[BodyParameter('refresh_token', type: 'string')]
    #[BodyParameter('token_expires_at', type: 'string', format: 'date-time', example: '2026-08-01T00:00:00Z')]
    #[BodyParameter('device_id', description: 'Required when push_token is provided.', type: 'string', example: 'A1B2C3D4-E5F6')]
    #[BodyParameter('platform', description: 'One of: ios, android, web, desktop.', type: 'string', example: 'android')]
    #[BodyParameter('push_token', type: 'string', example: 'fcm_token_abc123')]
    #[BodyParameter('app_version', type: 'string', example: '1.4.2')]
    public function googleLogin(Request $request): JsonResponse
    {
        return $this->providerLogin($request, 'google');
    }

    #[BodyParameter('provider_user_id', description: 'Unique user ID issued by Apple.', required: true, type: 'string', example: '001122.aabbccddeeff.0011')]
    #[BodyParameter('email', type: 'string', example: 'user@example.com')]
    #[BodyParameter('name', type: 'string', example: 'Asad Ullah')]
    #[BodyParameter('id_token', type: 'string')]
    #[BodyParameter('identity_token', description: 'Apple identity token (JWT).', type: 'string', example: 'eyJraWQiOiJXNlJIL...')]
    #[BodyParameter('access_token', type: 'string')]
    #[BodyParameter('refresh_token', type: 'string')]
    #[BodyParameter('token_expires_at', type: 'string', format: 'date-time', example: '2026-08-01T00:00:00Z')]
    #[BodyParameter('device_id', description: 'Required when push_token is provided.', type: 'string', example: 'A1B2C3D4-E5F6')]
    #[BodyParameter('platform', description: 'One of: ios, android, web, desktop.', type: 'string', example: 'ios')]
    #[BodyParameter('push_token', type: 'string', example: 'apns_token_abc123')]
    #[BodyParameter('app_version', type: 'string', example: '1.4.2')]
    public function appleLogin(Request $request): JsonResponse
    {
        return $this->providerLogin($request, 'apple');
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:191'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], $this->resetPasswordValidationMessages(), $this->authValidationAttributes());

        $user = User::query()->where('email', Str::lower($validated['email']))->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['No account was found for this email address.'],
            ]);
        }

        $reset = PasswordReset::query()
            ->where('user_id', $user->id)
            ->where('email', $user->email)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (! $reset || ! Hash::check($validated['code'], $reset->reset_token_hash)) {
            throw ValidationException::withMessages([
                'code' => ['The reset code is invalid or expired.'],
            ]);
        }

        DB::transaction(function () use ($user, $reset, $validated): void {
            $user->forceFill([
                'password_hash' => Hash::make($validated['password']),
                'failed_login_attempts' => 0,
            ])->save();

            $reset->forceFill([
                'used_at' => now(),
            ])->save();
        });

        return response()->json([
            'status_code' => 1,
            'message' => 'Password has been reset successfully.',
        ]);
    }

    public function verifyEmail(Request $request, int $id, string $token): JsonResponse
    {
        if (! $request->hasValidSignature()) {
            return response()->json([
                'status_code' => 0,
                'message' => 'This verification link is invalid or has expired.',
            ], 422);
        }

        $user = User::query()->findOrFail($id);

        $verification = EmailVerificationToken::query()
            ->where('user_id', $user->id)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (! $verification || ! hash_equals($verification->token_hash, hash('sha256', $token))) {
            return response()->json([
                'status_code' => 0,
                'message' => 'This verification link is invalid or has expired.',
            ], 422);
        }

        DB::transaction(function () use ($user, $verification): void {
            $user->forceFill([
                'email_verified_at' => now(),
                'status' => $user->status === 'inactive' ? 'active' : $user->status,
            ])->save();

            $verification->forceFill([
                'verified_at' => now(),
            ])->save();
        });

        return response()->json([
            'status_code' => 1,
            'message' => 'Email verified successfully.',
            'user' => $this->userPayload($user->fresh(['profile', 'settings'])),
        ]);
    }

    private function createSession(User $user, Request $request): array
    {
        $plainTextToken = Str::random(80);
        $platform = $this->detectPlatform($request);
        $deviceId = $request->input('device_id', $request->header('X-Device-Id'));

        $session = UserSession::create([
            'user_id' => $user->id,
            'session_token_hash' => hash('sha256', $plainTextToken),
            'device_id' => $deviceId,
            'device_name' => $request->userAgent() ? Str::limit($request->userAgent(), 120, '') : null,
            'platform' => $platform,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent() ? Str::limit($request->userAgent(), 255, '') : null,
            'last_seen_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        $this->storeDevicePushToken($user, $request, $platform, $deviceId);

        $user->forceFill([
            'last_login_at' => now(),
            'last_seen_at' => now(),
        ])->save();

        return [
            'plain_text_token' => $plainTextToken,
            'session' => $session,
            'user' => $user->fresh(['profile', 'settings']),
        ];
    }

    private function providerLogin(Request $request, string $provider): JsonResponse
    {
        $validated = $request->validate([
            'provider_user_id' => ['required', 'string', 'max:191'],
            'email' => ['nullable', 'string', 'email', 'max:191'],
            'name' => ['nullable', 'string', 'max:120'],
            'id_token' => ['nullable', 'string'],
            'identity_token' => ['nullable', 'string'],
            'access_token' => ['nullable', 'string'],
            'refresh_token' => ['nullable', 'string'],
            'token_expires_at' => ['nullable', 'date'],
            'device_id' => ['nullable', 'string', 'max:191', 'required_with:push_token'],
            'platform' => ['nullable', 'string', 'in:ios,android,web,desktop'],
            'push_token' => ['nullable', 'string', 'max:512'],
            'app_version' => ['nullable', 'string', 'max:32'],
        ], $this->providerLoginValidationMessages(), $this->authValidationAttributes());

        $providerUserId = $validated['provider_user_id'];
        $email = isset($validated['email']) ? Str::lower($validated['email']) : null;
        $name = $validated['name'] ?? ($email ? Str::before($email, '@') : Str::headline($provider).' User');

        $user = DB::transaction(function () use ($provider, $providerUserId, $email, $name, $validated): User {
            $authProvider = UserAuthProvider::query()
                ->where('provider', $provider)
                ->where('provider_user_id', $providerUserId)
                ->first();

            if ($authProvider) {
                $this->updateAuthProvider($authProvider, $validated, $email);

                return $authProvider->user;
            }

            if (! $email) {
                throw ValidationException::withMessages([
                    'email' => ['Email is required for first-time '.Str::headline($provider).' login.'],
                ]);
            }

            $user = User::query()->where('email', $email)->first();

            if (! $user) {
                $user = User::create([
                    'email' => $email,
                    'username' => $this->generateUniqueUsername($name, $email),
                    'password_hash' => Hash::make(Str::random(40)),
                    'full_name' => $name,
                    'status' => 'active',
                    'email_verified_at' => now(),
                    'locale' => 'en',
                    'timezone' => config('app.timezone', 'UTC'),
                ]);

                Profile::create([
                    'user_id' => $user->id,
                    'display_name' => $name,
                ]);

                UserSetting::create([
                    'user_id' => $user->id,
                    'timezone' => config('app.timezone', 'UTC'),
                ]);
            }

            $authProvider = UserAuthProvider::query()
                ->where('user_id', $user->id)
                ->where('provider', $provider)
                ->first();

            if ($authProvider && $authProvider->provider_user_id !== $providerUserId) {
                throw ValidationException::withMessages([
                    'email' => ['This account is already linked to another '.Str::headline($provider).' login.'],
                ]);
            }

            $authProvider ??= UserAuthProvider::create([
                'user_id' => $user->id,
                'provider' => $provider,
                'provider_user_id' => $providerUserId,
            ]);

            $this->updateAuthProvider($authProvider, $validated, $email);

            return $user;
        });

        if ($user->status !== 'active') {
            throw ValidationException::withMessages([
                'email' => ['This account is not active.'],
            ]);
        }

        $session = $this->createSession($user, $request);

        return response()->json([
            'status_code' => 1,
            'message' => Str::headline($provider).' login successful.',
            'token_type' => 'Bearer',
            'access_token' => $session['plain_text_token'],
            'bearer_token' => 'Bearer '.$session['plain_text_token'],
            'user' => $this->userPayload($session['user']),
        ]);
    }

    private function updateAuthProvider(UserAuthProvider $authProvider, array $validated, ?string $email): void
    {
        $authProvider->forceFill([
            'provider_email' => $email ?? $authProvider->provider_email,
            'access_token' => $validated['access_token']
                ?? $validated['id_token']
                ?? $validated['identity_token']
                ?? $authProvider->access_token,
            'refresh_token' => $validated['refresh_token'] ?? $authProvider->refresh_token,
            'token_expires_at' => isset($validated['token_expires_at'])
                ? Carbon::parse($validated['token_expires_at'])
                : $authProvider->token_expires_at,
        ])->save();
    }

    private function detectPlatform(Request $request): string
    {
        $platform = Str::lower((string) $request->input('platform', $request->header('X-Platform', 'web')));

        return in_array($platform, ['ios', 'android', 'web', 'desktop'], true)
            ? $platform
            : 'web';
    }

    private function storeDevicePushToken(User $user, Request $request, string $platform, ?string $deviceId): void
    {
        $pushToken = $request->input('push_token');

        if (! $pushToken) {
            return;
        }

        if (! $deviceId) {
            throw ValidationException::withMessages([
                'device_id' => ['The device_id field is required when push_token is provided.'],
            ]);
        }

        if (! in_array($platform, ['ios', 'android', 'web'], true)) {
            throw ValidationException::withMessages([
                'platform' => ['Push token storage only supports ios, android, or web platforms.'],
            ]);
        }

        DB::transaction(function () use ($user, $deviceId, $platform, $pushToken, $request): void {
            $existingForDevice = DeviceToken::query()
                ->where('user_id', $user->id)
                ->where('device_id', $deviceId)
                ->first();

            $existingForPushToken = DeviceToken::query()
                ->where('platform', $platform)
                ->where('push_token', $pushToken)
                ->first();

            if (
                $existingForDevice
                && $existingForPushToken
                && $existingForDevice->id !== $existingForPushToken->id
            ) {
                $existingForDevice->delete();
            }

            $deviceToken = $existingForPushToken ?? $existingForDevice ?? new DeviceToken;

            $deviceToken->forceFill([
                'user_id' => $user->id,
                'device_id' => $deviceId,
                'platform' => $platform,
                'push_token' => $pushToken,
                'app_version' => $request->input('app_version'),
                'is_active' => true,
                'last_used_at' => now(),
            ])->save();
        });
    }

    private function generateUniqueUsername(string $name, string $email): string
    {
        $base = Str::of($name)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '')
            ->value();

        if ($base === '') {
            $base = Str::before(Str::lower($email), '@');
            $base = preg_replace('/[^a-z0-9]+/', '', $base) ?: 'user';
        }

        $base = Str::limit($base, 40, '');
        $username = $base;
        $counter = 1;

        while (User::query()->where('username', $username)->exists()) {
            $suffix = (string) $counter;
            $username = Str::limit($base, 50 - strlen($suffix), '').$suffix;
            $counter++;
        }

        return $username;
    }

    private function sendVerificationEmail(User $user): void
    {
        EmailVerificationToken::query()
            ->where('user_id', $user->id)
            ->whereNull('verified_at')
            ->delete();

        $plainTextToken = Str::random(64);

        EmailVerificationToken::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'token_hash' => hash('sha256', $plainTextToken),
            'expires_at' => now()->addHours(24),
        ]);

        $verificationUrl = URL::temporarySignedRoute(
            'auth.verify-email',
            now()->addHours(24),
            [
                'id' => $user->id,
                'token' => $plainTextToken,
            ]
        );

        stylebite_send_email(
            $user->email,
            $user->full_name ?? $user->username,
            'Verify your Stylebite account',
            'Welcome to Stylebite',
            "Thanks for registering with Stylebite.\n\nPlease verify your email address by clicking the button below. This verification link will expire in 24 hours.",
            'Verify Email',
            $verificationUrl
        );
    }

    private function generateNumericCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->full_name,
            'email' => $user->email,
            'username' => $user->username,
            'avatar_url' => stylebite_asset_url($user->avatar_url),
            'cover_url' => stylebite_asset_url($user->cover_url),
            'role' => $user->role,
            'status' => $user->status,
            'email_verified_at' => $user->email_verified_at?->toISOString(),
            'is_email_verified' => $user->email_verified_at !== null,
            'profile' => [
                'display_name' => $user->profile?->display_name,
            ],
        ];
    }

    private function registerValidationMessages(): array
    {
        return [
            'name.required' => 'Please enter your full name.',
            'name.max' => 'Your full name may not be greater than 120 characters.',
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'An account with this email already exists.',
            'password.required' => 'Please enter a password.',
            'password.confirmed' => 'Password confirmation does not match.',
            'password.min' => 'Your password must be at least 8 characters long.',
            'device_id.required_with' => 'Device ID is required when a push token is provided.',
            'platform.in' => 'Please choose a valid platform.',
            'push_token.max' => 'Push token is too long. Please try again.',
            'app_version.max' => 'App version is too long. Please try again.',
        ];
    }

    private function loginValidationMessages(): array
    {
        return [
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
            'password.required' => 'Please enter your password.',
            'device_id.required_with' => 'Device ID is required when a push token is provided.',
            'platform.in' => 'Please choose a valid platform.',
            'push_token.max' => 'Push token is too long. Please try again.',
            'app_version.max' => 'App version is too long. Please try again.',
        ];
    }

    private function providerLoginValidationMessages(): array
    {
        return [
            'provider_user_id.required' => 'Provider user ID is required.',
            'provider_user_id.max' => 'Provider user ID is too long.',
            'email.email' => 'Please enter a valid email address.',
            'name.max' => 'Your full name may not be greater than 120 characters.',
            'device_id.required_with' => 'Device ID is required when a push token is provided.',
            'platform.in' => 'Please choose a valid platform.',
            'push_token.max' => 'Push token is too long. Please try again.',
            'app_version.max' => 'App version is too long. Please try again.',
        ];
    }

    private function forgotPasswordValidationMessages(): array
    {
        return [
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
        ];
    }

    private function resetPasswordValidationMessages(): array
    {
        return [
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
            'code.required' => 'Please enter the 6-digit reset code.',
            'code.digits' => 'The reset code must be 6 digits.',
            'password.required' => 'Please enter a new password.',
            'password.confirmed' => 'Password confirmation does not match.',
            'password.min' => 'Your password must be at least 8 characters long.',
        ];
    }

    private function authValidationAttributes(): array
    {
        return [
            'device_id' => 'device ID',
            'push_token' => 'push token',
            'app_version' => 'app version',
        ];
    }
}
