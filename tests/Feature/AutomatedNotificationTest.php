<?php

namespace Tests\Feature;

use App\Models\AppConfig;
use App\Models\Contest;
use App\Models\ContestParticipant;
use App\Models\Notification;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AutomatedNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function userWithStreak(int $days, ?string $lastStreakDay, array $userAttributes = []): User
    {
        $user = User::factory()->create($userAttributes + ['status' => 'active']);

        Profile::create([
            'user_id' => $user->id,
            'display_name' => $user->username,
            'current_streak_days' => $days,
            'last_streak_day' => $lastStreakDay,
        ]);

        return $user;
    }

    private function yesterday(): string
    {
        return now(stylebite_reporting_timezone())->subDay()->toDateString();
    }

    private function today(): string
    {
        return now(stylebite_reporting_timezone())->toDateString();
    }

    public function test_streak_reminder_goes_only_to_users_whose_streak_lapses_tonight(): void
    {
        $atRisk = $this->userWithStreak(12, $this->yesterday());
        $alreadyPostedToday = $this->userWithStreak(5, $this->today());
        $noStreak = $this->userWithStreak(0, $this->yesterday());
        $longGone = $this->userWithStreak(3, now(stylebite_reporting_timezone())->subDays(9)->toDateString());

        $this->artisan('stylebite:send-streak-reminders')
            ->expectsOutputToContain('Sent 1 streak reminder')
            ->assertSuccessful();

        $recipients = Notification::pluck('recipient_user_id')->map(fn ($id) => (int) $id);

        $this->assertTrue($recipients->contains($atRisk->id));
        $this->assertFalse($recipients->contains($alreadyPostedToday->id));
        $this->assertFalse($recipients->contains($noStreak->id));
        $this->assertFalse($recipients->contains($longGone->id));

        $this->assertSame('Your 12-day streak ends tonight', Notification::sole()->title);
    }

    public function test_streak_reminder_is_not_repeated_when_the_command_runs_again_the_same_day(): void
    {
        $this->userWithStreak(7, $this->yesterday());

        $this->artisan('stylebite:send-streak-reminders')->assertSuccessful();
        $this->artisan('stylebite:send-streak-reminders')
            ->expectsOutputToContain('No streaks at risk')
            ->assertSuccessful();

        // Hourly cron must not spam: exactly one notification for the day.
        $this->assertSame(1, Notification::count());
        $this->assertDatabaseCount('automated_notification_sends', 1);
    }

    public function test_streak_reminder_skips_banned_and_suspended_users(): void
    {
        $this->userWithStreak(4, $this->yesterday(), ['status' => 'banned']);
        $this->userWithStreak(6, $this->yesterday(), ['status' => 'suspended']);

        $this->artisan('stylebite:send-streak-reminders')
            ->expectsOutputToContain('No streaks at risk')
            ->assertSuccessful();

        $this->assertSame(0, Notification::count());
    }

    public function test_streak_reminder_can_be_disabled_from_settings(): void
    {
        AppConfig::create([
            'config_key' => 'streaks.reminder_enabled',
            'config_value' => '0',
            'value_type' => 'boolean',
        ]);
        Cache::forget('stylebite_app_configs');

        $this->userWithStreak(9, $this->yesterday());

        $this->artisan('stylebite:send-streak-reminders')
            ->expectsOutputToContain('disabled')
            ->assertSuccessful();

        $this->assertSame(0, Notification::count());
    }

    public function test_streak_reminder_reports_when_it_hits_the_limit_instead_of_truncating_silently(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->userWithStreak(5, $this->yesterday());
        }

        $this->artisan('stylebite:send-streak-reminders --limit=2')
            ->expectsOutputToContain('Sent 2 streak reminder')
            ->expectsOutputToContain('Hit the 2 reminder cap')
            ->assertSuccessful();

        $this->assertSame(2, Notification::count());

        // The rest are picked up by the next run.
        $this->artisan('stylebite:send-streak-reminders --limit=2')->assertSuccessful();
        $this->assertSame(3, Notification::count());
    }

    private function contest(array $attributes = []): Contest
    {
        return Contest::create(array_merge([
            'slug' => 'contest-'.uniqid(),
            'title' => 'Winter Street Style',
            'category' => 'admin',
            'contest_type' => 'group',
            'status' => 'active',
            'visibility' => 'public',
            'voting_type' => 'community',
            'start_at' => now()->subDays(3),
            'end_at' => now()->addHours(6),
        ], $attributes));
    }

    private function participant(Contest $contest, string $status = 'approved'): User
    {
        $user = User::factory()->create(['status' => 'active']);

        ContestParticipant::create([
            'contest_id' => $contest->id,
            'user_id' => $user->id,
            'participant_role' => 'creator',
            'status' => $status,
            'joined_at' => now(),
        ]);

        return $user;
    }

    public function test_contest_reminder_notifies_active_participants_of_a_closing_contest(): void
    {
        $contest = $this->contest();

        $approved = $this->participant($contest, 'approved');
        $joined = $this->participant($contest, 'joined');
        $withdrawn = $this->participant($contest, 'withdrawn');

        $this->artisan('stylebite:send-contest-ending-reminders')
            ->expectsOutputToContain('Sent 2 contest reminder')
            ->assertSuccessful();

        $recipients = Notification::pluck('recipient_user_id')->map(fn ($id) => (int) $id);

        $this->assertTrue($recipients->contains($approved->id));
        $this->assertTrue($recipients->contains($joined->id));
        $this->assertFalse($recipients->contains($withdrawn->id));

        $notification = Notification::where('recipient_user_id', $approved->id)->sole();
        $this->assertSame('contest', $notification->type);
        $this->assertSame('contest', $notification->entity_type);
        $this->assertSame($contest->id, (int) $notification->entity_id);
        $this->assertStringContainsString('closes soon', $notification->title);
    }

    public function test_contest_reminder_ignores_contests_outside_the_window(): void
    {
        $this->participant($this->contest(['end_at' => now()->addDays(9)]));
        $this->participant($this->contest(['end_at' => now()->subHour()]));
        $this->participant($this->contest(['status' => 'draft', 'end_at' => now()->addHour()]));

        $this->artisan('stylebite:send-contest-ending-reminders')
            ->expectsOutputToContain('No active contests ending')
            ->assertSuccessful();

        $this->assertSame(0, Notification::count());
    }

    public function test_contest_reminder_is_sent_once_per_contest_even_across_hourly_runs(): void
    {
        $contest = $this->contest();
        $this->participant($contest);

        $this->artisan('stylebite:send-contest-ending-reminders')->assertSuccessful();
        $this->artisan('stylebite:send-contest-ending-reminders')->assertSuccessful();

        $this->assertSame(1, Notification::count());
        $this->assertDatabaseCount('automated_notification_sends', 1);
    }

    public function test_contest_reminder_window_is_configurable(): void
    {
        AppConfig::create([
            'config_key' => 'contests.ending_soon_hours',
            'config_value' => '2',
            'value_type' => 'number',
        ]);
        Cache::forget('stylebite_app_configs');

        // 6 hours out — outside a 2-hour window.
        $this->participant($this->contest(['end_at' => now()->addHours(6)]));

        $this->artisan('stylebite:send-contest-ending-reminders')
            ->expectsOutputToContain('No active contests ending within 2 hour')
            ->assertSuccessful();

        $this->assertSame(0, Notification::count());
    }

    public function test_contest_reminder_can_be_disabled_from_settings(): void
    {
        AppConfig::create([
            'config_key' => 'contests.ending_soon_enabled',
            'config_value' => '0',
            'value_type' => 'boolean',
        ]);
        Cache::forget('stylebite_app_configs');

        $this->participant($this->contest());

        $this->artisan('stylebite:send-contest-ending-reminders')
            ->expectsOutputToContain('disabled')
            ->assertSuccessful();

        $this->assertSame(0, Notification::count());
    }
}
