<?php

namespace App\Support;

use App\Models\Contest;
use App\Models\ContestParticipant;
use App\Models\ContestPlace;
use App\Models\ContestSubmission;
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
        $participantStats = self::participantResultStats($contest);

        self::afterResponse(function () use ($contestId, $title, $image, $actionUrl, $winners, $participantStats): void {
            if (self::alreadySent($contestId, $actionUrl)) {
                return;
            }

            self::eachActiveUser(function (int $userId) use ($contestId, $title, $image, $actionUrl, $winners, $participantStats): void {
                if (isset($winners[$userId])) {
                    [$headline, $body] = self::winnerCopy((int) $winners[$userId], $title);
                } elseif (isset($participantStats[$userId])) {
                    [$headline, $body] = self::nonWinnerCopy($title, $participantStats[$userId]);
                } else {
                    $headline = 'See who won';
                    $body = 'See who won '.$title.'.';
                }

                stylebite_notify_user(
                    $userId,
                    null,
                    'contest',
                    'contest',
                    $contestId,
                    $headline,
                    Str::limit($body, 500, ''),
                    $actionUrl,
                    $image
                );
            });
        });

        return true;
    }

    /**
     * Best live submission stats per approved participant (for better-luck copy).
     *
     * @return array<int, array{score:float,avg_rating:float,likes:int,ratings:int,rank:?int}>
     */
    private static function participantResultStats(Contest $contest): array
    {
        $participantIds = ContestParticipant::query()
            ->where('contest_id', $contest->id)
            ->where('status', 'approved')
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($participantIds === []) {
            return [];
        }

        $submissions = ContestSubmission::query()
            ->with(['votes.voter:id,status', 'post:id,like_count'])
            ->where('contest_id', $contest->id)
            ->whereIn('user_id', $participantIds)
            ->whereNotIn('submission_status', ['rejected', 'disqualified'])
            ->orderByDesc('final_score')
            ->orderBy('id')
            ->get();

        $stats = [];
        foreach ($submissions as $submission) {
            $userId = (int) $submission->user_id;
            if ($userId <= 0 || isset($stats[$userId])) {
                continue;
            }

            $activeVotes = $submission->votes->filter(
                fn ($vote) => $vote->voter && $vote->voter->status === 'active'
            );
            $ratings = $activeVotes->count();
            $avg = $ratings === 0 ? 0.0 : round((float) $activeVotes->avg('score'), 2);
            $likes = max(0, (int) ($submission->post?->like_count ?? 0));

            $stats[$userId] = [
                'score' => round((float) ($submission->final_score ?? 0), 2),
                'avg_rating' => $avg,
                'likes' => $likes,
                'ratings' => $ratings,
                'rank' => $submission->rank_position !== null ? (int) $submission->rank_position : null,
            ];
        }

        // Approved joiners with no live submission still get a softer notice.
        foreach ($participantIds as $userId) {
            if (! isset($stats[$userId])) {
                $stats[$userId] = [
                    'score' => 0.0,
                    'avg_rating' => 0.0,
                    'likes' => 0,
                    'ratings' => 0,
                    'rank' => null,
                ];
            }
        }

        return $stats;
    }

    /**
     * @param  array{score:float,avg_rating:float,likes:int,ratings:int,rank:?int}  $stats
     * @return array{0:string,1:string}
     */
    private static function nonWinnerCopy(string $title, array $stats): array
    {
        $rankBit = ! empty($stats['rank'])
            ? ' You finished #'.(int) $stats['rank'].'.'
            : '';

        $body = sprintf(
            'Thanks for entering %s — this round went to someone else.%s Your entry landed at %.1f avg rating across %d voters, %d likes, and a final score of %.2f. Keep showing up; the next contest is wide open.',
            $title,
            $rankBit,
            (float) $stats['avg_rating'],
            (int) $stats['ratings'],
            (int) $stats['likes'],
            (float) $stats['score'],
        );

        return ['Better luck next time', $body];
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
