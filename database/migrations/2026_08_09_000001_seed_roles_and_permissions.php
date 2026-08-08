<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Permission catalog, module.action style (mirrors the reference project).
     * One entry per admin-panel capability; new permissions get their own
     * migration later, never an edit to this one.
     */
    private const PERMISSIONS = [
        'dashboard.view',
        'search.view',

        'users.view',
        'users.create',
        'users.update',
        'users.delete',
        'users.moderate',

        'roles.view',
        'roles.create',
        'roles.update',
        'roles.delete',

        'social.view',
        'social.manage',

        'comments.view',
        'comments.update',

        'engagement.view',
        'media.view',

        'memories.view',
        'memories.update',

        'posts.view',
        'posts.update',
        'posts.moderate',

        'messaging.view',
        'messaging.update',

        'notifications.view',
        'notifications.send',

        'moderation.view',
        'moderation.manage',

        'activity.view',

        'contests.view',
        'contests.create',
        'contests.update',

        'earnings.view',
        'earnings.manage',

        'settings.access',
        'system.access',
    ];

    /**
     * What a moderator can do out of the box: read most things, act on
     * content and accounts, stay out of money, roles, and system settings.
     */
    private const MODERATOR_PERMISSIONS = [
        'dashboard.view',
        'search.view',
        'users.view',
        'users.moderate',
        'social.view',
        'comments.view',
        'comments.update',
        'engagement.view',
        'media.view',
        'memories.view',
        'posts.view',
        'posts.update',
        'posts.moderate',
        'messaging.view',
        'notifications.view',
        'moderation.view',
        'moderation.manage',
        'activity.view',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // Roles mirror the users.role enum so every existing account maps 1:1.
        // creator/user carry no panel permissions — they exist so the role
        // picker and future app-side permissions have somewhere to live.
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $moderator = Role::firstOrCreate(['name' => 'moderator', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'creator', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

        $admin->syncPermissions(self::PERMISSIONS);
        $moderator->syncPermissions(self::MODERATOR_PERMISSIONS);

        // Backfill: give existing panel-capable accounts their Spatie role in
        // one INSERT…SELECT per role — no per-user loop. Regular/creator
        // accounts get no pivot rows; the users.role column still labels them.
        foreach (['admin' => $admin, 'moderator' => $moderator] as $enumRole => $role) {
            DB::table('model_has_roles')->insertUsing(
                ['role_id', 'model_type', 'model_id'],
                DB::table('users')
                    ->where('role', $enumRole)
                    ->whereNotIn('id', DB::table('model_has_roles')
                        ->where('role_id', $role->id)
                        ->where('model_type', User::class)
                        ->select('model_id'))
                    ->selectRaw('?, ?, id', [$role->id, User::class])
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::whereIn('name', ['admin', 'moderator', 'creator', 'user'])
            ->where('guard_name', 'web')
            ->get()
            ->each(fn (Role $role) => $role->delete());

        Permission::whereIn('name', self::PERMISSIONS)
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
