<?php

use App\Models\AppConfig;
use App\Models\DeviceToken;
use App\Models\Notification;
use App\Models\PushNotificationLog;
use App\Models\User;
use App\Mail\GlobalAppMail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

if (! function_exists('stylebite_app_config')) {
    /**
     * Read an admin-managed setting from app_configs (Admin → Settings),
     * cast per its value_type. Cached briefly; admin saves bust the cache.
     */
    function stylebite_app_config(string $key, mixed $default = null): mixed
    {
        $configs = Cache::remember(
            'stylebite_app_configs',
            300,
            fn () => AppConfig::query()
                ->get(['config_key', 'config_value', 'value_type'])
                ->keyBy('config_key')
                ->map(fn (AppConfig $config) => [
                    'value' => $config->config_value,
                    'type' => $config->value_type,
                ])
                ->all()
        );

        if (! isset($configs[$key]) || $configs[$key]['value'] === null || $configs[$key]['value'] === '') {
            return $default;
        }

        $value = $configs[$key]['value'];

        return match ($configs[$key]['type']) {
            'number' => (float) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($value, true) ?? $default,
            default => $value,
        };
    }
}

if (! function_exists('stylebite_ad_eligibility')) {
    /**
     * Evaluate a creator against the admin-configured monetization / ads criteria
     * (Admin → Settings → Ads). Read-only metrics; request status comes from profile.
     *
     * Vibe Count in the app = follower_count. Watch hours come from post_views.
     *
     * @return array<string, mixed>
     */
    function stylebite_ad_eligibility(int $userId): array
    {
        $minFollowers = (int) stylebite_app_config('ads.min_followers', 500);
        $minWatchHours = (float) stylebite_app_config('ads.min_watch_hours', 1000);
        $infoText = trim((string) stylebite_app_config(
            'ads.monetization_info',
            'Unlock StyleBite Monetization when your account reaches the community goals set by StyleBite. Grow your Vibe Count and Watch Time, then send a request for review.'
        ));

        $profile = \App\Models\Profile::query()->where('user_id', $userId)->first();
        $followers = (int) ($profile?->follower_count ?? 0);

        $watchSeconds = (int) (\Illuminate\Support\Facades\DB::table('post_views')
            ->join('posts', 'posts.id', '=', 'post_views.post_id')
            ->where('posts.user_id', $userId)
            ->whereNull('posts.deleted_at')
            ->sum('post_views.watch_seconds') ?? 0);

        $watchHours = round($watchSeconds / 3600, 2);
        $meetsFollowers = $followers >= $minFollowers;
        $meetsWatchHours = $watchHours >= $minWatchHours;
        $eligible = $meetsFollowers && $meetsWatchHours;

        $status = (string) ($profile?->monetization_status ?? 'none');
        if ($status === '') {
            $status = 'none';
        }

        $followerProgress = $minFollowers > 0
            ? min(1, round($followers / $minFollowers, 4))
            : 1.0;
        $watchProgress = $minWatchHours > 0
            ? min(1, round($watchHours / $minWatchHours, 4))
            : 1.0;
        $overallProgress = round(($followerProgress + $watchProgress) / 2, 4);
        $adsEnabled = (bool) ($profile?->ads_enabled ?? false);
        $isApproved = $status === 'approved' || $adsEnabled;

        return [
            'eligible' => $eligible,
            'is_eligible' => $eligible,
            'ads_enabled' => $adsEnabled,
            'monetization_status' => $status,
            'monetization_requested_at' => optional($profile?->monetization_requested_at)?->toIso8601String(),
            'monetization_reviewed_at' => optional($profile?->monetization_reviewed_at)?->toIso8601String(),
            'monetization_admin_note' => $profile?->monetization_admin_note,
            'can_request' => $eligible && in_array($status, ['none', 'rejected'], true),
            'is_approved' => $isApproved,
            'info' => $infoText,
            'overall_progress' => $overallProgress,
            'config' => [
                'reel_owner_share_percent' => (float) stylebite_app_config('ads.reel_owner_share_percent', 70),
                'mid_reel_trigger_percent' => (float) stylebite_app_config('ads.mid_reel_trigger_percent', 40),
            ],
            'followers' => $followers,
            'vibe_count' => $followers,
            'min_followers' => $minFollowers,
            'min_vibe_count' => $minFollowers,
            'meets_followers' => $meetsFollowers,
            'meets_vibe_count' => $meetsFollowers,
            'watch_hours' => $watchHours,
            'min_watch_hours' => $minWatchHours,
            'meets_watch_hours' => $meetsWatchHours,
            'requirements' => [
                [
                    'type' => 'followers',
                    'label' => 'Vibe Count',
                    'current' => $followers,
                    'threshold' => $minFollowers,
                    'is_passed' => $meetsFollowers,
                    'progress' => $followerProgress,
                    'unit' => 'vibes',
                ],
                [
                    'type' => 'watch_hours',
                    'label' => 'Watch Time',
                    'current' => $watchHours,
                    'threshold' => $minWatchHours,
                    'is_passed' => $meetsWatchHours,
                    'progress' => $watchProgress,
                    'unit' => 'hours',
                ],
            ],
        ];
    }
}

