<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\LegalAcceptance;
use App\Models\LegalDocument;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Editable Privacy Policy and Terms, with an audit trail of who agreed to what.
 *
 * The property that matters legally is immutability: once a version is published
 * and someone has accepted it, that exact text must stay recoverable. Editing in
 * place would destroy the only evidence of what the user actually agreed to, so
 * these tests pin versioning behaviour rather than the editor's cosmetics.
 */
class LegalDocumentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'status' => 'active']);
    }

    /**
     * @return array{0: User, 1: string}
     */
    private function appUser(): array
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

    private function publish(string $key, int $version, string $body = 'Published text.'): LegalDocument
    {
        return LegalDocument::create([
            'key' => $key,
            'version' => $version,
            'title' => ucfirst(str_replace('_', ' ', $key))." v{$version}",
            'body' => $body,
            'is_published' => true,
            'published_at' => now(),
        ]);
    }

    public function test_saving_a_published_document_creates_a_new_version_instead_of_editing_it(): void
    {
        // Seeded v1 exists from the migration; capture it before editing.
        $original = LegalDocument::current(LegalDocument::KEY_PRIVACY);
        $this->assertNotNull($original, 'The migration should have published v1.');
        $originalBody = $original->body;

        $this->actingAs($this->admin())
            ->post(route('admin.legal.store', LegalDocument::KEY_PRIVACY), [
                'title' => 'Privacy Policy',
                'body' => 'We collect much less than we used to.',
                'summary_of_changes' => 'Reduced data collection.',
                'publish' => 1,
                'requires_reacceptance' => 1,
            ])
            ->assertRedirect(route('admin.legal.edit', LegalDocument::KEY_PRIVACY));

        // The old row is untouched, byte for byte.
        $this->assertSame($originalBody, $original->fresh()->body);

        $current = LegalDocument::current(LegalDocument::KEY_PRIVACY);
        $this->assertSame($original->version + 1, $current->version);
        $this->assertSame('We collect much less than we used to.', $current->body);
        $this->assertTrue((bool) $current->requires_reacceptance);
    }

    public function test_an_unpublished_draft_is_edited_in_place_rather_than_stacking_versions(): void
    {
        $admin = $this->admin();
        $startingVersion = LegalDocument::nextVersion(LegalDocument::KEY_TERMS);

        $this->actingAs($admin)->post(route('admin.legal.store', LegalDocument::KEY_TERMS), [
            'title' => 'Terms',
            'body' => 'First attempt.',
        ]);

        $this->actingAs($admin)->post(route('admin.legal.store', LegalDocument::KEY_TERMS), [
            'title' => 'Terms',
            'body' => 'Second attempt.',
        ]);

        $drafts = LegalDocument::where('key', LegalDocument::KEY_TERMS)->where('is_published', false)->get();

        $this->assertCount(1, $drafts, 'Repeated draft saves must not create a version each time.');
        $this->assertSame('Second attempt.', $drafts->first()->body);
        $this->assertSame($startingVersion, $drafts->first()->version);
    }

    public function test_a_draft_is_not_served_to_users_until_published(): void
    {
        $admin = $this->admin();
        $liveVersion = LegalDocument::current(LegalDocument::KEY_PRIVACY)->version;

        $this->actingAs($admin)->post(route('admin.legal.store', LegalDocument::KEY_PRIVACY), [
            'title' => 'Privacy Policy',
            'body' => 'Unreleased wording.',
        ]);

        $response = $this->getJson('/api/legal/'.LegalDocument::KEY_PRIVACY);

        $response->assertOk()->assertJsonPath('document.version', $liveVersion);
        $this->assertStringNotContainsString('Unreleased wording.', $response->json('document.body'));
    }

    public function test_publishing_is_recorded_in_the_activity_log(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.legal.store', LegalDocument::KEY_TERMS), [
            'title' => 'Terms of Service',
            'body' => 'Be decent to one another.',
            'summary_of_changes' => 'Initial rewrite.',
            'publish' => 1,
        ]);

        $log = ActivityLog::where('event_name', 'legal_document_published')->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame('legal_document', $log->entity_type);
        $this->assertSame(LegalDocument::KEY_TERMS, $log->metadata_json['key']);
        $this->assertSame('Initial rewrite.', $log->metadata_json['summary_of_changes']);
    }

    public function test_an_unknown_document_key_is_a_404_not_a_new_document(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.legal.store', 'cookie_policy'), ['title' => 'Cookies', 'body' => 'Nom.'])
            ->assertNotFound();

        $this->getJson('/api/legal/cookie_policy')->assertNotFound();
        $this->assertDatabaseMissing('legal_documents', ['key' => 'cookie_policy']);
    }

    public function test_a_document_body_is_required(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.legal.store', LegalDocument::KEY_TERMS), ['title' => 'Terms', 'body' => ''])
            ->assertSessionHasErrors('body');
    }

    public function test_staff_without_the_legal_permission_cannot_reach_the_editor(): void
    {
        $moderator = User::factory()->create(['role' => 'user', 'status' => 'active']);
        $moderator->assignRole('content_moderator');

        $this->actingAs($moderator)->get(route('admin.legal.index'))->assertForbidden();
        $this->actingAs($moderator)
            ->post(route('admin.legal.store', LegalDocument::KEY_TERMS), ['title' => 'x', 'body' => 'y'])
            ->assertForbidden();
    }

    public function test_the_api_returns_the_current_version_with_split_paragraphs(): void
    {
        $this->publish(LegalDocument::KEY_TERMS, 90, "First para.\n\nSecond para.\n\n\nThird para.");

        $this->getJson('/api/legal/'.LegalDocument::KEY_TERMS)
            ->assertOk()
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('document.version', 90)
            ->assertJsonCount(3, 'document.paragraphs')
            ->assertJsonPath('document.paragraphs.1', 'Second para.');
    }

    public function test_reading_a_document_does_not_require_a_session(): void
    {
        // A user has to be able to read the terms before they have an account.
        $this->getJson('/api/legal/'.LegalDocument::KEY_PRIVACY)->assertOk();
    }

    public function test_a_user_can_accept_the_current_version_and_it_is_stored_with_their_ip(): void
    {
        [$user, $token] = $this->appUser();
        $document = LegalDocument::current(LegalDocument::KEY_PRIVACY);

        $this->withHeaders($this->headers($token))
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->postJson('/api/legal/'.LegalDocument::KEY_PRIVACY.'/accept', ['version' => $document->version])
            ->assertOk()
            ->assertJsonPath('accepted.version', $document->version);

        $acceptance = LegalAcceptance::where('user_id', $user->id)->firstOrFail();

        $this->assertSame($document->id, $acceptance->legal_document_id);
        $this->assertSame(LegalDocument::KEY_PRIVACY, $acceptance->document_key);
        $this->assertSame($document->version, (int) $acceptance->document_version);
        $this->assertSame('203.0.113.9', $acceptance->ip_address);
        $this->assertNotNull($acceptance->accepted_at);
    }

    public function test_accepting_a_stale_version_is_rejected_with_a_conflict(): void
    {
        [, $token] = $this->appUser();

        // The client is holding v1 while v2 is already live.
        $stale = LegalDocument::current(LegalDocument::KEY_PRIVACY);
        $current = $this->publish(LegalDocument::KEY_PRIVACY, $stale->version + 1);

        $this->withHeaders($this->headers($token))
            ->postJson('/api/legal/'.LegalDocument::KEY_PRIVACY.'/accept', ['version' => $stale->version])
            ->assertStatus(409)
            ->assertJsonPath('current_version', $current->version);

        $this->assertDatabaseCount('legal_acceptances', 0);
    }

    public function test_accepting_twice_does_not_duplicate_the_record(): void
    {
        [, $token] = $this->appUser();
        $version = LegalDocument::current(LegalDocument::KEY_PRIVACY)->version;

        foreach ([1, 2] as $attempt) {
            $this->withHeaders($this->headers($token))
                ->postJson('/api/legal/'.LegalDocument::KEY_PRIVACY.'/accept', ['version' => $version])
                ->assertOk();
        }

        $this->assertDatabaseCount('legal_acceptances', 1);
    }

    public function test_pending_lists_documents_the_user_has_not_accepted(): void
    {
        [, $token] = $this->appUser();
        $privacy = LegalDocument::current(LegalDocument::KEY_PRIVACY);

        $this->withHeaders($this->headers($token))
            ->getJson('/api/legal/pending/all')
            ->assertOk()
            ->assertJsonPath('has_pending', true)
            ->assertJsonCount(2, 'pending');

        $this->withHeaders($this->headers($token))
            ->postJson('/api/legal/'.LegalDocument::KEY_PRIVACY.'/accept', ['version' => $privacy->version]);

        $response = $this->withHeaders($this->headers($token))->getJson('/api/legal/pending/all');

        $response->assertOk()->assertJsonCount(1, 'pending');
        $this->assertSame(LegalDocument::KEY_TERMS, $response->json('pending.0.key'));
    }

    public function test_a_new_version_makes_a_previously_accepted_document_pending_again(): void
    {
        [$user, $token] = $this->appUser();
        $privacy = LegalDocument::current(LegalDocument::KEY_PRIVACY);

        $this->withHeaders($this->headers($token))
            ->postJson('/api/legal/'.LegalDocument::KEY_PRIVACY.'/accept', ['version' => $privacy->version])
            ->assertOk();

        $this->publish(LegalDocument::KEY_PRIVACY, $privacy->version + 1);

        $response = $this->withHeaders($this->headers($token))->getJson('/api/legal/pending/all');

        $this->assertContains(
            LegalDocument::KEY_PRIVACY,
            array_column($response->json('pending'), 'key'),
            'A newly published version must be re-consented, not inherited from the old one.'
        );

        // The old acceptance survives — it is the evidence for the old text.
        $this->assertDatabaseHas('legal_acceptances', [
            'user_id' => $user->id,
            'document_version' => $privacy->version,
        ]);
    }

    public function test_accepting_requires_authentication(): void
    {
        $this->postJson('/api/legal/'.LegalDocument::KEY_PRIVACY.'/accept', ['version' => 1])
            ->assertUnauthorized();
    }

    public function test_the_public_web_pages_render_the_published_text(): void
    {
        $this->publish(LegalDocument::KEY_TERMS, 77, 'Terms body for the web page.');

        $this->get('/terms')->assertOk()->assertSee('Terms body for the web page.');
        $this->get('/privacy-policy')->assertOk();
    }

    public function test_acceptances_export_is_a_csv_of_who_agreed_to_what(): void
    {
        [$user, $token] = $this->appUser();
        $version = LegalDocument::current(LegalDocument::KEY_PRIVACY)->version;

        $this->withHeaders($this->headers($token))
            ->postJson('/api/legal/'.LegalDocument::KEY_PRIVACY.'/accept', ['version' => $version]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.legal.acceptances.export', LegalDocument::KEY_PRIVACY));

        $response->assertOk();
        $body = $response->streamedContent();

        $this->assertStringContainsString($user->username, $body);
        $this->assertStringContainsString($user->email, $body);
        $this->assertStringContainsString('Accepted At', $body);
        $this->assertNotNull(
            ActivityLog::where('event_name', 'legal_acceptances_exported')->first(),
            'A compliance export is itself an auditable action.'
        );
    }
}
