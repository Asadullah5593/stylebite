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

if (! function_exists('stylebite_reporting_timezone')) {
    /**
     * Timezone that decides where an analytics "day" starts (DAU, new signups).
     *
     * Storage stays UTC everywhere; this only shifts the reporting boundary so
     * a Pakistan-evening user is not counted against the next day. Falls back to
     * Asia/Karachi when the admin-entered value is not a real timezone, because
     * this runs on every authenticated API request.
     */
    function stylebite_reporting_timezone(): string
    {
        $timezone = (string) stylebite_app_config('general.default_timezone', 'Asia/Karachi');

        return in_array($timezone, timezone_identifiers_list(), true)
            ? $timezone
            : 'Asia/Karachi';
    }
}

if (! function_exists('stylebite_streak_mode')) {
    /**
     * What a user has to do to keep a streak alive (Admin → Settings → Streaks).
     *
     *   outfit   — publish an outfit post that day (default; the product spec's
     *              "Daily Outfit Streak")
     *   any_post — publish any post that day, outfit or food
     *   login    — simply open the app that day, nothing to post
     */
    function stylebite_streak_mode(): string
    {
        $mode = strtolower(trim((string) stylebite_app_config('streaks.mode', 'outfit')));

        return in_array($mode, ['outfit', 'any_post', 'login'], true) ? $mode : 'outfit';
    }
}

if (! function_exists('stylebite_streak_active_days')) {
    /**
     * The calendar days (reporting timezone) that count towards a user's streak,
     * as 'Y-m-d' strings — the user's own activity plus any admin-granted grace
     * days, with everything before an admin reset dropped.
     */
    function stylebite_streak_active_days(int $userId, \Carbon\CarbonImmutable $windowStart, ?\Carbon\CarbonInterface $resetAt): array
    {
        $timezone = $windowStart->getTimezone()->getName();
        $offsetMinutes = (int) round($windowStart->getOffset() / 60);
        $days = [];

        if (stylebite_streak_mode() === 'login') {
            $query = \App\Models\UserDailyActivity::query()
                ->where('user_id', $userId)
                ->where('activity_date', '>=', $windowStart->toDateString());

            // Only whole days here, so the reset day itself cannot count — there
            // is no way to tell whether the login came before or after the reset.
            if ($resetAt !== null) {
                $query->where('activity_date', '>', $resetAt->copy()->setTimezone($timezone)->toDateString());
            }

            $days = $query
                ->pluck('activity_date')
                ->map(fn ($date) => $date instanceof \Carbon\CarbonInterface ? $date->toDateString() : (string) $date)
                ->all();
        } else {
            $query = \App\Models\Post::query()
                ->where('user_id', $userId)
                ->where('status', 'published')
                ->whereNotNull('published_at')
                ->where('published_at', '>=', $windowStart->setTimezone('UTC'));

            if (stylebite_streak_mode() === 'outfit') {
                $query->where('post_type', 'outfit');
            }

            // Compared against the reset instant, not the reset day: a reset must
            // read as 0 straight away even if the user already posted earlier
            // today, while a post made after it starts the new streak at once.
            if ($resetAt !== null) {
                $query->where('published_at', '>', $resetAt);
            }

            // Several posts on one day are still one day of the streak.
            $days = $query
                ->distinct()
                ->selectRaw('DATE(published_at + INTERVAL ? MINUTE) as day', [$offsetMinutes])
                ->pluck('day')
                ->map(fn ($day) => (string) $day)
                ->all();
        }

        $graceQuery = \App\Models\StreakGraceDay::query()
            ->where('user_id', $userId)
            ->where('grace_date', '>=', $windowStart->toDateString());

        // Grace granted before a reset is void; grace granted after it stands,
        // whatever day it covers — that is an admin decision made after the fact.
        if ($resetAt !== null) {
            $graceQuery->where('created_at', '>', $resetAt);
        }

        $grace = $graceQuery
            ->pluck('grace_date')
            ->map(fn ($date) => $date instanceof \Carbon\CarbonInterface ? $date->toDateString() : (string) $date)
            ->all();

        $days = array_values(array_unique(array_merge($days, $grace)));

        rsort($days);

        return $days;
    }
}

