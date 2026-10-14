<?php

namespace App\Support;

use App\Models\Contest;
use App\Models\ContestPlace;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Str;

class ContestNotifications
{
    public static function announceCreated(Contest $contest): bool
    {
        if (! in_array($contest->status, ['upcoming', 'active'], true)) {
            return false;
        }

        $actionUrl = self::contestUrl($contest->id);
        if (self::alreadySent($contest->id, $actionUrl)) {
            return false;
        }

        $contestId = (int) $contest->id;
        $title = self::contestName($contest);
        $image = $contest->cover_image_url ?: $contest->banner_image_url;

        self::afterResponse(function () use ($contestId, $title, $image, $actionUrl): void {
            if (self::alreadySent($contestId, $actionUrl)) {
                return;
            }

            self::eachActiveUser(function (int $userId) use ($contestId, $title, $image, $actionUrl): void {
                stylebite_notify_user(
                    $userId,
                    null,
                    'contest',
                    'contest',
                    $contestId,
                    'New contest just dropped',
                    Str::limit($title.' is live. Pull up and enter.', 500, ''),
                    $actionUrl,
                    $image
                );
            });
        });

        return true;
    }

    public static function announceWinners(Contest $contest): bool
    {
        $winners = self::winnerPlaces($contest);
        if ($winners === []) {
            return false;
        }

        $actionUrl = self::contestUrl($contest->id).'?results='.self::signature($winners);
        if (self::alreadySent($contest->id, $actionUrl)) {
            return false;
        }

        $contestId = (int) $contest->id;
        $title = self::contestName($contest);
        $image = $contest->cover_image_url ?: $contest->banner_image_url;

        self::afterResponse(function () use ($contestId, $title, $image, $actionUrl, $winners): void {
            if (self::alreadySent($contestId, $actionUrl)) {
                return;
            }

            self::eachActiveUser(function (int $userId) use ($contestId, $title, $image, $actionUrl, $winners): void {
                [$headline, $body] = isset($winners[$userId])
                    ? self::winnerCopy((int) $winners[$userId], $title)
                    : ['See who won', 'See who won '.$title.'.'];

                stylebite_notify_user(
                    $userId,
                    null,
                    'contest',
                    'contest',
                    $contestId,
                    $headline,
                    $body,
                    $actionUrl,
                    $image
                );
            });
        });

        return true;
    }

    private static function winnerPlaces(Contest $contest): array
    {
        $places = $contest->places()
            ->whereNotNull('winner_user_id')
            ->orderBy('place')
            ->get(['place', 'winner_user_id']);

        $winners = [];
        foreach ($places as $place) {
            $userId = (int) $place->winner_user_id;
            if ($userId > 0 && ! isset($winners[$userId])) {
                $winners[$userId] = (int) $place->place;
            }
        }

        if ($winners === [] && $contest->winner_user_id) {
            $winners[(int) $contest->winner_user_id] = 1;
        }

        return $winners;
    }

    private static function winnerCopy(int $place, string $title): array
    {
        $body = match ($place) {
            1 => 'No crumbs left. You won '.$title.'.',
            2 => '2nd place in '.$title.'. You understood the assignment.',
            3 => '3rd in '.$title.'. The fit was never in question.',
            default => 'You placed '.ContestPlace::ordinal($place).' in '.$title.'.',
        };

        $headline = match ($place) {
            1 => 'You ate',
            2 => 'You understood the assignment',
            3 => 'Podium secured',
            default => 'You popped off',
        };

        return [$headline, Str::limit($body, 500, '')];
    }

    private static function eachActiveUser(callable $callback): void
    {
        set_time_limit(0);

        User::query()
            ->where('status', 'active')
            ->orderBy('id')
            ->select('id')
            ->chunkById(100, function ($users) use ($callback): void {
                foreach ($users as $user) {
                    try {
                        $callback((int) $user->id);
                    } catch (\Throwable $exception) {
                        report($exception);
                    }
                }
            });
    }

    private static function alreadySent(int $contestId, string $actionUrl): bool
    {
        return Notification::query()
            ->where('type', 'contest')
            ->where('entity_type', 'contest')
            ->where('entity_id', $contestId)
            ->where('action_url', $actionUrl)
            ->exists();
    }

    private static function contestUrl(int $contestId): string
    {
        return '/contests/'.$contestId;
    }

    private static function contestName(Contest $contest): string
    {
        $title = trim((string) $contest->title);

        return $title !== '' ? $title : 'this contest';
    }

    private static function signature(array $winners): string
    {
        $parts = [];
        foreach ($winners as $userId => $place) {
            $parts[] = $place.':'.$userId;
        }
        sort($parts);

        return implode(',', $parts);
    }

    private static function afterResponse(callable $callback): void
    {
        app()->terminating($callback);
    }
}