if (! function_exists('stylebite_currency_for_country')) {
    /**
     * Map a profile's free-text country to an ISO 4217 currency code.
     * Returns null when the country is blank or unrecognized so callers
     * can apply their own default.
     */
    function stylebite_currency_for_country(?string $country): ?string
    {
        $normalized = Str::of((string) $country)->lower()->squish()->value();

        if ($normalized === '') {
            return null;
        }

        $map = [
            'PKR' => ['pakistan', 'pk'],
            'INR' => ['india', 'in'],
            'BDT' => ['bangladesh', 'bd'],
            'USD' => ['united states', 'united states of america', 'usa', 'us', 'america'],
            'GBP' => ['united kingdom', 'uk', 'great britain', 'britain', 'england', 'scotland', 'wales', 'northern ireland'],
            'EUR' => ['germany', 'france', 'italy', 'spain', 'netherlands', 'belgium', 'austria', 'ireland', 'portugal', 'greece', 'finland'],
            'AED' => ['united arab emirates', 'uae'],
            'SAR' => ['saudi arabia', 'ksa', 'kingdom of saudi arabia'],
            'QAR' => ['qatar'],
            'KWD' => ['kuwait'],
            'BHD' => ['bahrain'],
            'OMR' => ['oman'],
            'TRY' => ['turkey', 'turkiye', 'türkiye'],
            'CAD' => ['canada'],
            'AUD' => ['australia'],
            'MYR' => ['malaysia'],
            'IDR' => ['indonesia'],
            'SGD' => ['singapore'],
            'CNY' => ['china'],
            'JPY' => ['japan'],
        ];

        foreach ($map as $currency => $countries) {
            if (in_array($normalized, $countries, true)) {
                return $currency;
            }
        }

        return null;
    }
}

if (! function_exists('stylebite_send_email')) {
    function stylebite_send_email(
        string $toEmail,
        string $toName,
        string $subject,
        string $heading,
        string $content,
        ?string $actionText = null,
        ?string $actionUrl = null
    ): void {
        Mail::to($toEmail, $toName)->send(
            new GlobalAppMail($subject, $heading, $content, $actionText, $actionUrl)
        );
    }
}

if (! function_exists('stylebite_normalize_email')) {
    /**
     * Lower/trim, and collapse Gmail aliases (dots / +tags / googlemail.com)
     * so the same mailbox cannot register twice under lookalike addresses.
     */
    function stylebite_normalize_email(string $email): string
    {
        $email = Str::lower(trim($email));
        if (! str_contains($email, '@')) {
            return $email;
        }

        [$local, $domain] = explode('@', $email, 2);
        if (in_array($domain, ['gmail.com', 'googlemail.com'], true)) {
            $local = Str::before($local, '+');
            $local = str_replace('.', '', $local);

            return $local.'@gmail.com';
        }

        return $email;
    }
}

if (! function_exists('stylebite_allowed_email_domains')) {
    /**
     * Consumer mailboxes allowed for StyleBite signup / first-time social login.
     * Gmail + iCloud (including Apple's legacy iCloud aliases).
     *
     * @return list<string>
     */
    function stylebite_allowed_email_domains(): array
    {
        return [
            'gmail.com',
            'googlemail.com',
            'icloud.com',
            'me.com',
            'mac.com',
            // Apple Sign In "Hide My Email"
            'privaterelay.appleid.com',
            'appleid.com',
        ];
    }
}

