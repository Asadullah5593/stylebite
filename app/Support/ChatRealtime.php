<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatRealtime
{
    /**
     * @param  array<int, string>  $channels
     * @param  array<string, mixed>  $data
     */
    public static function trigger(array $channels, string $event, array $data): void
    {
        $channels = array_values(array_filter($channels, fn ($channel) => is_string($channel) && $channel !== ''));
        if ($channels === [] || $event === '') {
            return;
        }

        $appId = (string) (config('broadcasting.connections.pusher.app_id') ?? '');
        $key = (string) (config('broadcasting.connections.pusher.key') ?? '');
        $secret = (string) (config('broadcasting.connections.pusher.secret') ?? '');
        if ($appId === '' || $key === '' || $secret === '') {
            return;
        }

        try {
            $body = json_encode([
                'name' => $event,
                'channels' => $channels,
                'data' => json_encode($data, JSON_THROW_ON_ERROR),
            ], JSON_THROW_ON_ERROR);

            $path = '/apps/'.$appId.'/events';
            $params = [
                'auth_key' => $key,
                'auth_timestamp' => (string) time(),
                'auth_version' => '1.0',
                'body_md5' => md5($body),
            ];
            ksort($params);
            $signedQuery = http_build_query($params);
            $params['auth_signature'] = hash_hmac(
                'sha256',
                "POST\n{$path}\n{$signedQuery}",
                $secret,
            );

            $host = (string) (config('broadcasting.connections.pusher.options.host') ?? '');
            $cluster = (string) (config('broadcasting.connections.pusher.options.cluster') ?? '');
            if ($host === '') {
                $host = $cluster !== '' ? 'api-'.$cluster.'.pusher.com' : 'api.pusher.com';
            }

            Http::timeout(2)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->withBody($body, 'application/json')
                ->post('https://'.$host.$path.'?'.http_build_query($params));
        } catch (\Throwable $exception) {
            Log::warning('Chat realtime event was not published.');
            report($exception);
        }
    }
}
