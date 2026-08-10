<?php

use App\Models\LegalDocument;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSIONS = ['legal.view', 'legal.manage'];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // Publishing legal text is an admin-level responsibility.
        foreach (['admin', 'super_admin'] as $roleName) {
            Role::query()->where('name', $roleName)->where('guard_name', 'web')->first()?->givePermissionTo(self::PERMISSIONS);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // A published placeholder for each document, so the admin screens and the
        // API have something coherent to show immediately. The Privacy Policy
        // keeps its existing hardcoded page as the public fallback until real
        // text is pasted in; Terms has never existed, so it says so plainly
        // rather than pretending to be a policy.
        $placeholders = [
            LegalDocument::KEY_PRIVACY => [
                'title' => 'Privacy Policy',
                'body' => "This document has not been finalised in the admin panel yet.\n\n"
                    ."Until it is published here, the Privacy Policy shown on the website is the previously written version. "
                    ."Replace this text with the current policy and publish it to make this the authoritative version.",
            ],
            LegalDocument::KEY_TERMS => [
                'title' => 'Terms of Service',
                'body' => "Stylebite does not have a Terms of Service document yet.\n\n"
                    ."This placeholder exists so the page and the mobile app have something to load. "
                    ."Paste the terms supplied by your legal advisor and publish them.",
            ],
        ];

        foreach ($placeholders as $key => $content) {
            $exists = DB::table('legal_documents')->where('key', $key)->exists();

            if ($exists) {
                continue;
            }

            DB::table('legal_documents')->insert([
                'key' => $key,
                'version' => 1,
                'title' => $content['title'],
                'body' => $content['body'],
                'summary_of_changes' => 'Initial placeholder created during setup.',
                'is_published' => true,
                'published_at' => now(),
                'requires_reacceptance' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::whereIn('name', self::PERMISSIONS)->where('guard_name', 'web')->delete();
        DB::table('legal_documents')->where('summary_of_changes', 'Initial placeholder created during setup.')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