if (! function_exists('stylebite_email_domain_allowed')) {
    /**
     * True when the address uses an allowed signup domain (gmail / iCloud).
     */
    function stylebite_email_domain_allowed(string $email): bool
    {
        $email = Str::lower(trim($email));
        if ($email === '' || ! str_contains($email, '@')) {
            return false;
        }

        $domain = Str::afterLast($email, '@');

        return in_array($domain, stylebite_allowed_email_domains(), true);
    }
}

if (! function_exists('stylebite_email_is_registered')) {
    /**
     * True when any user (including soft-deleted) already owns this mailbox.
     */
    function stylebite_email_is_registered(string $email): bool
    {
        $email = Str::lower(trim($email));
        if ($email === '') {
            return false;
        }

        if (User::withTrashed()->where('email', $email)->exists()) {
            return true;
        }

        $normalized = stylebite_normalize_email($email);
        if ($normalized === $email || ! str_ends_with($normalized, '@gmail.com')) {
            return false;
        }

        $gmailUsers = User::withTrashed()
            ->where(function ($query) {
                $query->where('email', 'like', '%@gmail.com')
                    ->orWhere('email', 'like', '%@googlemail.com');
            })
            ->get(['id', 'email']);

        foreach ($gmailUsers as $user) {
            if (stylebite_normalize_email((string) $user->email) === $normalized) {
                return true;
            }
        }

        return false;
    }
}

if (! function_exists('stylebite_upload_file')) {
    function stylebite_upload_file(UploadedFile $file, string $folderName): array
    {
        $folderName = trim($folderName, '/');
        $destinationPath = base_path($folderName);
        $originalFileName = $file->getClientOriginalName();
        $mimeType = $file->getMimeType();
        $sizeBytes = $file->getSize();

        if (! File::exists($destinationPath)) {
            File::makeDirectory($destinationPath, 0755, true);
        }

        $extension = $file->getClientOriginalExtension() ?: $file->extension() ?: 'bin';
        $fileName = Str::uuid()->toString().'.'.$extension;
        $file->move($destinationPath, $fileName);
        $storedPath = $folderName.'/'.$fileName;

        return [
            'file_path' => $storedPath,
            'file_url' => rtrim((string) config('app.asset_url'), '/').'/'.$storedPath,
            'original_file_name' => $originalFileName,
            'mime_type' => $mimeType,
            'size_bytes' => $sizeBytes,
        ];
    }
}

if (! function_exists('stylebite_asset_url')) {
    function stylebite_asset_url(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        $path = trim($path);
        $assetBaseUrl = rtrim((string) config('app.asset_url'), '/');

        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, 'data:')) {
            return $path;
        }

        if (preg_match('/^https?:\/\//i', $path) === 1) {
            $host = Str::lower((string) parse_url($path, PHP_URL_HOST));

            if (in_array($host, ['localhost', '127.0.0.1', '0.0.0.0'], true)) {
                $rewrittenPath = (string) parse_url($path, PHP_URL_PATH);
                $query = parse_url($path, PHP_URL_QUERY);

                return $assetBaseUrl
                    .'/'.ltrim($rewrittenPath, '/')
                    .($query ? '?'.$query : '');
            }

            return $path;
        }

        if (preg_match('/^\/\//', $path) === 1) {
            return 'https:'.$path;
        }

        return $assetBaseUrl.'/'.ltrim($path, '/');
    }
}

