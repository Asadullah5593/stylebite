<?php

use App\Services\EmailTemplates;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSIONS = [
        'email_templates.view',
        'email_templates.manage',
    ];

    public function up(): void
    {
        // Seed the built-in copy so admins have something to edit. The code
        // keeps its own copy as a fallback, so these rows are convenience, not
        // a dependency.
        foreach (EmailTemplates::DEFAULTS as $key => $template) {
            DB::table('email_templates')->updateOrInsert(
                ['key' => $key],
                [
                    'name' => $template['name'],
                    'category' => $template['category'],
                    'description' => $template['description'],
                    'subject' => $template['subject'],
                    'heading' => $template['heading'],
                    'body' => $template['body'],
                    'action_text' => $template['action_text'],
                    'action_url' => $template['action_url'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // Editing user-facing email copy is an admin-level job; the job-shaped
        // staff roles deliberately do not get it.
        foreach (['admin', 'super_admin'] as $roleName) {
            $role = Role::query()->where('name', $roleName)->where('guard_name', 'web')->first();

            $role?->givePermissionTo(self::PERMISSIONS);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::whereIn('name', self::PERMISSIONS)->where('guard_name', 'web')->delete();

        DB::table('email_templates')->whereIn('key', array_keys(EmailTemplates::DEFAULTS))->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
