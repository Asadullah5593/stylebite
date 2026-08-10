<?php

namespace Tests\Feature;

use App\Models\EmailTemplate;
use App\Models\User;
use App\Services\EmailTemplates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Smoke test: every admin page must actually render.
 *
 * This exists because a Blade compile error shipped to production — the
 * template editor 500'd on a nested {{ '{{' }} expression. Every other test
 * exercised that controller through POST/PUT and never rendered the view, so
 * nothing caught it. Asserting these pages return 200 is cheap and closes the
 * whole class of "the controller works but the view is broken" bug.
 */
class AdminPageRenderTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'status' => 'active']);
    }

    public function test_every_email_template_edit_page_renders(): void
    {
        $admin = $this->admin();

        // Derived from the built-in set so adding a template doesn't break this.
        $templates = EmailTemplate::all();
        $this->assertCount(
            count(EmailTemplates::DEFAULTS),
            $templates,
            'Every built-in template should be seeded.'
        );

        foreach ($templates as $template) {
            $this->actingAs($admin)
                ->get(route('admin.email_templates.edit', $template))
                ->assertOk()
                ->assertSee($template->name, false);
        }
    }

    public function test_every_role_edit_page_renders_including_locked_roles(): void
    {
        $admin = $this->admin();

        foreach (Role::where('guard_name', 'web')->get() as $role) {
            $this->actingAs($admin)
                ->get(route('admin.roles.edit', $role))
                ->assertOk();
        }
    }

    /**
     * @return array<int, array<int, string>>
     */
    public static function adminPageProvider(): array
    {
        return [
            'dashboard' => ['admin.dashboard'],
            'users list' => ['admin.users.all_users'],
            'user create' => ['admin.users.create'],
            'roles list' => ['admin.roles.index'],
            'role create' => ['admin.roles.create'],
            'notifications' => ['admin.notifications.notifications'],
            'campaigns' => ['admin.notifications.campaigns'],
            'email templates' => ['admin.email_templates.index'],
            'push logs' => ['admin.notifications.push_logs'],
            'moderation reports' => ['admin.moderation.reports'],
            'moderation actions' => ['admin.moderation.actions'],
            'activity logs' => ['admin.activity.activity_logs'],
            'social graph' => ['admin.social.follows'],
            'posts' => ['admin.posts.all_posts'],
            'comments' => ['admin.comments.comments'],
            'memories' => ['admin.memories.memories'],
            'contests' => ['admin.contests.contests'],
            'earnings' => ['admin.earnings.wallets'],
            'settings' => ['admin.settings.configs'],
            'system health' => ['admin.system_health'],
        ];
    }

    #[DataProvider('adminPageProvider')]
    public function test_admin_page_renders(string $routeName): void
    {
        $this->actingAs($this->admin())
            ->get(route($routeName))
            ->assertOk();
    }
}
