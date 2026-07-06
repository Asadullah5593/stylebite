<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSearchApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_search_matches_public_profiles_by_username_name_and_city_only(): void
    {
        [$viewer, $token] = $this->authenticatedUser();

        $publicUser = User::factory()->create([
            'username' => 'karachi_style',
            'full_name' => 'Ayesha Khan',
        ]);

        Profile::create([
            'user_id' => $publicUser->id,
            'display_name' => 'Ayesha Styles',
            'bio' => 'Fashion creator from Karachi',
            'city' => 'Karachi',
            'country' => 'Pakistan',
            'visibility' => 'public',
            'is_private' => false,
        ]);

        $privateUser = User::factory()->create([
            'username' => 'karachi_private',
            'full_name' => 'Hidden User',
        ]);

        Profile::create([
            'user_id' => $privateUser->id,
            'display_name' => 'Hidden Styles',
            'bio' => 'Private profile from Karachi',
            'city' => 'Karachi',
            'country' => 'Pakistan',
            'visibility' => 'private',
            'is_private' => true,
        ]);

        $response = $this->withHeaders($this->headers($token))
            ->getJson('/api/users/search?query=Karachi');

        $response
            ->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('pagination.per_page', 10)
            ->assertJsonPath('users.0.username', 'karachi_style');

        $usernames = collect($response->json('users'))->pluck('username');

        $this->assertTrue($usernames->contains('karachi_style'));
        $this->assertFalse($usernames->contains('karachi_private'));
    }

    public function test_user_search_without_query_returns_all_public_profiles_only(): void
    {
        [$viewer, $token] = $this->authenticatedUser();

        $publicUserOne = User::factory()->create(['username' => 'public_alpha']);
        Profile::create([
            'user_id' => $publicUserOne->id,
            'display_name' => 'Public Alpha',
            'visibility' => 'public',
            'is_private' => false,
        ]);

        $publicUserTwo = User::factory()->create(['username' => 'public_beta']);
        Profile::create([
            'user_id' => $publicUserTwo->id,
            'display_name' => 'Public Beta',
            'visibility' => 'public',
            'is_private' => false,
        ]);

        $privateUser = User::factory()->create(['username' => 'private_gamma']);
        Profile::create([
            'user_id' => $privateUser->id,
            'display_name' => 'Private Gamma',
            'visibility' => 'private',
            'is_private' => true,
        ]);

        $response = $this->withHeaders($this->headers($token))
            ->getJson('/api/users/search');

        $response
            ->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('pagination.per_page', 10);

        $usernames = collect($response->json('users'))->pluck('username');

        $this->assertTrue($usernames->contains('public_alpha'));
        $this->assertTrue($usernames->contains('public_beta'));
        $this->assertFalse($usernames->contains('private_gamma'));
    }

    private function authenticatedUser(): array
    {
        $user = User::factory()->create([
            'username' => 'search_viewer',
        ]);
        $token = str_repeat('s', 80);

        UserSession::create([
            'user_id' => $user->id,
            'session_token_hash' => hash('sha256', $token),
            'platform' => 'web',
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
