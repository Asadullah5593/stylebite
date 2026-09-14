<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Deleting a post is its own permission, separate from posts.moderate.
 *
 * Moderation takes a post down but leaves it in the list, reviewable and
 * reversible by anyone who can moderate. Deleting removes it from every feed,
 * profile and admin list at once and takes its day back out of the author's
 * streak — the same class of act as deleting an account, so it sits with the
 * same roles that hold users.delete rather than with the moderators.
 */
return new class extends Migration
{
    private const PERMISSION = 'posts.delete';

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => self::PERMISSION, 'guard_name' => 'web']);

        foreach (['admin', 'super_admin'] as $roleName) {
            Role::query()
                ->where('name', $roleName)
                ->where('guard_name', 'web')
                ->first()
                ?->givePermissionTo(self::PERMISSION);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::where('name', self::PERMISSION)->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
