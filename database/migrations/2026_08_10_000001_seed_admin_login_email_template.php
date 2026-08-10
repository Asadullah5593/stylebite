<?php

use App\Services\EmailTemplates;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const KEY = 'auth.admin_login_code';

    public function up(): void
    {
        $template = EmailTemplates::DEFAULTS[self::KEY];

        DB::table('email_templates')->updateOrInsert(
            ['key' => self::KEY],
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

    public function down(): void
    {
        DB::table('email_templates')->where('key', self::KEY)->delete();
    }
};
