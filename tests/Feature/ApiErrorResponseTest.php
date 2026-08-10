<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Guards the API's error contract.
 *
 * Laravel converts ModelNotFoundException into a NotFoundHttpException *before*
 * render callbacks run, so the app's ModelNotFoundException handler could never
 * fire: every findOrFail/firstOrFail in the API answered 500 instead of 404, and
 * middleware rate limiting answered 500 instead of 429. These assertions exist so
 * that regression cannot come back silently.
 */
class ApiErrorResponseTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: string}
     */
    private function authenticatedUser(): array
    {
        $user = User::factory()->create(['status' => 'active']);
        $token = Str::random(80);

        UserSession::create([
            'user_id' => $user->id,
            'session_token_hash' => hash('sha256', $token),
            'platform' => 'android',
            'last_seen_at' => now(),
            'expires_at' => now()->addDay(),
        ]);

        return [$user, $token];
    }

    private function headers(string $token): array
    {
        return ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$token];
    }

    public function test_a_missing_record_behind_first_or_fail_is_a_404_not_a_500(): void
    {
        [, $token] = $this->authenticatedUser();

        // Real endpoint a user hits: a profile that no longer exists.
        $this->withHeaders($this->headers($token))
            ->getJson('/api/profiles/definitely-not-a-real-username')
            ->assertStatus(404)
            ->assertJsonPath('status_code', 0)
            ->assertJsonPath('message', 'Requested resource was not found.');
    }

    public function test_a_missing_support_ticket_is_a_404(): void
    {
        [, $token] = $this->authenticatedUser();

        $this->withHeaders($this->headers($token))
            ->getJson('/api/support/tickets/999999')
            ->assertStatus(404)
            ->assertJsonPath('status_code', 0);
    }

    public function test_an_unknown_api_route_returns_the_standard_json_404(): void
    {
        $this->getJson('/api/there-is-no-such-endpoint')
            ->assertStatus(404)
            ->assertJsonPath('status_code', 0)
            ->assertJsonPath('message', 'Requested resource was not found.');
    }

    public function test_the_wrong_http_method_returns_405_rather_than_500(): void
    {
        // /api/auth/login is POST-only.
        $this->getJson('/api/auth/login')
            ->assertStatus(405)
            ->assertJsonPath('status_code', 0)
            ->assertJsonPath('message', 'This action is not allowed on this endpoint.');
    }

    public function test_rate_limiting_returns_429_rather_than_500(): void
    {
        // /api/reports is throttled at 20 per hour.
        [, $token] = $this->authenticatedUser();

        $lastStatus = null;

        for ($attempt = 0; $attempt < 22; $attempt++) {
            $lastStatus = $this->withHeaders($this->headers($token))
                ->postJson('/api/reports', [
                    'target_type' => 'post',
                    'target_id' => 999000 + $attempt,
                    'reason' => 'spam',
                ])->getStatusCode();

            if ($lastStatus === 429) {
                break;
            }
        }

        $this->assertSame(429, $lastStatus, 'The throttle should answer 429, not 500.');
    }

    public function test_validation_errors_keep_their_own_shape(): void
    {
        [, $token] = $this->authenticatedUser();

        // The dedicated ValidationException handler must still win over the
        // generic HTTP one.
        $this->withHeaders($this->headers($token))
            ->postJson('/api/reports', [])
            ->assertStatus(422)
            ->assertJsonPath('status_code', 0)
            ->assertJsonStructure(['status_code', 'message', 'errors']);
    }

    public function test_an_unauthenticated_api_call_is_still_401(): void
    {
        $this->getJson('/api/profile/me')
            ->assertStatus(401)
            ->assertJsonPath('status_code', 0);
    }
}
