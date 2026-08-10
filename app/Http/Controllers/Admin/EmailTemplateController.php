<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\EmailTemplate;
use App\Services\EmailTemplates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailTemplateController extends Controller
{
    public function __construct(private readonly EmailTemplates $templates)
    {
    }

    public function index(Request $request): View
    {
        $templates = EmailTemplate::query()
            ->with('editor:id,username,full_name')
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')))
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('key', 'like', "%{$search}%")
                        ->orWhere('subject', 'like', "%{$search}%");
                });
            })
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return view('admin.notifications.EmailTemplatesPage', compact('templates'));
    }

    public function edit(EmailTemplate $emailTemplate): View
    {
        return view('admin.notifications.EmailTemplateFormPage', [
            'template' => $emailTemplate,
            'placeholders' => $this->templates->availablePlaceholders($emailTemplate->category),
            'defaultCopy' => EmailTemplates::DEFAULTS[$emailTemplate->key] ?? null,
            'preview' => $this->templates->render($emailTemplate->key, $this->sampleVariables()),
        ]);
    }

    public function update(Request $request, EmailTemplate $emailTemplate): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:191'],
            'heading' => ['required', 'string', 'max:191'],
            'body' => ['required', 'string', 'max:5000'],
            'action_text' => ['nullable', 'string', 'max:60'],
            'action_url' => ['nullable', 'string', 'max:1024'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $emailTemplate->fill([
            'subject' => $data['subject'],
            'heading' => $data['heading'],
            'body' => $data['body'],
            'action_text' => filled($data['action_text'] ?? null) ? $data['action_text'] : null,
            'action_url' => filled($data['action_url'] ?? null) ? $data['action_url'] : null,
            'is_active' => $request->boolean('is_active'),
            'updated_by_user_id' => auth()->id(),
        ])->save();

        $this->templates->forgetCache();

        $this->logActivity('email_template_updated', $emailTemplate->id, [
            'key' => $emailTemplate->key,
            'is_active' => $emailTemplate->is_active,
        ]);

        return redirect()
            ->route('admin.email_templates.edit', $emailTemplate)
            ->with('status', $emailTemplate->is_active
                ? 'Template saved.'
                : 'Template saved and deactivated — the built-in wording will be used until you turn it back on.');
    }

    /**
     * Restore the built-in copy. The safety net exists in code, so this is how
     * an admin recovers from an edit they regret.
     */
    public function reset(EmailTemplate $emailTemplate): RedirectResponse
    {
        $default = EmailTemplates::DEFAULTS[$emailTemplate->key] ?? null;

        if (! $default) {
            return back()->with('status', 'This template has no built-in default to restore.');
        }

        $emailTemplate->fill([
            'subject' => $default['subject'],
            'heading' => $default['heading'],
            'body' => $default['body'],
            'action_text' => $default['action_text'],
            'action_url' => $default['action_url'],
            'is_active' => true,
            'updated_by_user_id' => auth()->id(),
        ])->save();

        $this->templates->forgetCache();

        $this->logActivity('email_template_reset', $emailTemplate->id, ['key' => $emailTemplate->key]);

        return back()->with('status', 'Template restored to its built-in wording.');
    }

    /**
     * Send the template to the signed-in admin with sample data, so copy can be
     * checked in a real inbox before users receive it.
     */
    public function testSend(Request $request, EmailTemplate $emailTemplate): RedirectResponse
    {
        $admin = $request->user();

        if (! $admin->email) {
            return back()->with('status', 'Your admin account has no email address to send a test to.');
        }

        try {
            $this->templates->send(
                $emailTemplate->key,
                $admin->email,
                $admin->full_name ?: $admin->username,
                $this->sampleVariables(),
                highlightCode: '123456'
            );
        } catch (\Throwable $exception) {
            return back()->with('status', 'Test send failed: '.\Illuminate\Support\Str::limit($exception->getMessage(), 160));
        }

        $this->logActivity('email_template_test_sent', $emailTemplate->id, [
            'key' => $emailTemplate->key,
            'to' => $admin->email,
        ]);

        return back()->with('status', "Test email sent to {$admin->email}.");
    }

    private function sampleVariables(): array
    {
        return [
            'username' => 'sample_user',
            'expiry_minutes' => 15,
            'contest_title' => 'Winter Street Style',
            'contest_ends_at' => now()->addDays(2)->format('M d, Y H:i'),
        ];
    }

    private function logActivity(string $eventName, ?int $entityId, array $metadata = []): void
    {
        ActivityLog::create([
            'user_id' => auth()->id(),
            'actor_type' => 'admin',
            'event_name' => $eventName,
            'entity_type' => 'email_template',
            'entity_id' => $entityId,
            'metadata_json' => $metadata ?: null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }

    public static function tabCount(): int
    {
        return EmailTemplate::count();
    }
}
