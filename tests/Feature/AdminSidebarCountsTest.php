<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Report;
use App\Models\User;
use App\Support\AdminSidebarCounts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The sidebar badges shipped as hardcoded placeholders (7, 2, 4, 2, 6 ...) and
 * nobody noticed for months because every value looked plausible. These pin
 * them to the database.
 */
class AdminSidebarCountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_badges_reflect_real_record_counts(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $reporter = User::factory()->create();
        User::factory()->create();

        foreach ([1, 2] as $targetId) {
            Report::query()->create([
                'reporter_user_id' => $reporter->id,
                'target_type' => 'post',
                'target_id' => $targetId,
                'reason' => 'spam',
                'status' => 'open',
            ]);
        }

        Notification::query()->create([
            'recipient_user_id' => $reporter->id,
            'type' => 'system',
            'entity_type' => 'system',
            'title' => 'Welcome',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();

        $response->assertSeeInOrder(['<span>Users</span>', '<span class="nav-count ms-auto">3</span>'], false);
        $response->assertSeeInOrder(['<span>Moderation</span>', '<span class="nav-count ms-auto">2</span>'], false);
        $response->assertSeeInOrder(['<span>Notifications</span>', '<span class="nav-count ms-auto">1</span>'], false);
        $response->assertSeeInOrder(['<span>Posts</span>', '<span class="nav-count ms-auto">0</span>'], false);

        // The old placeholders must be gone for good.
        $response->assertDontSee('<span class="nav-count ms-auto">7</span>', false);
    }

    public function test_counts_are_cached_and_refresh_after_forget(): void
    {
        User::factory()->create();

        $this->assertSame(1, AdminSidebarCounts::all()['users']);

        User::factory()->create();

        // Still the cached value — the badges are orientation, not a live feed.
        $this->assertSame(1, AdminSidebarCounts::all()['users']);

        AdminSidebarCounts::forget();

        $this->assertSame(2, AdminSidebarCounts::all()['users']);
    }

    public function test_compact_number_formatting(): void
    {
        $this->assertSame('0', stylebite_compact_number(null));
        $this->assertSame('999', stylebite_compact_number(999));
        $this->assertSame('1k', stylebite_compact_number(1000));
        $this->assertSame('1.5k', stylebite_compact_number(1500));
        $this->assertSame('12k', stylebite_compact_number(12_345));
        $this->assertSame('999k', stylebite_compact_number(999_499));
        $this->assertSame('1M', stylebite_compact_number(1_000_000));
        $this->assertSame('2.3M', stylebite_compact_number(2_300_000));
    }
}
