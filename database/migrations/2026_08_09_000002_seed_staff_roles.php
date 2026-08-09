<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Job-shaped staff roles requested by the client. These are panel roles
     * only — they intentionally have no users.role enum counterpart, so an
     * account holding one keeps its app-side account type (user/creator) while
     * gaining scoped admin access.
     */
    private const ROLES = [
        // Everything, including roles and system tooling. Locked in the UI, so
        // it stays a true full-access role.
        'super_admin' => '*',

        // Content + reports. Reels are posts with video in this schema, so
        // posts.* covers them; banning a reported account happens through the
        // reports queue (moderation.manage), not the user list.
        'content_moderator' => [
            'dashboard.view',
            'search.view',
            'users.view',
            'posts.view',
            'posts.update',
            'posts.moderate',
            'comments.view',
            'comments.update',
            'memories.view',
            'memories.update',
            'media.view',
            'engagement.view',
            'moderation.view',
            'moderation.manage',
            'activity.view',
        ],

        // Contest lifecycle end to end: create, edit, participants,
        // submissions, recalculation and declaring winners (all contests.update),
        // plus announcements for publishing results.
        'contest_manager' => [
            'dashboard.view',
            'search.view',
            'users.view',
            'posts.view',
            'contests.view',
            'contests.create',
            'contests.update',
            'notifications.view',
            'notifications.send',
        ],

        // Wallets, transactions, reversals, withdrawals, reconciliation.
        'finance_manager' => [
            'dashboard.view',
            'search.view',
            'users.view',
            'earnings.view',
            'earnings.manage',
            'activity.view',
        ],

        // User support: look accounts up and fix them (revoke a stuck session,
        // expire a password reset, disable a device, restore a streak, badges)
        // and read conversations to investigate a complaint. Deliberately no
        // ban/suspend, no delete, no money.
        'support_agent' => [
            'dashboard.view',
            'search.view',
            'users.view',
            'users.update',
            'posts.view',
            'memories.view',
            'messaging.view',
            'notifications.view',
            'moderation.view',
        ],
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $allPermissions = Permission::query()
            ->where('guard_name', 'web')
            ->pluck('name')
            ->all();

        foreach (self::ROLES as $name => $permissions) {
            $role = Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);

            $role->syncPermissions($permissions === '*' ? $allPermissions : $permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::whereIn('name', array_keys(self::ROLES))
            ->where('guard_name', 'web')
            ->get()
            ->each(fn (Role $role) => $role->delete());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