if (! function_exists('stylebite_recalculate_streak')) {
    /**
     * Recompute and store a user's streak from scratch.
     *
     * Deliberately a pure function of the underlying data — it never reads the
     * stored streak and adds to it. That is what makes every edge case fall out
     * for free: deleting a post drops its day and the streak shortens, posting
     * five times in a day still counts once, and an admin restore works because
     * it adds a grace day rather than writing a number that the next run would
     * overwrite.
     *
     * A streak stays alive if the user was active today *or* yesterday, so it
     * does not collapse mid-morning before they have posted.
     */
    function stylebite_recalculate_streak(int $userId): array
    {
        $profile = \App\Models\Profile::query()->where('user_id', $userId)->first();

        if (! $profile) {
            return ['days' => 0, 'label' => null];
        }

        $today = \Carbon\CarbonImmutable::now(stylebite_reporting_timezone())->startOfDay();
        // Bounds the query; a streak longer than this is not a real streak.
        $windowStart = $today->subDays(400);

        $days = stylebite_streak_active_days($userId, $windowStart, $profile->streak_reset_at);
        $active = array_flip($days);

        $todayKey = $today->toDateString();
        $yesterdayKey = $today->subDay()->toDateString();

        $streak = 0;

        if (isset($active[$todayKey]) || isset($active[$yesterdayKey])) {
            $cursor = isset($active[$todayKey]) ? $today : $today->subDay();

            while (isset($active[$cursor->toDateString()])) {
                $streak++;
                $cursor = $cursor->subDay();
            }
        }

        $profile->forceFill([
            'current_streak_days' => $streak,
            'current_streak_label' => $streak > 0 ? $streak.' day streak' : null,
            'longest_streak_days' => max((int) $profile->longest_streak_days, $streak),
            'last_streak_day' => $days[0] ?? null,
        ])->save();

        return [
            'days' => $streak,
            'label' => $profile->current_streak_label,
        ];
    }
}

if (! function_exists('stylebite_ad_eligibility')) {
    /**
     * Evaluate a creator against the admin-configured ad eligibility criteria
     * (Admin → Settings → Ads). Read-only: nothing is stored or changed.
     *
     * NOTE: watch hours are summed from post_views.watch_seconds, which is not
     * populated yet — until the app starts reporting watch time, watch_hours
     * will read 0 and no creator will meet the watch-hours criterion.
     *
     * @return array{
     *     eligible:bool,
     *     followers:int, min_followers:int, meets_followers:bool,
     *     watch_hours:float, min_watch_hours:float, meets_watch_hours:bool
     * }
     */
    function stylebite_ad_eligibility(int $userId): array
    {
        $minFollowers = (int) stylebite_app_config('ads.min_followers', 500);
        $minWatchHours = (float) stylebite_app_config('ads.min_watch_hours', 1000);

        $followers = (int) (\App\Models\Profile::query()
            ->where('user_id', $userId)
            ->value('follower_count') ?? 0);

        $watchSeconds = (int) (\Illuminate\Support\Facades\DB::table('post_views')
            ->join('posts', 'posts.id', '=', 'post_views.post_id')
            ->where('posts.user_id', $userId)
            ->whereNull('posts.deleted_at')
            ->sum('post_views.watch_seconds') ?? 0);

        $watchHours = round($watchSeconds / 3600, 2);
        $meetsFollowers = $followers >= $minFollowers;
        $meetsWatchHours = $watchHours >= $minWatchHours;

        return [
            'eligible' => $meetsFollowers && $meetsWatchHours,
            'followers' => $followers,
            'min_followers' => $minFollowers,
            'meets_followers' => $meetsFollowers,
            'watch_hours' => $watchHours,
            'min_watch_hours' => $minWatchHours,
            'meets_watch_hours' => $meetsWatchHours,
        ];
    }
}

