<?php

namespace Tests\Feature;

use App\Models\Contest;
use App\Models\ContestSubmission;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContestApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_and_show_ignore_stored_contest_type(): void
    {
        [, $token] = $this->authenticatedUser('viewer');
        $contest = $this->createContest([
            'title' => 'Summer Looks',
            'category' => 'admin',
            'contest_type' => 'city',
            'prize_pool' => 500,
        ]);

        $this->withHeaders($this->headers($token))
            ->getJson('/api/contests/home')
            ->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('contests.0.id', $contest->id)
            ->assertJsonPath('contests.0.contest_type', 'contest')
            ->assertJsonPath('contests.0.can_join', true)
            ->assertJsonPath('contests.0.is_admin_contest', true)
            ->assertJsonPath('contests.0.featured', true);

        $this->withHeaders($this->headers($token))
            ->getJson('/api/contests/'.$contest->id)
            ->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('contest.contest_type', 'contest')
            ->assertJsonPath('contest.can_join', true)
            ->assertJsonPath('contest.is_participant', false)
            ->assertJsonPath('contest.team_ranking', [])
            ->assertJsonPath('joinable_users', []);
    }

    public function test_admin_list_excludes_community_contests(): void
    {
        [, $token] = $this->authenticatedUser('viewer');
        $admin = $this->createContest(['title' => 'Admin Arena', 'category' => 'admin']);
        $this->createContest(['title' => 'Street Style', 'category' => 'community', 'contest_type' => 'group']);

        $this->withHeaders($this->headers($token))
            ->getJson('/api/contests/admin')
            ->assertOk()
            ->assertJsonPath('contests.0.id', $admin->id)
            ->assertJsonCount(1, 'contests');
    }

    public function test_user_can_join_vote_and_rank(): void
    {
        [$alice, $aliceToken] = $this->authenticatedUser('alice', 'a');
        [$bob, $bobToken] = $this->authenticatedUser('bob', 'b');
        $contest = $this->createContest(['title' => 'Clean Logic']);
        $alicePost = $this->createOutfitPost($alice->id, 'Alice look');
        $bobPost = $this->createOutfitPost($bob->id, 'Bob look');

        $join = $this->withHeaders($this->headers($aliceToken))
            ->postJson('/api/contests/'.$contest->id.'/join', ['post_id' => $alicePost->id])
            ->assertCreated()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('contest.can_join', false)
            ->assertJsonPath('contest.is_participant', true)
            ->assertJsonPath('contest.submissions.0.is_mine', true)
            ->assertJsonPath('contest.submissions.0.username', 'alice');

        $aliceSubmissionId = (int) $join->json('contest.submissions.0.id');

        $this->withHeaders($this->headers($aliceToken))
            ->postJson('/api/contests/'.$contest->id.'/join', ['post_id' => $alicePost->id])
            ->assertStatus(422)
            ->assertJsonPath('status_code', 0);

        $bobJoin = $this->withHeaders($this->headers($bobToken))
            ->postJson('/api/contests/'.$contest->id.'/submissions', ['post_id' => $bobPost->id])
            ->assertCreated();

        $bobSubmissionId = (int) collect($bobJoin->json('contest.submissions'))
            ->firstWhere('username', 'bob')['id'];

        $this->withHeaders($this->headers($aliceToken))
            ->postJson('/api/contests/'.$contest->id.'/vote', [
                'submission_id' => $aliceSubmissionId,
                'score' => 5,
            ])
            ->assertStatus(422);

        $this->withHeaders($this->headers($aliceToken))
            ->postJson('/api/contests/'.$contest->id.'/vote', [
                'submission_id' => $bobSubmissionId,
                'score' => 5,
            ])
            ->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('contest.winner', null);

        // Alice sees her own 5 on Bob's entry; Bob (who didn't vote) sees none.
        $aliceView = collect(
            $this->withHeaders($this->headers($aliceToken))
                ->getJson('/api/contests/'.$contest->id)
                ->assertOk()
                ->json('contest.submissions')
        )->firstWhere('username', 'bob');
        $this->assertSame(5.0, (float) $aliceView['viewer_score']);
        $this->assertTrue((bool) $aliceView['viewer_has_voted']);

        $ranked = $this->withHeaders($this->headers($bobToken))
            ->getJson('/api/contests/'.$contest->id)
            ->assertOk();

        $bobRow = collect($ranked->json('contest.submissions'))->firstWhere('username', 'bob');
        $this->assertSame(1, (int) $bobRow['rank']);
        $this->assertSame(5.0, (float) $bobRow['score']);
        $this->assertSame(1, (int) $bobRow['total_votes']);
        $this->assertNull($bobRow['viewer_score']);
        $this->assertFalse((bool) $bobRow['viewer_has_voted']);

        $bobRanking = collect($ranked->json('contest.participants_ranking'))->firstWhere('username', 'bob');
        $this->assertSame($bobPost->id, (int) $bobRanking['post_id']);
        $this->assertSame($bobSubmissionId, (int) $bobRanking['submission_id']);

        $this->assertDatabaseHas('contest_participants', [
            'contest_id' => $contest->id,
            'user_id' => $alice->id,
            'status' => 'approved',
        ]);
        $this->assertSame(2, ContestSubmission::query()->where('contest_id', $contest->id)->count());
    }

    public function test_removed_one_vs_one_and_city_endpoints_return_422(): void
    {
        [, $token] = $this->authenticatedUser('viewer');
        $contest = $this->createContest(['contest_type' => 'city']);

        $this->withHeaders($this->headers($token))
            ->postJson('/api/contests/one-vs-one', ['title' => 'Duel'])
            ->assertStatus(422)
            ->assertJsonPath('status_code', 0);

        $this->withHeaders($this->headers($token))
            ->postJson('/api/contests/city-vs-city', ['title' => 'Lahore vs Karachi'])
            ->assertStatus(422);

        $this->withHeaders($this->headers($token))
            ->postJson('/api/contests/'.$contest->id.'/one-vs-one/request-join')
            ->assertStatus(422);

        $this->withHeaders($this->headers($token))
            ->postJson('/api/contests/'.$contest->id.'/city/join')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Join this contest with an outfit using the join endpoint.');
    }

    public function test_completed_contest_cannot_be_joined(): void
    {
        [$user, $token] = $this->authenticatedUser('late');
        $contest = $this->createContest([
            'status' => 'completed',
            'end_at' => now()->subDay(),
            'voting_end_at' => now()->subDay(),
        ]);
        $post = $this->createOutfitPost($user->id, 'Too late');

        $this->withHeaders($this->headers($token))
            ->getJson('/api/contests/'.$contest->id)
            ->assertOk()
            ->assertJsonPath('contest.can_join', false);

        $this->withHeaders($this->headers($token))
            ->postJson('/api/contests/'.$contest->id.'/join', ['post_id' => $post->id])
            ->assertStatus(422)
            ->assertJsonPath('message', 'This contest has ended.');
    }

    public function test_profile_results_only_include_joined_contests(): void
    {
        [$user, $token] = $this->authenticatedUser('joined');
        $mine = $this->createContest(['title' => 'My result']);
        $this->createContest(['title' => 'Not mine']);
        $post = $this->createOutfitPost($user->id, 'Entry');

        $this->withHeaders($this->headers($token))
            ->postJson('/api/contests/'.$mine->id.'/join', ['post_id' => $post->id])
            ->assertCreated();

        $this->withHeaders($this->headers($token))
            ->getJson('/api/contests/profile/results')
            ->assertOk()
            ->assertJsonCount(1, 'contests')
            ->assertJsonPath('contests.0.id', $mine->id)
            ->assertJsonPath('contests.0.is_participant', true);
    }

    public function test_deleted_posts_are_hidden_from_contest_feed(): void
    {
        [$user, $token] = $this->authenticatedUser('entrant');
        [, $viewerToken] = $this->authenticatedUser('spectator', 's');
        $contest = $this->createContest(['title' => 'Live looks']);
        $post = $this->createOutfitPost($user->id, 'Soon deleted');

        $this->withHeaders($this->headers($token))
            ->postJson('/api/contests/'.$contest->id.'/join', ['post_id' => $post->id])
            ->assertCreated()
            ->assertJsonPath('contest.submissions.0.username', 'entrant');

        $post->delete();

        $this->withHeaders($this->headers($viewerToken))
            ->getJson('/api/contests/'.$contest->id)
            ->assertOk()
            ->assertJsonPath('contest.submissions', [])
            ->assertJsonPath('contest.participants_ranking', [])
            ->assertJsonPath('contest.can_join', true)
            ->assertJsonPath('contest.submission_count', 0);

        $this->withHeaders($this->headers($token))
            ->getJson('/api/contests/'.$contest->id)
            ->assertOk()
            ->assertJsonPath('contest.can_join', true)
            ->assertJsonPath('contest.is_participant', false);

        $replacement = $this->createOutfitPost($user->id, 'Replacement look');
        $this->withHeaders($this->headers($token))
            ->postJson('/api/contests/'.$contest->id.'/join', ['post_id' => $replacement->id])
            ->assertCreated()
            ->assertJsonPath('contest.submissions.0.caption', 'Replacement look');
    }

    public function test_removed_or_blocked_posts_are_hidden_from_contest_feed(): void
    {
        [$user, $token] = $this->authenticatedUser('blocked_look');
        $contest = $this->createContest();
        $removed = $this->createOutfitPost($user->id, 'Removed by admin');

        $this->withHeaders($this->headers($token))
            ->postJson('/api/contests/'.$contest->id.'/join', ['post_id' => $removed->id])
            ->assertCreated();

        $removed->forceFill([
            'status' => 'removed',
            'is_blocked' => true,
        ])->save();

        $this->withHeaders($this->headers($token))
            ->getJson('/api/contests/'.$contest->id)
            ->assertOk()
            ->assertJsonPath('contest.submissions', [])
            ->assertJsonPath('contest.can_join', true);
    }

    public function test_feed_rating_is_enabled_only_for_contest_enrolled_posts(): void
    {
        [$author, $authorToken] = $this->authenticatedUser('author', 'a');
        [, $viewerToken] = $this->authenticatedUser('viewer', 'v');
        $contest = $this->createContest();
        $plain = $this->createOutfitPost($author->id, 'Plain look');
        $plain->forceFill(['rating_enabled' => true])->save();
        $entered = $this->createOutfitPost($author->id, 'Contest look');

        $this->withHeaders($this->headers($authorToken))
            ->postJson('/api/contests/'.$contest->id.'/join', ['post_id' => $entered->id])
            ->assertCreated();

        $feed = collect(
            $this->withHeaders($this->headers($viewerToken))
                ->getJson('/api/feed/home')
                ->assertOk()
                ->json('feed')
        );

        $plainCard = $feed->firstWhere('id', $plain->id);
        $enteredCard = $feed->firstWhere('id', $entered->id);

        $this->assertNotNull($plainCard);
        $this->assertNotNull($enteredCard);
        $this->assertFalse((bool) $plainCard['in_contest']);
        $this->assertFalse((bool) $plainCard['rating_enabled']);
        $this->assertTrue((bool) $enteredCard['in_contest']);
        $this->assertTrue((bool) $enteredCard['rating_enabled']);
        $this->assertTrue((bool) $enteredCard['viewer_state']['can_vote']);
        $this->assertNull($plainCard['contest']);
        $this->assertSame($contest->id, (int) $enteredCard['contest']['id']);
        $this->assertSame($contest->title, $enteredCard['contest']['title']);

        $this->withHeaders($this->headers($viewerToken))
            ->postJson('/api/feed/posts/'.$plain->id.'/vote', ['rating_value' => 5])
            ->assertForbidden()
            ->assertJsonPath('can_vote', false);

        $this->withHeaders($this->headers($viewerToken))
            ->postJson('/api/feed/posts/'.$entered->id.'/vote', ['rating_value' => 4])
            ->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('can_vote', true);
    }

    public function test_enrolled_posts_endpoint_lists_only_live_contest_posts(): void
    {
        [$author, $authorToken] = $this->authenticatedUser('author', 'a');
        [, $viewerToken] = $this->authenticatedUser('viewer', 'v');
        $contest = $this->createContest();
        $this->createOutfitPost($author->id, 'Plain look');
        $entered = $this->createOutfitPost($author->id, 'Contest look');
        $removed = $this->createOutfitPost($author->id, 'Removed look');

        $this->withHeaders($this->headers($authorToken))
            ->postJson('/api/contests/'.$contest->id.'/join', ['post_id' => $entered->id])
            ->assertCreated();

        $other = $this->createContest(['title' => 'Second Arena']);
        $this->withHeaders($this->headers($authorToken))
            ->postJson('/api/contests/'.$other->id.'/join', ['post_id' => $removed->id])
            ->assertCreated();
        $removed->forceFill(['status' => 'removed', 'is_blocked' => true])->save();

        $this->withHeaders($this->headers($viewerToken))
            ->getJson('/api/contests/enrolled-posts')
            ->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('post_ids', [$entered->id])
            ->assertJsonPath('posts.0.post_id', $entered->id)
            ->assertJsonPath('posts.0.contest_id', $contest->id)
            ->assertJsonPath('posts.0.contest_title', $contest->title);

        $contests = collect(
            $this->withHeaders($this->headers($viewerToken))
                ->getJson('/api/contests/home')
                ->assertOk()
                ->json('contests')
        );

        $this->assertSame([$entered->id], $contests->firstWhere('id', $contest->id)['enrolled_post_ids']);
        $this->assertSame([], $contests->firstWhere('id', $other->id)['enrolled_post_ids']);
    }

    private function createContest(array $overrides = []): Contest
    {
        return Contest::query()->create(array_merge([
            'slug' => 'contest-'.uniqid(),
            'title' => 'Open Contest',
            'subtitle' => 'Join with an outfit',
            'description' => 'View, join, vote, rank.',
            'category' => 'admin',
            'contest_type' => 'group',
            'status' => 'active',
            'visibility' => 'public',
            'prize_pool' => 0,
            'start_at' => now()->subHour(),
            'end_at' => now()->addDays(3),
            'enrollment_start_at' => now()->subHour(),
            'enrollment_end_at' => now()->addDays(2),
            'voting_start_at' => now()->subHour(),
            'voting_end_at' => now()->addDays(2),
        ], $overrides));
    }

    private function createOutfitPost(int $userId, string $caption): Post
    {
        $post = Post::create([
            'user_id' => $userId,
            'post_type' => 'outfit',
            'content_type' => 'fashion',
            'media_kind' => 'image',
            'feed_type' => 'style',
            'caption' => $caption,
            'visibility' => 'public',
            'status' => 'published',
            'moderation_status' => 'clean',
            'posted_at' => now(),
            'published_at' => now(),
        ]);

        PostMedia::create([
            'post_id' => $post->id,
            'media_type' => 'image',
            'media_role' => 'original',
            'file_url' => 'https://example.com/contest-'.$post->id.'.jpg',
            'sort_order' => 0,
            'processing_status' => 'ready',
        ]);

        return $post;
    }

    private function authenticatedUser(string $username = 'contest_user', string $tokenSeed = 'c'): array
    {
        $user = User::factory()->create([
            'username' => $username,
            'status' => 'active',
        ]);
        $token = str_repeat($tokenSeed, 80);

        UserSession::create([
            'user_id' => $user->id,
            'session_token_hash' => hash('sha256', $token),
            'platform' => 'android',
            'device_id' => 'android-device-'.$tokenSeed,
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
