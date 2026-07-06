<?php

namespace Tests\Feature;

use App\Models\Memory;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class MemoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_memory_with_multiple_images(): void
    {
        [$user, $token] = $this->authenticatedUser();

        $response = $this->withHeaders($this->headers($token))->post('/api/memories', [
            'title' => 'Trip to Hunza',
            'short_title' => 'Hunza Trip',
            'short_description' => 'A beautiful mountain trip.',
            'description' => 'We visited Hunza and enjoyed the weather.',
            'memory_date' => '2026-04-12',
            'location' => 'Hunza Valley',
            'city' => 'Hunza',
            'country' => 'Pakistan',
            'lat' => '36.3167',
            'lng' => '74.6500',
            'rating' => '4.5',
            'comments' => ['Amazing experience', 'Will visit again'],
            'images' => [
                UploadedFile::fake()->image('hunza-1.jpg'),
                UploadedFile::fake()->image('hunza-2.jpg'),
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('memory.title', 'Trip to Hunza')
            ->assertJsonPath('memory.city', 'Hunza')
            ->assertJsonPath('memory.country', 'Pakistan')
            ->assertJsonCount(2, 'memory.images')
            ->assertJsonCount(2, 'memory.comments');

        $this->assertDatabaseHas('memories', [
            'user_id' => $user->id,
            'title' => 'Trip to Hunza',
            'short_title' => 'Hunza Trip',
            'location_name' => 'Hunza Valley',
            'city' => 'Hunza',
            'country' => 'Pakistan',
            'comment_count' => 2,
        ]);

        $this->assertDatabaseCount('memory_media', 2);
        $this->assertDatabaseCount('memory_comments', 2);
        $this->assertDatabaseHas('memory_ratings', [
            'user_id' => $user->id,
            'rating_value' => 5,
        ]);
    }

    public function test_authenticated_user_can_list_memories_with_offset_pagination(): void
    {
        [$user, $token] = $this->authenticatedUser();

        Memory::factory()->count(3)->create([
            'user_id' => $user->id,
        ]);

        $response = $this->withHeaders($this->headers($token))
            ->getJson('/api/memories?per_page=2&skip=1');

        $response
            ->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonCount(2, 'memories')
            ->assertJsonPath('pagination.per_page', 2)
            ->assertJsonPath('pagination.skip', 1)
            ->assertJsonPath('pagination.total', 3);
    }

    public function test_authenticated_user_can_update_memory_and_replace_media_selection(): void
    {
        [$user, $token] = $this->authenticatedUser();

        $memory = Memory::create([
            'user_id' => $user->id,
            'title' => 'Old title',
            'memory_date' => '2026-04-01',
            'status' => 'active',
        ]);

        $firstMedia = $memory->media()->create([
            'media_type' => 'image',
            'file_url' => 'https://example.com/old-1.jpg',
            'sort_order' => 0,
        ]);

        $secondMedia = $memory->media()->create([
            'media_type' => 'image',
            'file_url' => 'https://example.com/old-2.jpg',
            'sort_order' => 1,
        ]);

        $response = $this->withHeaders($this->headers($token))->put('/api/memories/'.$memory->id, [
            'title' => 'Updated title',
            'location' => 'Skardu',
            'retain_media_ids' => [$secondMedia->id],
            'images' => [
                UploadedFile::fake()->image('new-photo.jpg'),
            ],
            'comments' => ['Fresh comment'],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('memory.title', 'Updated title')
            ->assertJsonPath('memory.location', 'Skardu')
            ->assertJsonCount(2, 'memory.images')
            ->assertJsonCount(1, 'memory.comments');

        $this->assertDatabaseMissing('memory_media', [
            'id' => $firstMedia->id,
        ]);

        $this->assertDatabaseHas('memory_media', [
            'id' => $secondMedia->id,
        ]);
    }

    public function test_authenticated_user_can_toggle_memory_favorite_status(): void
    {
        [$user, $token] = $this->authenticatedUser();

        $memory = Memory::create([
            'user_id' => $user->id,
            'title' => 'Favorite Toggle Test',
            'memory_date' => '2026-04-01',
            'is_favorite' => false,
        ]);

        $response = $this->withHeaders($this->headers($token))
            ->postJson('/api/memories/'.$memory->id.'/favorite');

        $response
            ->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('is_favorite', true);

        $this->assertTrue($memory->fresh()->is_favorite);

        $this->withHeaders($this->headers($token))
            ->postJson('/api/memories/'.$memory->id.'/favorite')
            ->assertOk()
            ->assertJsonPath('is_favorite', false);

        $this->assertFalse($memory->fresh()->is_favorite);
    }

    public function test_authenticated_user_can_delete_memory_media(): void
    {
        [$user, $token] = $this->authenticatedUser();

        $memory = Memory::create([
            'user_id' => $user->id,
            'title' => 'Media Delete Test',
            'memory_date' => '2026-04-01',
        ]);

        $media = $memory->media()->create([
            'media_type' => 'image',
            'file_url' => 'https://example.com/test.jpg',
            'sort_order' => 0,
        ]);

        $response = $this->withHeaders($this->headers($token))
            ->deleteJson('/api/memories/'.$memory->id.'/media/'.$media->id);

        $response
            ->assertOk()
            ->assertJsonPath('status_code', 1);

        $this->assertDatabaseMissing('memory_media', ['id' => $media->id]);
    }

    public function test_authenticated_user_can_delete_memory(): void
    {
        [$user, $token] = $this->authenticatedUser();

        $memory = Memory::create([
            'user_id' => $user->id,
            'title' => 'Delete me',
            'memory_date' => '2026-04-01',
            'status' => 'active',
        ]);

        $response = $this->withHeaders($this->headers($token))
            ->deleteJson('/api/memories/'.$memory->id);

        $response
            ->assertOk()
            ->assertJsonPath('status_code', 1);

        $this->assertSoftDeleted('memories', [
            'id' => $memory->id,
        ]);
    }

    private function authenticatedUser(): array
    {
        $user = User::factory()->create();
        $token = str_repeat('m', 80);

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
