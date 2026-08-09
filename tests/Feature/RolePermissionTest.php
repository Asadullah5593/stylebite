<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'status' => 'active']);
    }

    private function moderator(): User
    {
        return User::factory()->create(['role' => 'moderator', 'status' => 'active']);
    }

    public function test_admin_role_has_full_panel_access(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('admin.settings.configs'))->assertOk();
        $this->actingAs($admin)->get(route('admin.roles.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.earnings.wallets'))->assertOk();
    }

    public function test_moderator_gets_moderation_but_not_money_roles_or_settings(): void
    {
        $moderator = $this->moderator();

        $this->actingAs($moderator)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($moderator)->get(route('admin.moderation.reports'))->assertOk();
        $this->actingAs($moderator)->get(route('admin.users.all_users'))->assertOk();

        $this->actingAs($moderator)->get(route('admin.settings.configs'))->assertForbidden();
        $this->actingAs($moderator)->get(route('admin.earnings.wallets'))->assertForbidden();
        $this->actingAs($moderator)->get(route('admin.roles.index'))->assertForbidden();
        $this->actingAs($moderator)->get(route('admin.notifications.push_logs'))->assertOk();
    }

    public function test_moderator_can_moderate_users_but_not_delete_or_create_them(): void
    {
        $moderator = $this->moderator();
        $target = User::factory()->create(['status' => 'active']);

        $this->actingAs($moderator)
            ->patch(route('admin.users.status', $target), [
                'action' => 'ban',
                'reason' => 'Moderator ban',
            ])
            ->assertRedirect();

        $this->assertSame('banned', $target->fresh()->status);

        $this->actingAs($moderator)
            ->delete(route('admin.users.destroy', $target))
            ->assertForbidden();

        $this->actingAs($moderator)
            ->get(route('admin.users.create'))
            ->assertForbidden();
    }

    public function test_user_without_permissions_cannot_enter_the_panel(): void
    {
        $user = User::factory()->create(['role' => 'user', 'status' => 'active']);

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_user_without_permissions_cannot_log_in_to_panel(): void
    {
        User::factory()->create([
            'email' => 'plain@example.com',
            'password_hash' => bcrypt('secret-password'),
            'role' => 'user',
            'status' => 'active',
        ]);

        $this->post('/admin/login', [
            'email' => 'plain@example.com',
            'password' => 'secret-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_moderator_can_log_in_to_panel(): void
    {
        User::factory()->create([
            'email' => 'mod@example.com',
            'password_hash' => bcrypt('secret-password'),
            'role' => 'moderator',
            'status' => 'active',
        ]);

        $this->post('/admin/login', [
            'email' => 'mod@example.com',
            'password' => 'secret-password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticated();
    }

    public function test_super_admin_email_bypasses_all_permission_checks(): void
    {
        config(['auth.super_admins' => 'boss@example.com, other@example.com']);

        $user = User::factory()->create([
            'email' => 'boss@example.com',
            'role' => 'user',
            'status' => 'active',
        ]);

        $this->assertTrue($user->canAccessAdminPanel());

        $this->actingAs($user)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($user)->get(route('admin.settings.configs'))->assertOk();
        $this->actingAs($user)->get(route('admin.roles.index'))->assertOk();
    }

    public function test_admin_can_create_custom_role_that_grants_scoped_access(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.roles.store'), [
                'name' => 'support',
                'permissions' => ['dashboard.view', 'users.view'],
            ])
            ->assertRedirect(route('admin.roles.index'));

        $this->assertDatabaseHas('roles', ['name' => 'support', 'guard_name' => 'web']);

        $staff = User::factory()->create(['status' => 'active']);
        $staff->assignRole('support');

        $this->actingAs($staff)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($staff)->get(route('admin.users.all_users'))->assertOk();
        $this->actingAs($staff)->get(route('admin.posts.all_posts'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.moderation.reports'))->assertForbidden();
    }

    public function test_locked_admin_role_cannot_be_edited_and_seeded_roles_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $adminRole = Role::findByName('admin', 'web');
        $moderatorRole = Role::findByName('moderator', 'web');

        $this->actingAs($admin)
            ->put(route('admin.roles.update', $adminRole), [
                'name' => 'admin',
                'permissions' => ['dashboard.view'],
            ])
            ->assertRedirect();

        $this->assertSame(36, $adminRole->fresh()->permissions()->count());

        $this->actingAs($admin)
            ->delete(route('admin.roles.destroy', $moderatorRole))
            ->assertRedirect();

        $this->assertDatabaseHas('roles', ['name' => 'moderator', 'guard_name' => 'web']);
    }

    public function test_role_with_assigned_users_cannot_be_deleted(): void
    {
        $admin = $this->admin();

        $role = Role::create(['name' => 'temp-role', 'guard_name' => 'web']);
        $role->givePermissionTo('users.view');

        $staff = User::factory()->create(['status' => 'active']);
        $staff->assignRole('temp-role');

        $this->actingAs($admin)
            ->delete(route('admin.roles.destroy', $role))
            ->assertRedirect();

        $this->assertDatabaseHas('roles', ['name' => 'temp-role']);

        $staff->removeRole('temp-role');

        $this->actingAs($admin)
            ->delete(route('admin.roles.destroy', $role->fresh()))
            ->assertRedirect(route('admin.roles.index'));

        $this->assertDatabaseMissing('roles', ['name' => 'temp-role']);
    }

    public function test_editing_a_user_syncs_spatie_role_and_enum_mirror(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['role' => 'user', 'status' => 'active']);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $target), [
                'username' => $target->username,
                'email' => $target->email,
                'role' => 'moderator',
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.users.show', $target));

        $fresh = $target->fresh();
        $this->assertTrue($fresh->hasRole('moderator'));
        $this->assertFalse($fresh->hasRole('user'));
        $this->assertSame('moderator', $fresh->role);

        $this->assertDatabaseHas('activity_logs', [
            'event_name' => 'user_role_updated',
            'entity_id' => $target->id,
        ]);
    }

    /**
     * @return array<string, array{0: string, 1: array<int, string>, 2: array<int, string>}>
     */
    public static function staffRoleMatrix(): array
    {
        return [
            'super admin' => ['super_admin', [
                'admin.dashboard', 'admin.settings.configs', 'admin.roles.index',
                'admin.earnings.wallets', 'admin.moderation.reports', 'admin.contests.contests',
                'admin.system_health',
            ], []],
            'content moderator' => ['content_moderator', [
                'admin.dashboard', 'admin.posts.all_posts', 'admin.comments.comments',
                'admin.moderation.reports', 'admin.users.all_users', 'admin.activity.activity_logs',
            ], [
                'admin.earnings.wallets', 'admin.contests.contests', 'admin.settings.configs',
                'admin.roles.index', 'admin.users.create',
            ]],
            'contest manager' => ['contest_manager', [
                'admin.dashboard', 'admin.contests.contests', 'admin.contests.create',
                'admin.contests.participants', 'admin.contests.submissions', 'admin.users.all_users',
            ], [
                'admin.earnings.wallets', 'admin.moderation.reports', 'admin.settings.configs',
                'admin.roles.index', 'admin.comments.comments',
            ]],
            'finance manager' => ['finance_manager', [
                'admin.dashboard', 'admin.earnings.wallets', 'admin.earnings.transactions',
                'admin.earnings.withdrawals', 'admin.earnings.reconciliation', 'admin.users.all_users',
            ], [
                'admin.moderation.reports', 'admin.contests.contests', 'admin.settings.configs',
                'admin.roles.index', 'admin.posts.all_posts',
            ]],
            'support agent' => ['support_agent', [
                'admin.dashboard', 'admin.users.all_users', 'admin.users.sessions',
                'admin.messaging.messages', 'admin.moderation.reports', 'admin.posts.all_posts',
            ], [
                'admin.earnings.wallets', 'admin.contests.contests', 'admin.settings.configs',
                'admin.roles.index', 'admin.users.create', 'admin.activity.activity_logs',
            ]],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('staffRoleMatrix')]
    public function test_staff_role_reaches_only_its_own_pages(string $roleName, array $allowed, array $denied): void
    {
        $staff = User::factory()->create(['role' => 'user', 'status' => 'active']);
        $staff->assignRole($roleName);

        foreach ($allowed as $routeName) {
            $this->actingAs($staff)
                ->get(route($routeName))
                ->assertOk("{$roleName} should reach {$routeName}");
        }

        foreach ($denied as $routeName) {
            $this->actingAs($staff)
                ->get(route($routeName))
                ->assertForbidden("{$roleName} should NOT reach {$routeName}");
        }
    }

    public function test_content_moderator_can_ban_through_reports_but_not_the_user_list(): void
    {
        $staff = User::factory()->create(['status' => 'active']);
        $staff->assignRole('content_moderator');

        $target = User::factory()->create(['status' => 'active']);

        $report = \App\Models\Report::create([
            'reporter_user_id' => User::factory()->create()->id,
            'target_type' => 'user',
            'target_id' => $target->id,
            'reason' => 'harassment',
            'status' => 'open',
        ]);

        $this->actingAs($staff)
            ->patch(route('admin.moderation.reports.target.update', $report), [
                'action' => 'ban',
                'reason' => 'Banned from the reports queue',
            ])
            ->assertRedirect();

        $this->assertSame('banned', $target->fresh()->status);

        // No users.moderate, so the user-list lifecycle endpoint stays closed.
        $other = User::factory()->create(['status' => 'active']);

        $this->actingAs($staff)
            ->patch(route('admin.users.status', $other), [
                'action' => 'ban',
                'reason' => 'Should not work',
            ])
            ->assertForbidden();

        $this->assertSame('active', $other->fresh()->status);
    }

    public function test_support_agent_can_fix_accounts_but_not_ban_or_delete(): void
    {
        $staff = User::factory()->create(['status' => 'active']);
        $staff->assignRole('support_agent');

        $target = User::factory()->create(['status' => 'active']);

        // users.update covers the support toolkit (badges, sessions, streaks).
        $this->actingAs($staff)
            ->patch(route('admin.users.badge.verified', $target))
            ->assertRedirect();

        $this->actingAs($staff)
            ->patch(route('admin.users.status', $target), [
                'action' => 'suspend',
                'reason' => 'Should not work',
                'duration_hours' => 24,
            ])
            ->assertForbidden();

        $this->actingAs($staff)
            ->delete(route('admin.users.destroy', $target))
            ->assertForbidden();

        $this->assertSame('active', $target->fresh()->status);
    }

    public function test_finance_manager_can_action_withdrawals_and_content_moderator_cannot(): void
    {
        $finance = User::factory()->create(['status' => 'active']);
        $finance->assignRole('finance_manager');

        $moderator = User::factory()->create(['status' => 'active']);
        $moderator->assignRole('content_moderator');

        $this->actingAs($finance)->get(route('admin.earnings.withdrawals'))->assertOk();
        $this->actingAs($moderator)->get(route('admin.earnings.withdrawals'))->assertForbidden();
    }

    public function test_super_admin_role_is_locked_from_permission_edits(): void
    {
        $admin = $this->admin();
        $superAdminRole = Role::findByName('super_admin', 'web');

        $this->actingAs($admin)
            ->put(route('admin.roles.update', $superAdminRole), [
                'name' => 'super_admin',
                'permissions' => ['dashboard.view'],
            ])
            ->assertRedirect();

        $this->assertSame(36, $superAdminRole->fresh()->permissions()->count());

        $this->actingAs($admin)
            ->delete(route('admin.roles.destroy', $superAdminRole))
            ->assertRedirect();

        $this->assertDatabaseHas('roles', ['name' => 'super_admin', 'guard_name' => 'web']);
    }

    public function test_staff_roles_do_not_change_the_app_account_type(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'username' => 'finance_staff',
                'email' => 'finance@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => 'finance_manager',
                'status' => 'active',
            ])
            ->assertRedirect();

        $staff = User::query()->where('email', 'finance@example.com')->firstOrFail();

        $this->assertTrue($staff->hasRole('finance_manager'));
        // Panel-only role: the app-side account type stays a regular user.
        $this->assertSame('user', $staff->role);
        $this->assertTrue($staff->canAccessAdminPanel());
    }

    public function test_admin_cannot_change_own_role_from_edit_form(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put(route('admin.users.update', $admin), [
                'username' => $admin->username,
                'email' => $admin->email,
                'role' => 'user',
                'status' => 'active',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'You cannot change your own role or account status from the edit form.');

        $this->assertTrue($admin->fresh()->hasRole('admin'));
    }
}
