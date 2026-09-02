<?php

namespace Tests\Feature;

use App\Models\Contest;
use App\Models\User;
use App\Services\ContestStatusResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContestRequirementsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'status' => 'active']);
    }

    /** @return array<string, mixed> */
    private function formPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Winter Style Off',
            'contest_type' => 'Seasonal Showdown',
            'voting_type' => 'Public Applause',
            'status' => 'upcoming',
            'entry_fee' => 0,
            'prize_pool' => 0,
        ], $overrides);
    }

    public function test_contest_type_and_voting_type_accept_free_text(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.contests.store'), $this->formPayload())
            ->assertRedirect();

        $contest = Contest::query()->where('title', 'Winter Style Off')->firstOrFail();

        $this->assertSame('Seasonal Showdown', $contest->contest_type);
        $this->assertSame('Public Applause', $contest->voting_type);

        // Behaviour is unaffected by whatever the admin typed.
        $this->assertSame('city', $contest->contest_behavior_type);
    }

    public function test_visibility_is_gone_from_the_schema_and_the_api(): void
    {
        $this->assertFalse(
            Schema::hasColumn('contests', 'visibility'),
            'visibility column should have been dropped.'
        );
    }

    public function test_status_follows_the_dates(): void
    {
        $resolver = app(ContestStatusResolver::class);

        $this->assertSame('upcoming', $resolver->forDates(now()->addDay(), now()->addDays(3)));
        $this->assertSame('active', $resolver->forDates(now()->subHour(), now()->addDays(3)));
        $this->assertSame('completed', $resolver->forDates(now()->subDays(3), now()->subHour()));
    }

    public function test_the_scheduled_command_advances_stored_statuses(): void
    {
        $starting = Contest::create([
            'slug' => 'a', 'title' => 'A', 'category' => 'admin',
            'contest_type' => 'X', 'contest_behavior_type' => 'city', 'status' => 'upcoming',
            'start_at' => now()->subHour(), 'end_at' => now()->addDay(),
        ]);
        $ending = Contest::create([
            'slug' => 'b', 'title' => 'B', 'category' => 'admin',
            'contest_type' => 'X', 'contest_behavior_type' => 'city', 'status' => 'active',
            'start_at' => now()->subDays(2), 'end_at' => now()->subHour(),
        ]);
        $cancelled = Contest::create([
            'slug' => 'c', 'title' => 'C', 'category' => 'admin',
            'contest_type' => 'X', 'contest_behavior_type' => 'city', 'status' => 'cancelled',
            'start_at' => now()->subDays(2), 'end_at' => now()->subHour(),
        ]);

        $this->artisan('stylebite:refresh-contest-statuses')->assertSuccessful();

        $this->assertSame('active', $starting->fresh()->status);
        $this->assertSame('completed', $ending->fresh()->status);
        // An admin decision is not undone by the clock.
        $this->assertSame('cancelled', $cancelled->fresh()->status);
    }

    public function test_only_one_contest_can_be_featured_at_a_time(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.contests.store'),
            $this->formPayload(['title' => 'First', 'is_featured' => '1']))->assertRedirect();
        $first = Contest::where('title', 'First')->firstOrFail();
        $this->assertTrue($first->fresh()->is_featured);

        $this->actingAs($admin)->post(route('admin.contests.store'),
            $this->formPayload(['title' => 'Second', 'is_featured' => '1']))->assertRedirect();
        $second = Contest::where('title', 'Second')->firstOrFail();

        $this->assertTrue($second->fresh()->is_featured, 'Newly featured contest should be featured.');
        $this->assertFalse($first->fresh()->is_featured, 'Previously featured contest should be un-featured.');
        $this->assertSame(1, Contest::where('is_featured', true)->count());
    }

    public function test_unchecking_featured_clears_it(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.contests.store'),
            $this->formPayload(['title' => 'Solo', 'is_featured' => '1']))->assertRedirect();
        $contest = Contest::where('title', 'Solo')->firstOrFail();
        $this->assertTrue($contest->fresh()->is_featured);

        $this->actingAs($admin)->patch(route('admin.contests.update', $contest),
            $this->formPayload(['title' => 'Solo', 'is_featured' => '0']))->assertRedirect();

        $this->assertFalse($contest->fresh()->is_featured);
    }

    public function test_one_upload_fills_both_banner_and_cover(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.contests.store'), $this->formPayload([
                'title' => 'Imaged',
                'contest_image' => UploadedFile::fake()->image('art.jpg', 1400, 1400),
            ]))
            ->assertRedirect();

        $contest = Contest::where('title', 'Imaged')->firstOrFail();

        $this->assertNotNull($contest->cover_image_url);
        $this->assertSame($contest->cover_image_url, $contest->banner_image_url);
    }

    public function test_an_image_below_the_minimum_size_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.contests.store'), $this->formPayload([
                'title' => 'Tiny',
                'contest_image' => UploadedFile::fake()->image('small.jpg', 400, 400),
            ]))
            ->assertSessionHasErrors('contest_image');

        $this->assertDatabaseMissing('contests', ['title' => 'Tiny']);
    }
}
