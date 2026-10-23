<?php

namespace Tests\Feature;

use App\Models\Contest;
use App\Models\ContestSubmission;
use App\Models\ContestVote;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\PostRating;
use App\Models\Profile;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContestFeedRatingTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_feed_exposes_contest_vote_average_on_contest_posts(): void
    {
        [$author] = $this->authenticatedUser('author_feed');
        [$voter, $voterToken] = $this->authenticatedUser('voter_feed');
        [$viewer, $viewerToken] = $this->authenticatedUser('viewer_feed');

        $contest = Contest::query()->create([
            'slug' => 'rating-'.uniqid(),
            'title' => 'Rating Contest',
            'category' => 'admin',
            'contest_type' => 'group',
            'status' => 'active',
            'visibility' => 'public',
            'entry_fee' => 0,
            'prize_pool' => 1000,
            'voting_type' => 'community',
            'start_at' => now()->subDay(),
            'end_at' => now()->addDay(),
            'voting_start_at' => now()->subDay(),
            'voting_end_at' => now()->addDay(),
        ]);

        $post = Post::query()->create([
            'user_id' => $author->id,
            'post_type' => 'outfit',
            'content_type' => 'fashion',
            'media_kind' => 'image',
            'feed_type' => 'style',
            'caption' => 'contest picture',
            'visibility' => 'public',
            'status' => 'published',
            'moderation_status' => 'clean',
            'like_count' => 1,
            'rating_avg' => null,
            'rating_count' => 0,
            'posted_at' => now(),
            'published_at' => now(),
        ]);
        PostMedia::query()->create([
            'post_id' => $post->id,
            'media_type' => 'image',
            'media_role' => 'original',
            'file_url' => '/fixtures/post-1.jpg',
            'sort_order' => 0,
            'processing_status' => 'ready',
        ]);

        $submission = ContestSubmission::query()->create([
            'contest_id' => $contest->id,
            'user_id' => $author->id,
            'post_id' => $post->id,
            'submission_status' => 'approved',
            'community_score' => 4.5,
            'final_score' => 315.3,
            'rank_position' => 1,
            'submitted_at' => now(),
        ]);

        ContestVote::query()->create([
            'contest_id' => $contest->id,
            'submission_id' => $submission->id,
            'voter_user_id' => $voter->id,
            'vote_type' => 'community',
            'score' => 4.5,
            'created_at' => now(),
        ]);

        $this->assertSame(0, PostRating::query()->where('post_id', $post->id)->count());

        $response = $this->withHeaders($this->headers($viewerToken))
            ->getJson('/api/feed/home?type=outfit&page=1&per_page=10');

        $response->assertOk()->assertJsonPath('status_code', 1);

        $feedPost = collect($response->json('feed'))
            ->firstWhere('id', $post->id);

        $this->assertNotNull($feedPost);
        $this->assertTrue((bool) $feedPost['in_contest']);
        $this->assertSame(4.5, (float) $feedPost['engagement']['rating_avg']);
        $this->assertSame(1, (int) $feedPost['engagement']['rating_count']);
        $this->assertSame($contest->id, (int) $feedPost['contest']['id']);
    }

    public function test_contest_vote_mirrors_into_post_ratings_for_feed_display(): void
    {
        [$author] = $this->authenticatedUser('author_vote');
        [$voter, $voterToken] = $this->authenticatedUser('voter_vote');

        $contest = Contest::query()->create([
            'slug' => 'mirror-'.uniqid(),
            'title' => 'Mirror Contest',
            'category' => 'admin',
            'contest_type' => 'group',
            'status' => 'active',
            'visibility' => 'public',
            'entry_fee' => 0,
            'prize_pool' => 500,
            'voting_type' => 'community',
            'start_at' => now()->subDay(),
            'end_at' => now()->addDay(),
            'voting_start_at' => now()->subDay(),
            'voting_end_at' => now()->addDay(),
        ]);

        $post = Post::query()->create([
            'user_id' => $author->id,
            'post_type' => 'outfit',
            'content_type' => 'fashion',
            'media_kind' => 'image',
            'feed_type' => 'style',
            'caption' => 'mirror look',
            'visibility' => 'public',
            'status' => 'published',
            'moderation_status' => 'clean',
            'like_count' => 0,
            'posted_at' => now(),
            'published_at' => now(),
        ]);
        PostMedia::query()->create([
            'post_id' => $post->id,
            'media_type' => 'image',
            'media_role' => 'original',
            'file_url' => '/fixtures/post-2.jpg',
            'sort_order' => 0,
            'processing_status' => 'ready',
        ]);

        $submission = ContestSubmission::query()->create([
            'contest_id' => $contest->id,
            'user_id' => $author->id,
            'post_id' => $post->id,
            'submission_status' => 'approved',
            'submitted_at' => now(),
        ]);

        $this->withHeaders($this->headers($voterToken))
            ->postJson('/api/contests/'.$contest->id.'/vote', [
                'submission_id' => $submission->id,
                'score' => 5,
            ])
            ->assertOk()
            ->assertJsonPath('status_code', 1);

        $this->assertDatabaseHas('post_ratings', [
            'post_id' => $post->id,
            'user_id' => $voter->id,
            'rating_value' => 5,
        ]);
        $this->assertSame(5.0, (float) $post->fresh()->rating_avg);
        $this->assertSame(1, (int) $post->fresh()->rating_count);
    }

    private function authenticatedUser(string $username): array
    {
        $user = User::factory()->create(['username' => $username]);
        Profile::query()->create([
            'user_id' => $user->id,
            'display_name' => $username,
        ]);
        $token = str_repeat(substr(md5($username), 0, 1), 80);
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
