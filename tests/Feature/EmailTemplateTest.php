<?php

namespace Tests\Feature;

use App\Mail\GlobalAppMail;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Services\EmailTemplates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailTemplateTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'status' => 'active']);
    }

    private function templates(): EmailTemplates
    {
        $service = app(EmailTemplates::class);
        $service->forgetCache();

        return $service;
    }

    public function test_seeded_templates_exist_for_every_built_in_default(): void
    {
        foreach (array_keys(EmailTemplates::DEFAULTS) as $key) {
            $this->assertDatabaseHas('email_templates', ['key' => $key, 'is_active' => true]);
        }
    }

    public function test_login_email_uses_the_admin_edited_wording(): void
    {
        Mail::fake();

        EmailTemplate::where('key', 'auth.login_code')->update([
            'subject' => 'Stylebite: your sign-in code',
            'heading' => 'Almost there',
            'body' => "Hi {{name}}, your code expires in {{expiry_minutes}} minutes.",
        ]);

        $this->templates();

        $user = User::factory()->create([
            'email' => 'editor@example.com',
            'full_name' => 'Ayesha',
            'password_hash' => bcrypt('password123'),
            'status' => 'active',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertOk();

        Mail::assertSent(GlobalAppMail::class, function (GlobalAppMail $mail): bool {
            return $mail->subjectLine === 'Stylebite: your sign-in code'
                && $mail->heading === 'Almost there'
                && str_contains($mail->contentText, 'Hi Ayesha')
                && str_contains($mail->contentText, '10 minutes')
                && $mail->highlightCode !== null;
        });
    }

    public function test_a_deactivated_template_falls_back_to_built_in_copy_so_login_still_works(): void
    {
        Mail::fake();

        // The dangerous case: an admin turns off the login-code template.
        EmailTemplate::where('key', 'auth.login_code')->update([
            'is_active' => false,
            'subject' => 'Should never be used',
        ]);

        $this->templates();

        $user = User::factory()->create([
            'email' => 'fallback@example.com',
            'password_hash' => bcrypt('password123'),
            'status' => 'active',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertOk()->assertJsonPath('requires_two_factor', true);

        Mail::assertSent(GlobalAppMail::class, fn (GlobalAppMail $mail) => $mail->subjectLine === 'Your Stylebite login code'
            && $mail->highlightCode !== null);
    }

    public function test_a_blank_template_body_also_falls_back(): void
    {
        EmailTemplate::where('key', 'auth.verify_email')->update(['body' => '   ']);

        $copy = $this->templates()->render('auth.verify_email', ['expiry_minutes' => 15]);

        $this->assertSame('Your Stylebite verification code', $copy['subject']);
        $this->assertStringContainsString('Thanks for registering', $copy['body']);
    }

    public function test_unknown_placeholders_are_stripped_rather_than_leaking_braces(): void
    {
        EmailTemplate::where('key', 'contest.announcement')->update([
            'subject' => 'Join {{contest_title}}',
            'body' => 'Hello {{name}}, prize is {{nonexistent_field}} today.',
        ]);

        $copy = $this->templates()->render('contest.announcement', [
            'name' => 'Sara',
            'contest_title' => 'Winter Looks',
        ]);

        $this->assertSame('Join Winter Looks', $copy['subject']);
        $this->assertStringContainsString('Hello Sara', $copy['body']);
        $this->assertStringNotContainsString('{{', $copy['body']);
        $this->assertStringNotContainsString('nonexistent_field', $copy['body']);
    }

    public function test_admin_can_edit_a_template_and_the_change_takes_effect_immediately(): void
    {
        $admin = $this->admin();
        $template = EmailTemplate::where('key', 'auth.password_reset')->sole();

        $this->actingAs($admin)
            ->put(route('admin.email_templates.update', $template), [
                'subject' => 'Reset your Stylebite password',
                'heading' => 'Reset requested',
                'body' => 'Use the code below within {{expiry_minutes}} minutes.',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.email_templates.edit', $template));

        // Cache must be busted by the save, not by a later request.
        $copy = app(EmailTemplates::class)->render('auth.password_reset', ['expiry_minutes' => 15]);

        $this->assertSame('Reset your Stylebite password', $copy['subject']);
        $this->assertStringContainsString('within 15 minutes', $copy['body']);

        $this->assertDatabaseHas('activity_logs', [
            'event_name' => 'email_template_updated',
            'entity_id' => $template->id,
        ]);
    }

    public function test_admin_can_restore_the_built_in_wording(): void
    {
        $admin = $this->admin();
        $template = EmailTemplate::where('key', 'auth.verify_email')->sole();

        $template->update(['subject' => 'Mangled', 'body' => 'Mangled body']);

        $this->actingAs($admin)
            ->patch(route('admin.email_templates.reset', $template))
            ->assertRedirect();

        $fresh = $template->fresh();
        $this->assertSame(EmailTemplates::DEFAULTS['auth.verify_email']['subject'], $fresh->subject);
        $this->assertTrue($fresh->is_active);
    }

    public function test_test_send_delivers_the_template_to_the_admin(): void
    {
        Mail::fake();

        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
            'email' => 'boss@example.com',
        ]);

        $template = EmailTemplate::where('key', 'contest.ending_soon')->sole();

        $this->actingAs($admin)
            ->post(route('admin.email_templates.test_send', $template))
            ->assertRedirect()
            ->assertSessionHas('status', fn ($message) => str_contains($message, 'boss@example.com'));

        Mail::assertSent(GlobalAppMail::class, fn (GlobalAppMail $mail) => $mail->hasTo('boss@example.com'));
    }

    public function test_staff_without_the_permission_cannot_reach_templates(): void
    {
        $moderator = User::factory()->create(['role' => 'moderator', 'status' => 'active']);

        $this->actingAs($moderator)
            ->get(route('admin.email_templates.index'))
            ->assertForbidden();
    }
}