if (! function_exists('stylebite_refresh_ad_eligibility')) {
    /**
     * Compute ad eligibility AND cache the result on the profile
     * (profiles.ad_eligible). The feed and impressions path read that cheap
     * boolean instead of recomputing the watch-hours aggregate every time.
     * Returns the full eligibility breakdown.
     */
    function stylebite_refresh_ad_eligibility(int $userId): array
    {
        $eligibility = stylebite_ad_eligibility($userId);

        $profile = \App\Models\Profile::query()->firstOrNew(['user_id' => $userId]);

        if ((bool) $profile->ad_eligible !== $eligibility['eligible']) {
            $profile->ad_eligible = $eligibility['eligible'];
            $profile->ad_eligible_at = $eligibility['eligible'] ? now() : null;
            $profile->save();
        }

        return $eligibility;
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

if (! function_exists('stylebite_is_super_admin')) {
    /**
     * Whether this account is on the break-glass super-admin list
     * (SUPER_ADMIN_EMAILS env, comma-separated). These accounts bypass all
     * permission checks via Gate::before and can always enter the panel.
     */
    function stylebite_is_super_admin($user): bool
    {
        if (! $user || empty($user->email)) {
            return false;
        }

        $emails = array_values(array_filter(array_map(
            fn ($email) => strtolower(trim($email)),
            explode(',', (string) config('auth.super_admins', ''))
        )));

        return $emails !== [] && in_array(strtolower($user->email), $emails, true);
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
        ?string $actionUrl = null,
        ?string $highlightCode = null
    ): void {
        Mail::to($toEmail, $toName)->send(
            new GlobalAppMail($subject, $heading, $content, $actionText, $actionUrl, $highlightCode)
        );
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
        ?string $image = null
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
            // One concurrent batch instead of a serial request per device. A
            // user with three devices costs one round-trip's latency, not three
            // — which is what makes campaign fan-out viable.
            $pushResults = stylebite_send_firebase_push_batch(
                $deviceTokens->pluck('push_token')->all(),
                $title,
                $body,
                [
                    'notification_id' => (string) $notification->id,
                    'type' => $type,
                    'entity_type' => $entityType,
                    'entity_id' => $entityId !== null ? (string) $entityId : '',
                    'recipient_user_id' => (string) $recipientUserId,
                    'actor_user_id' => $actorUserId !== null ? (string) $actorUserId : '',
                    'action_url' => $actionUrl ?? '',
                ],
                $imageUrl
            );

            foreach ($deviceTokens as $index => $deviceToken) {
                $pushResult = $pushResults[$index] ?? [
                    'status' => 'failed',
                    'provider_response' => 'No provider response for this device.',
                    'sent_at' => null,
                ];

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

                    // FCM has told us this token will never address anything
                    // again. Keeping the row meant every future notification
                    // retried a dead token forever and reported a failure, which
                    // is what made a campaign look sent-but-undelivered. The push
                    // log survives the delete (device_token_id is nullOnDelete),
                    // so the evidence is not lost.
                    if (stylebite_push_token_is_dead($pushResult['provider_response'])) {
                        $deviceToken->delete();
                    }
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

if (! function_exists('stylebite_send_firebase_push_batch')) {
    /**
     * Send the same notification to many device tokens concurrently.
     *
     * FCM HTTP v1 has no multicast endpoint (that was legacy FCM), and the
     * /batch multipart endpoint is not worth hand-rolling here — so this fires
     * the per-token sends in parallel with Http::pool instead. Results come
     * back in the same order as $pushTokens.
     *
     * The OAuth token is fetched once up front: doing it inside the pool would
     * race every worker into its own token exchange.
     *
     * @param  array<int, string>  $pushTokens
     * @return array<int, array{status:string, provider_response:string, sent_at:?\Illuminate\Support\Carbon}>
     */
    function stylebite_send_firebase_push_batch(
        array $pushTokens,
        ?string $title,
        ?string $body,
        array $data = [],
        ?string $image = null
    ): array {
        $pushTokens = array_values(array_filter($pushTokens, fn ($token) => is_string($token) && trim($token) !== ''));

        if ($pushTokens === []) {
            return [];
        }

        $serviceAccount = stylebite_firebase_service_account();
        $projectId = $serviceAccount['project_id'] ?? config('services.firebase.project_id');

        if (! is_string($projectId) || trim($projectId) === '') {
            throw new RuntimeException('Firebase project ID is missing.');
        }

        $accessToken = stylebite_firebase_access_token($serviceAccount);
        $endpoint = rtrim((string) config('services.firebase.messaging_base_url'), '/').'/'.$projectId.'/messages:send';
        $concurrency = max(1, (int) config('services.firebase.push_concurrency', 20));
        $results = [];

        foreach (array_chunk($pushTokens, $concurrency) as $chunk) {
            $responses = Http::pool(fn ($pool) => array_map(
                fn (string $pushToken) => $pool
                    ->withToken($accessToken)
                    ->acceptJson()
                    ->post($endpoint, [
                        'message' => stylebite_firebase_message_payload($pushToken, $title, $body, $data, $image),
                    ]),
                $chunk
            ));

            foreach ($chunk as $index => $pushToken) {
                $response = $responses[$index] ?? null;

                // A pooled request can hand back a connection exception rather
                // than a response; that is a failed delivery, not a crash.
                if ($response instanceof \Throwable) {
                    $results[] = [
                        'status' => 'failed',
                        'provider_response' => Str::limit($response->getMessage(), 65000, ''),
                        'sent_at' => null,
                    ];

                    continue;
                }

                $successful = $response !== null && $response->successful();

                $results[] = [
                    'status' => $successful ? 'sent' : 'failed',
                    'provider_response' => $response !== null
                        ? Str::limit($response->body(), 65000, '')
                        : 'No response from provider.',
                    'sent_at' => $successful ? now() : null,
                ];
            }
        }

        return $results;
    }
}

if (! function_exists('stylebite_firebase_message_payload')) {
    /**
     * The FCM v1 "message" object for one device token. Shared by the single
     * and batched senders so both stay byte-identical on the wire.
     */
    function stylebite_firebase_message_payload(
        string $pushToken,
        ?string $title,
        ?string $body,
        array $data = [],
        ?string $image = null
    ): array {
        $notificationPayload = array_filter([
            'title' => $title,
            'body' => $body,
            'image' => $image,
        ], fn ($value) => $value !== null && $value !== '');

        $dataPayload = collect($data)
            ->filter(fn ($value) => $value !== null)
            ->map(fn ($value) => (string) $value)
            ->all();

        $hasImage = is_string($image) && $image !== '';

        $apns = [
            'headers' => [
                'apns-priority' => '10',
            ],
            'payload' => [
                'aps' => [
                    'sound' => 'default',
                ],
            ],
        ];

        // fcm_options is only ever sent when it has something in it. An empty PHP
        // array encodes as a JSON list, and FCM rejects the whole message with
        // "Unknown name \"fcm_options\" ... Proto field is not repeating, cannot
        // start list" — a 400 before delivery is even attempted. Because the key
        // only ever held the image, that broke every push without one: almost all
        // of them. The outer array_filter below cannot catch it, as it only
        // reaches the top level and this sits a layer deeper.
        if ($hasImage) {
            $apns['fcm_options'] = ['image' => $image];
        }

        return array_filter([
            'token' => $pushToken,
            'notification' => $notificationPayload,
            'data' => $dataPayload,
            'android' => [
                'priority' => 'high',
                'notification' => array_filter([
                    'image' => $hasImage ? $image : null,
                    'sound' => 'default',
                ], fn ($value) => $value !== null && $value !== ''),
            ],
            'apns' => $apns,
        ], fn ($value) => ! (is_array($value) && $value === []));
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

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->post(rtrim((string) config('services.firebase.messaging_base_url'), '/').'/'.$projectId.'/messages:send', [
                'message' => stylebite_firebase_message_payload($pushToken, $title, $body, $data, $image),
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

if (! function_exists('stylebite_broadcast')) {
    /**
     * Broadcast a realtime event without ever letting the transport break the
     * request that produced it. Pusher is a third party over the network: if it
     * is slow or down, the message is still saved, the HTTP response is still
     * correct, and the client recovers the missed event through
     * GET /chats/{id}/sync. Realtime is an accelerator here, not the source of
     * truth.
     */
    function stylebite_broadcast(object $event): void
    {
        if (config('broadcasting.default') === 'null') {
            return;
        }

        try {
            broadcast($event);
        } catch (Throwable $exception) {
            Log::warning('Chat broadcast failed.', [
                'event' => $event::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}

if (! function_exists('stylebite_push_token_is_dead')) {
    /**
     * Does this FCM error mean the token itself is finished?
     *
     * Only two responses justify deleting a device row. UNREGISTERED means the app
     * was uninstalled or the token was replaced; a token-scoped INVALID_ARGUMENT
     * means the string is not an FCM token at all.
     *
     * INVALID_ARGUMENT on its own is NOT enough — FCM returns it for a malformed
     * image URL or payload too, and treating that as a dead token would
     * unregister a perfectly good handset because an admin typed a bad image URL.
     * Hence the check that the violation is specifically about message.token.
     */
    function stylebite_push_token_is_dead(?string $providerResponse): bool
    {
        if ($providerResponse === null || trim($providerResponse) === '') {
            return false;
        }

        $decoded = json_decode($providerResponse, true);

        if (! is_array($decoded)) {
            return false;
        }

        $error = $decoded['error'] ?? [];
        $status = (string) ($error['status'] ?? '');
        $details = $error['details'] ?? [];

        $errorCodes = [];
        $violationFields = [];

        foreach (is_array($details) ? $details : [] as $detail) {
            if (! is_array($detail)) {
                continue;
            }

            if (isset($detail['errorCode'])) {
                $errorCodes[] = (string) $detail['errorCode'];
            }

            foreach ($detail['fieldViolations'] ?? [] as $violation) {
                if (is_array($violation) && isset($violation['field'])) {
                    $violationFields[] = (string) $violation['field'];
                }
            }
        }

        if (in_array('UNREGISTERED', $errorCodes, true) || $status === 'UNREGISTERED') {
            return true;
        }

        return ($status === 'INVALID_ARGUMENT' || in_array('INVALID_ARGUMENT', $errorCodes, true))
            && in_array('message.token', $violationFields, true);
    }
}

if (! function_exists('stylebite_compact_number')) {
    /**
     * 999 → "999", 1500 → "1.5k", 2300000 → "2.3M". For badges and tiles where a
     * full number would not fit; never for anything that gets added up.
     */
    function stylebite_compact_number(int|float|null $value): string
    {
        $value = (int) ($value ?? 0);

        if ($value < 1000) {
            return (string) $value;
        }

        [$divisor, $suffix] = $value < 1_000_000 ? [1000, 'k'] : [1_000_000, 'M'];

        $short = $value / $divisor;
        $formatted = $short < 10 ? number_format($short, 1) : number_format($short, 0);

        return rtrim(rtrim($formatted, '0'), '.').$suffix;
    }
}

if (! function_exists('stylebite_parse_admin_datetime')) {
    /**
     * Read a datetime the admin panel collected and return the instant it means, in UTC.
     *
     * An <input type="datetime-local"> posts a bare wall clock — "2026-09-02T23:55" —
     * with no offset at all. Carbon::parse() then reads it in the app timezone, which
     * is UTC, so 11:55 PM picked in Karachi was stored as 23:55 UTC: the contest really
     * started five hours after the admin intended. The admin is reading the clock on
     * the wall, so that is the timezone the value has to be resolved against.
     *
     * Anything that arrives with its own offset (an API caller, a seeder) already names
     * an instant and is passed through untouched.
     */
    function stylebite_parse_admin_datetime(mixed $value): ?\Carbon\CarbonImmutable
    {
        if ($value instanceof \DateTimeInterface) {
            return \Carbon\CarbonImmutable::instance($value)->utc();
        }

        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $carriesOffset = (bool) preg_match('/(Z|[+-]\d{2}:?\d{2})$/i', $value);

        try {
            $parsed = $carriesOffset
                ? \Carbon\CarbonImmutable::parse($value)
                : \Carbon\CarbonImmutable::parse($value, stylebite_reporting_timezone());
        } catch (\Throwable) {
            // Let validation report a malformed value rather than throwing here.
            return null;
        }

        return $parsed->utc();
    }
}

if (! function_exists('stylebite_admin_datetime_input')) {
    /**
     * Render a stored UTC datetime as the wall clock an admin expects to see in a
     * <input type="datetime-local">. The inverse of stylebite_parse_admin_datetime().
     *
     * Strings pass through: those come from old() on a failed submit and are already
     * the admin's own wall clock, so converting them again would shift the value.
     */
    function stylebite_admin_datetime_input(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (! $value instanceof \DateTimeInterface) {
            return '';
        }

        return \Carbon\CarbonImmutable::instance($value)
            ->setTimezone(stylebite_reporting_timezone())
            ->format('Y-m-d\TH:i');
    }
}

if (! function_exists('stylebite_normalize_admin_datetimes')) {
    /**
     * Rewrite the named request fields from admin wall clock to UTC, in place.
     *
     * Called before validate() rather than after, because rules like `after:now` and
     * `after_or_equal:start_at` compare against UTC too: a two-hour suspension picked
     * in Karachi looked like it was three hours in the past and was rejected outright.
     */
    function stylebite_normalize_admin_datetimes(\Illuminate\Http\Request $request, string ...$fields): void
    {
        foreach ($fields as $field) {
            if (! $request->has($field)) {
                continue;
            }

            $parsed = stylebite_parse_admin_datetime($request->input($field));

            // A value we could not read is left alone so validation can reject it.
            if ($parsed === null && trim((string) $request->input($field)) !== '') {
                continue;
            }

            $request->merge([$field => $parsed?->toDateTimeString()]);
        }
    }
}