if (! function_exists('stylebite_notify_user')) {
    function stylebite_notify_user(
        int $recipientUserId,
        ?int $actorUserId,
        string $type,
        string $entityType,
        ?int $entityId,
        ?string $title,
        ?string $body,
        ?string $actionUrl = null,
        ?string $image = null,
        array $extraData = []
    ): Notification {
        $notification = Notification::create([
            'recipient_user_id' => $recipientUserId,
            'actor_user_id' => $actorUserId,
            'type' => $type,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'title' => $title,
            'body' => $body,
            'image_url' => $image,
            'action_url' => $actionUrl,
            'delivery_status' => 'pending',
        ]);

        $recipient = User::query()
            ->with(['settings', 'deviceTokens' => fn ($query) => $query->where('is_active', true)])
            ->find($recipientUserId);

        if (! $recipient) {
            $notification->forceFill([
                'delivery_status' => 'failed',
            ])->save();

            return $notification->fresh();
        }

        if (
            $actorUserId !== null
            && $actorUserId === $recipientUserId
        ) {
            $notification->forceFill([
                'delivery_status' => 'skipped',
            ])->save();

            return $notification->fresh();
        }

        if (($recipient->settings?->push_notifications_enabled ?? true) !== true) {
            $notification->forceFill([
                'delivery_status' => 'skipped',
            ])->save();

            return $notification->fresh();
        }

        $deviceTokens = $recipient->deviceTokens;

        if ($deviceTokens->isEmpty()) {
            $notification->forceFill([
                'delivery_status' => 'skipped',
            ])->save();

            return $notification->fresh();
        }

        $imageUrl = stylebite_asset_url($image);
        $sentAt = null;
        $hasSuccessfulPush = false;
        $hasFailedPush = false;

        try {
            foreach ($deviceTokens as $deviceToken) {
                $pushData = array_merge([
                    'notification_id' => (string) $notification->id,
                    'type' => $type,
                    'entity_type' => $entityType,
                    'entity_id' => $entityId !== null ? (string) $entityId : '',
                    'recipient_user_id' => (string) $recipientUserId,
                    'actor_user_id' => $actorUserId !== null ? (string) $actorUserId : '',
                    'action_url' => $actionUrl ?? '',
                    'title' => (string) ($title ?? ''),
                    'body' => (string) ($body ?? ''),
                ], $extraData);

                $pushResult = stylebite_send_firebase_push_notification(
                    $deviceToken->push_token,
                    $title,
                    $body,
                    $pushData,
                    $imageUrl
                );

                PushNotificationLog::create([
                    'notification_id' => $notification->id,
                    'user_id' => $recipientUserId,
                    'device_token_id' => $deviceToken->id,
                    'provider' => 'fcm',
                    'status' => $pushResult['status'],
                    'provider_response' => $pushResult['provider_response'],
                    'sent_at' => $pushResult['sent_at'],
                ]);

                if ($pushResult['status'] === 'sent') {
                    $hasSuccessfulPush = true;
                    $sentAt = $pushResult['sent_at'];
                } else {
                    $hasFailedPush = true;
                }
            }
        } catch (\Throwable $exception) {
            $hasFailedPush = true;

            PushNotificationLog::create([
                'notification_id' => $notification->id,
                'user_id' => $recipientUserId,
                'device_token_id' => null,
                'provider' => 'fcm',
                'status' => 'failed',
                'provider_response' => Str::limit($exception->getMessage(), 65000, ''),
                'sent_at' => null,
            ]);

            Log::warning('Failed to send Firebase push notification.', [
                'notification_id' => $notification->id,
                'recipient_user_id' => $recipientUserId,
                'message' => $exception->getMessage(),
            ]);
        }

        $notification->forceFill([
            'delivery_status' => $hasSuccessfulPush ? 'sent' : ($hasFailedPush ? 'failed' : 'skipped'),
            'push_sent_at' => $sentAt,
        ])->save();

        return $notification->fresh();
    }
}

if (! function_exists('stylebite_send_firebase_push_notification')) {
    function stylebite_send_firebase_push_notification(
        string $pushToken,
        ?string $title,
        ?string $body,
        array $data = [],
        ?string $image = null
    ): array {
        $serviceAccount = stylebite_firebase_service_account();
        $projectId = $serviceAccount['project_id'] ?? config('services.firebase.project_id');

        if (! is_string($projectId) || trim($projectId) === '') {
            throw new RuntimeException('Firebase project ID is missing.');
        }

        $accessToken = stylebite_firebase_access_token($serviceAccount);
        $notificationPayload = array_filter([
            'title' => $title,
            'body' => $body,
            'image' => $image,
        ], fn ($value) => $value !== null && $value !== '');

        $dataPayload = collect($data)
            ->filter(fn ($value) => $value !== null)
            ->map(fn ($value) => (string) $value)
            ->all();

        $message = array_filter([
            'token' => $pushToken,
            'notification' => $notificationPayload,
            'data' => $dataPayload,
            'android' => [
                'priority' => 'high',
                'notification' => array_filter([
                    'image' => $image,
                    'sound' => 'default',
                ], fn ($value) => $value !== null && $value !== ''),
            ],
            'apns' => [
                'headers' => [
                    'apns-priority' => '10',
                ],
                'payload' => [
                    'aps' => [
                        'sound' => 'default',
                    ],
                ],
                'fcm_options' => array_filter([
                    'image' => $image,
                ], fn ($value) => $value !== null && $value !== ''),
            ],
        ], fn ($value) => ! (is_array($value) && $value === []));

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->post(rtrim((string) config('services.firebase.messaging_base_url'), '/').'/'.$projectId.'/messages:send', [
                'message' => $message,
            ]);

        return [
            'status' => $response->successful() ? 'sent' : 'failed',
            'provider_response' => $response->body(),
            'sent_at' => $response->successful() ? now() : null,
        ];
    }
}

