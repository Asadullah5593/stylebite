<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReelsPageSizeTest extends TestCase
{
    use RefreshDatabase;

    public function test_reels_page_size_above_fifteen_is_clamped(): void
    {
        [$user, $token] = $this->authenticatedUser();

        $response = $this->withHeaders($this->headers($token))
            ->getJson('/api/reels?type=outfit&page=1&per_page=24');

        $response->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('pagination.per_page', 15)
            ->assertJsonPath('pagination.current_page', 1);

        $this->assertNotSame($user->id, 0);
    }

    public function test_reels_page_size_of_ten_is_preserved(): void
    {
        [, $token] = $this->authenticatedUser();

        $this->withHeaders($this->headers($token))
            ->getJson('/api/reels?type=outfit&page=1&per_page=10')
            ->assertOk()
            ->assertJsonPath('pagination.per_page', 10);
    }

    public function test_reels_page_size_of_zero_stays_invalid(): void
    {
        [, $token] = $this->authenticatedUser();

        $this->withHeaders($this->headers($token))
            ->getJson('/api/reels?type=outfit&page=1&per_page=0')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_home_feed_still_rejects_a_page_size_above_fifteen(): void
    {
        [, $token] = $this->authenticatedUser();

        $this->withHeaders($this->headers($token))
            ->getJson('/api/feed/home?type=outfit&page=1&per_page=24')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    private function authenticatedUser(): array
    {
        $user = User::factory()->create();
        Profile::query()->create([
            'user_id' => $user->id,
            'display_name' => $user->username,
        ]);
        $token = str_repeat('d', 80);
        UserSession::query()->create([
            'user_id' => $user->id,
            'session_token_hash' => hash('sha256', $token),
            'platform' => 'android',
            'last_seen_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        return [$user, $token];
    }

    private function headers(string $token): array
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$token,
        ];
    }
}
