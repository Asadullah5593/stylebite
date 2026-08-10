<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Bulk data export is its own permission, separate from users.view.
 *
 * Looking one account up to help its owner and downloading a spreadsheet of
 * every user's email address are not the same act, and neither is dumping one
 * person's entire history as JSON. Both exports previously sat behind users.view,
 * which support_agent and finance_manager hold — so the whole user table was one
 * click away from roles explicitly designed not to have that reach.
 *
 * Export is a compliance function, so it stays with the administrators who answer
 * for it.
 */
return new class extends Migration
{
    private const PERMISSION = 'users.export';

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
