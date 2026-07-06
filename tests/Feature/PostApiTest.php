<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PostApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_outfit_post(): void
    {
        Storage::fake('public');
        Http::fake([
            'https://maps.googleapis.com/maps/api/geocode/json*' => Http::response([
                'status' => 'OK',
                'results' => [[
                    'formatted_address' => 'Lahore, Punjab, Pakistan',
                    'address_components' => [
                        ['long_name' => 'Lahore', 'types' => ['locality']],
                        ['long_name' => 'Pakistan', 'types' => ['country']],
                    ],
                ]],
            ]),
        ]);

        [$user, $token] = $this->authenticatedUser();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$token,
        ])->post('/api/posts', [
            'post_type' => '1',
            'file' => UploadedFile::fake()->image('outfit.jpg'),
            'caption' => 'Weekend outfit',
            'tags' => 'Casual, Summer, Casual',
            'visibility' => 'followers_only',
            'lat' => '31.5204',
            'lng' => '74.3587',
            'rating_enabled' => true,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('post.post_type', 'outfit')
            ->assertJsonPath('post.visibility', 'followers_only')
            ->assertJsonPath('post.city', 'Lahore')
            ->assertJsonPath('post.country', 'Pakistan')
            ->assertJsonPath('post.rating_enabled', true);

        $this->assertDatabaseHas('posts', [
            'user_id' => $user->id,
            'post_type' => 'outfit',
            'caption' => 'Weekend outfit',
            'visibility' => 'followers_only',
            'city' => 'Lahore',
            'country' => 'Pakistan',
            'rating_enabled' => 1,
        ]);

        $this->assertDatabaseHas('tags', [
            'normalized_name' => 'casual',
        ]);

        $this->assertDatabaseHas('tags', [
            'normalized_name' => 'summer',
        ]);

        $this->assertDatabaseHas('media_uploads', [
            'user_id' => $user->id,
            'upload_type' => 'post_media',
            'media_type' => 'image',
        ]);
    }

    public function test_authenticated_user_can_create_food_post(): void
    {
        Storage::fake('public');
        Http::fake([
            'https://maps.googleapis.com/maps/api/geocode/json*' => Http::response([
                'status' => 'OK',
                'results' => [[
                    'formatted_address' => 'Karachi, Sindh, Pakistan',
                    'address_components' => [
                        ['long_name' => 'Karachi', 'types' => ['locality']],
                        ['long_name' => 'Pakistan', 'types' => ['country']],
                    ],
                ]],
            ]),
        ]);

        [$user, $token] = $this->authenticatedUser();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$token,
        ])->post('/api/posts', [
            'post_type' => '2',
            'file' => UploadedFile::fake()->image('food.jpg'),
            'caption' => 'Amazing biryani night',
            'tags' => 'Biryani, Dinner',
            'visibility' => 'private',
            'lat' => '24.8607',
            'lng' => '67.0011',
            'dish_name' => 'Chicken Biryani',
            'restaurant' => 'Spice House',
            'food_rating' => 5,
            'service_rating' => 4,
            'staff_rating' => 4,
            'ambience_rating' => 5,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('post.post_type', 'food')
            ->assertJsonPath('post.dish_name', 'Chicken Biryani')
            ->assertJsonPath('post.restaurant', 'Spice House')
            ->assertJsonPath('post.visibility', 'private')
            ->assertJsonPath('post.city', 'Karachi')
            ->assertJsonPath('post.country', 'Pakistan')
            ->assertJsonPath('post.food_rating', 5)
            ->assertJsonPath('post.service_rating', 4)
            ->assertJsonPath('post.staff_rating', 4)
            ->assertJsonPath('post.ambience_rating', 5);

        $this->assertDatabaseHas('posts', [
            'user_id' => $user->id,
            'post_type' => 'food',
            'dish_name' => 'Chicken Biryani',
            'restaurant' => 'Spice House',
            'visibility' => 'private',
            'city' => 'Karachi',
            'country' => 'Pakistan',
            'food_rating' => 5,
            'service_rating' => 4,
            'staff_rating' => 4,
            'ambience_rating' => 5,
            'rating_enabled' => 1,
        ]);
    }

    public function test_post_api_requires_valid_bearer_token(): void
    {
        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->post('/api/posts', []);

        $response
            ->assertUnauthorized()
            ->assertJsonPath('status_code', 0);
    }

    public function test_post_api_can_resolve_city_and_country_from_later_geocode_results(): void
    {
        Http::fake([
            'https://maps.googleapis.com/maps/api/geocode/json*' => Http::response([
                'status' => 'OK',
                'results' => [
                    [
                        'formatted_address' => 'Unnamed Road, Pakistan',
                        'address_components' => [
                            ['long_name' => 'Pakistan', 'types' => ['country']],
                        ],
                    ],
                    [
                        'formatted_address' => 'Lahore, Punjab, Pakistan',
                        'address_components' => [
                            ['long_name' => 'Lahore', 'types' => ['locality']],
                            ['long_name' => 'Pakistan', 'types' => ['country']],
                        ],
                    ],
                ],
            ]),
        ]);

        [$user, $token] = $this->authenticatedUser();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$token,
        ])->post('/api/posts', [
            'post_type' => '1',
            'file' => UploadedFile::fake()->image('outfit.jpg'),
            'caption' => 'Later result city lookup',
            'lat' => '31.5204',
            'lng' => '74.3587',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('post.city', 'Lahore')
            ->assertJsonPath('post.country', 'Pakistan');
    }

    public function test_post_api_falls_back_to_openstreetmap_when_google_does_not_resolve_city(): void
    {
        Http::fake([
            'https://maps.googleapis.com/maps/api/geocode/json*' => Http::response([
                'status' => 'REQUEST_DENIED',
                'results' => [],
            ], 200),
            'https://nominatim.openstreetmap.org/reverse*' => Http::response([
                'display_name' => 'Karachi, Sindh, Pakistan',
                'address' => [
                    'city' => 'Karachi',
                    'country' => 'Pakistan',
                ],
            ], 200),
        ]);

        [$user, $token] = $this->authenticatedUser();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$token,
        ])->post('/api/posts', [
            'post_type' => '1',
            'file' => UploadedFile::fake()->image('outfit.jpg'),
            'caption' => 'OSM fallback lookup',
            'lat' => '24.8607',
            'lng' => '67.0011',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('post.city', 'Karachi')
            ->assertJsonPath('post.country', 'Pakistan');
    }

    public function test_authenticated_user_can_update_delete_and_repost_post(): void
    {
        Http::fake([
            'https://maps.googleapis.com/maps/api/geocode/json*' => Http::response([
                'status' => 'OK',
                'results' => [[
                    'formatted_address' => 'Islamabad, Pakistan',
                    'address_components' => [
                        ['long_name' => 'Islamabad', 'types' => ['locality']],
                        ['long_name' => 'Pakistan', 'types' => ['country']],
                    ],
                ]],
            ]),
        ]);

        [$user, $token] = $this->authenticatedUser();

        $createResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$token,
        ])->post('/api/posts', [
            'post_type' => '1',
            'file' => UploadedFile::fake()->image('outfit.jpg'),
            'caption' => 'Original caption',
            'tags' => 'Casual, Summer',
        ])->assertCreated();

        $postId = $createResponse->json('post.id');

        $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$token,
        ])->patch('/api/posts/'.$postId, [
            'caption' => 'Updated caption',
            'tags' => 'Updated, Look',
            'location' => 'Islamabad',
            'lat' => '33.6844',
            'lng' => '73.0479',
            'visibility' => 'private',
        ])->assertOk()
            ->assertJsonPath('post.caption', 'Updated caption')
            ->assertJsonPath('post.visibility', 'private')
            ->assertJsonPath('post.city', 'Islamabad');

        $this->assertDatabaseHas('posts', [
            'id' => $postId,
            'caption' => 'Updated caption',
            'visibility' => 'private',
            'city' => 'Islamabad',
            'status' => 'published',
        ]);

        $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$token,
        ])->delete('/api/posts/'.$postId)
            ->assertOk()
            ->assertJsonPath('status_code', 1);

        $this->assertSoftDeleted('posts', [
            'id' => $postId,
            'status' => 'removed',
        ]);

        $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$token,
        ])->post('/api/posts/'.$postId.'/repost')
            ->assertOk()
            ->assertJsonPath('post.id', $postId)
            ->assertJsonPath('post.caption', 'Updated caption');

        $this->assertDatabaseHas('posts', [
            'id' => $postId,
            'status' => 'published',
            'deleted_at' => null,
        ]);
    }

    private function authenticatedUser(): array
    {
        $user = User::factory()->create();
        $token = str_repeat('a', 80);

        UserSession::create([
            'user_id' => $user->id,
            'session_token_hash' => hash('sha256', $token),
            'platform' => 'web',
            'last_seen_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        return [$user, $token];
    }
}