if (! function_exists('stylebite_firebase_access_token')) {
    function stylebite_firebase_access_token(array $serviceAccount): string
    {
        static $cachedToken = null;
        static $cachedTokenExpiresAt = null;

        if (
            is_string($cachedToken)
            && $cachedTokenExpiresAt instanceof DateTimeInterface
            && $cachedTokenExpiresAt > now()->addMinute()
        ) {
            return $cachedToken;
        }

        $cacheKey = 'stylebite_firebase_access_token_'.md5((string) ($serviceAccount['client_email'] ?? 'default'));
        $cached = Cache::get($cacheKey);

        if (
            is_array($cached)
            && isset($cached['access_token'], $cached['expires_at'])
            && now()->lt(\Illuminate\Support\Carbon::parse($cached['expires_at'])->subMinute())
        ) {
            $cachedToken = $cached['access_token'];
            $cachedTokenExpiresAt = \Illuminate\Support\Carbon::parse($cached['expires_at']);

            return $cachedToken;
        }

        $response = Http::asForm()->post(
            (string) ($serviceAccount['token_uri'] ?? config('services.firebase.token_uri')),
            [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => stylebite_firebase_jwt($serviceAccount),
            ]
        );

        if (! $response->successful()) {
            throw new RuntimeException('Unable to fetch Firebase access token: '.$response->body());
        }

        $payload = $response->json();
        $expiresIn = max(60, (int) ($payload['expires_in'] ?? 3600));
        $expiresAt = now()->addSeconds($expiresIn);

        $cachedToken = (string) $payload['access_token'];
        $cachedTokenExpiresAt = $expiresAt;

        Cache::put($cacheKey, [
            'access_token' => $cachedToken,
            'expires_at' => $expiresAt->toIso8601String(),
        ], $expiresAt);

        return $cachedToken;
    }
}

if (! function_exists('stylebite_firebase_jwt')) {
    function stylebite_firebase_jwt(array $serviceAccount): string
    {
        $clientEmail = $serviceAccount['client_email'] ?? null;
        $privateKey = $serviceAccount['private_key'] ?? null;
        $tokenUri = $serviceAccount['token_uri'] ?? config('services.firebase.token_uri');

        if (! is_string($clientEmail) || trim($clientEmail) === '') {
            throw new RuntimeException('Firebase client email is missing.');
        }

        if (! is_string($privateKey) || trim($privateKey) === '') {
            throw new RuntimeException('Firebase private key is missing.');
        }

        $header = stylebite_base64url_encode(json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT',
        ], JSON_THROW_ON_ERROR));

        $issuedAt = time();
        $payload = stylebite_base64url_encode(json_encode([
            'iss' => $clientEmail,
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => $tokenUri,
            'iat' => $issuedAt,
            'exp' => $issuedAt + 3600,
        ], JSON_THROW_ON_ERROR));

        $signatureInput = $header.'.'.$payload;
        $signature = '';

        if (! openssl_sign($signatureInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Unable to sign Firebase JWT.');
        }

        return $signatureInput.'.'.stylebite_base64url_encode($signature);
    }
}

if (! function_exists('stylebite_firebase_service_account')) {
    function stylebite_firebase_service_account(): array
    {
        $serviceAccountPath = (string) config('services.firebase.service_account_path');

        if ($serviceAccountPath === '' || ! File::exists($serviceAccountPath)) {
            throw new RuntimeException('Firebase service account file not found at '.$serviceAccountPath);
        }

        $payload = json_decode((string) File::get($serviceAccountPath), true);

        if (! is_array($payload)) {
            throw new RuntimeException('Firebase service account file is invalid JSON.');
        }

        return $payload;
    }
}

if (! function_exists('stylebite_base64url_encode')) {
    function stylebite_base64url_encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
