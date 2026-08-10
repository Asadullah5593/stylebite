<?php

namespace App\Services;

use App\Models\EmailTemplate;
use Illuminate\Support\Facades\Cache;

/**
 * Admin-editable email copy, with the built-in wording as a safety net.
 *
 * The important property here is that a bad edit can never stop a critical
 * email. Every template has a hardcoded default below; if the row is missing,
 * deactivated, or blank, the default is used and the mail still goes out. That
 * matters most for auth.login_code — an admin deleting that template must not
 * lock every user out of the app.
 *
 * Placeholders are {{snake_case}} and unknown ones are stripped, so a typo in
 * the admin editor leaves clean copy rather than literal braces in the email.
 */
class EmailTemplates
{
    public const CATEGORY_TRANSACTIONAL = 'transactional';

    public const CATEGORY_CONTEST = 'contest';

    public const CATEGORY_ANNOUNCEMENT = 'announcement';

    private const CACHE_KEY = 'stylebite_email_templates';

    /**
     * Built-in copy. Also seeded into the database so admins have something to
     * edit, but kept here as the fallback of record.
     */
    public const DEFAULTS = [
        'auth.verify_email' => [
            'name' => 'Email verification code',
            'category' => self::CATEGORY_TRANSACTIONAL,
            'description' => 'Sent at registration and when a user asks for a new verification code.',
            'subject' => 'Your Stylebite verification code',
            'heading' => 'Verify your email',
            'body' => "Thanks for registering with Stylebite.\n\nEnter this 6-digit code to verify your email. It expires in {{expiry_minutes}} minutes.\n\nIf you didn't create this account, you can safely ignore this email.",
            'action_text' => null,
            'action_url' => null,
        ],
        'auth.login_code' => [
            'name' => 'Login two-factor code',
            'category' => self::CATEGORY_TRANSACTIONAL,
            'description' => 'Sent on every password login. Users cannot sign in without it.',
            'subject' => 'Your Stylebite login code',
            'heading' => 'Confirm your login',
            'body' => "Someone just signed in to your Stylebite account with your password.\n\nEnter this 6-digit code to finish logging in. It expires in {{expiry_minutes}} minutes.\n\nIf this wasn't you, reset your password right away.",
            'action_text' => null,
            'action_url' => null,
        ],
        'auth.password_reset' => [
            'name' => 'Password reset code',
            'category' => self::CATEGORY_TRANSACTIONAL,
            'description' => 'Sent when a user requests a password reset.',
            'subject' => 'Your Stylebite password reset code',
            'heading' => 'Password reset request',
            'body' => "We received a request to reset your Stylebite password.\n\nEnter this 6-digit code to reset your password. It expires in {{expiry_minutes}} minutes.\n\nIf you didn't request this, you can safely ignore this email.",
            'action_text' => null,
            'action_url' => null,
        ],
        'contest.announcement' => [
            'name' => 'Contest announcement',
            'category' => self::CATEGORY_CONTEST,
            'description' => 'General-purpose contest announcement. Placeholders: {{contest_title}}, {{contest_ends_at}}, {{name}}.',
            'subject' => 'New on Stylebite: {{contest_title}}',
            'heading' => '{{contest_title}} is live',
            'body' => "Hi {{name}},\n\n{{contest_title}} is now open on Stylebite. Entries close {{contest_ends_at}}.\n\nOpen the app to join and share your look.",
            'action_text' => 'Open Stylebite',
            'action_url' => null,
        ],
        'contest.ending_soon' => [
            'name' => 'Contest ending soon',
            'category' => self::CATEGORY_CONTEST,
            'description' => 'Reminder that a contest is about to close. Placeholders: {{contest_title}}, {{contest_ends_at}}, {{name}}.',
            'subject' => '{{contest_title}} closes soon',
            'heading' => 'Last chance to enter',
            'body' => "Hi {{name}},\n\n{{contest_title}} closes {{contest_ends_at}}. If you have not submitted your look yet, now is the moment.\n\nOpen the app to finish your entry.",
            'action_text' => 'Open Stylebite',
            'action_url' => null,
        ],
        'contest.winner' => [
            'name' => 'Contest winner announcement',
            'category' => self::CATEGORY_CONTEST,
            'description' => 'Sent to the winner of a contest. Placeholders: {{contest_title}}, {{name}}.',
            'subject' => 'You won {{contest_title}}',
            'heading' => 'Congratulations!',
            'body' => "Hi {{name}},\n\nYou won {{contest_title}}. Your prize will be credited to your Stylebite wallet.\n\nThanks for taking part — and enjoy the win.",
            'action_text' => 'Open Stylebite',
            'action_url' => null,
        ],
    ];

    /**
     * Resolved copy for a key, with placeholders replaced. Never returns null:
     * a missing or deactivated template falls back to the built-in default.
     *
     * @return array{subject:string, heading:string, body:string, action_text:?string, action_url:?string}
     */
    public function render(string $key, array $variables = []): array
    {
        $default = self::DEFAULTS[$key] ?? null;
        $stored = $this->stored()[$key] ?? null;

        // A stored row only wins while it is active and actually has copy.
        $usable = $stored !== null
            && ($stored['is_active'] ?? false)
            && trim((string) ($stored['subject'] ?? '')) !== ''
            && trim((string) ($stored['body'] ?? '')) !== '';

        $source = $usable ? $stored : $default;

        if ($source === null) {
            // Unknown key with no default: give the caller something harmless
            // rather than throwing inside a mail send.
            $source = [
                'subject' => 'Stylebite',
                'heading' => 'Stylebite',
                'body' => '',
                'action_text' => null,
                'action_url' => null,
            ];
        }

        $variables = $this->withGlobals($variables);

        return [
            'subject' => $this->substitute((string) $source['subject'], $variables),
            'heading' => $this->substitute((string) $source['heading'], $variables),
            'body' => $this->substitute((string) $source['body'], $variables),
            'action_text' => filled($source['action_text'] ?? null)
                ? $this->substitute((string) $source['action_text'], $variables)
                : null,
            'action_url' => filled($source['action_url'] ?? null)
                ? $this->substitute((string) $source['action_url'], $variables)
                : null,
        ];
    }

    /**
     * Render and send in one call. Kept synchronous on purpose: OTP and login
     * codes must not wait on the once-per-minute queue cron.
     */
    public function send(
        string $key,
        string $toEmail,
        string $toName,
        array $variables = [],
        ?string $highlightCode = null
    ): void {
        $copy = $this->render($key, $variables + ['name' => $toName, 'email' => $toEmail]);

        stylebite_send_email(
            $toEmail,
            $toName,
            $copy['subject'],
            $copy['heading'],
            $copy['body'],
            $copy['action_text'],
            $copy['action_url'],
            $highlightCode
        );
    }

    /**
     * Placeholder names an admin may use, for the editor's help text.
     *
     * @return array<int, string>
     */
    public function availablePlaceholders(string $category): array
    {
        $common = ['name', 'username', 'email', 'app_name', 'expiry_minutes'];

        return $category === self::CATEGORY_CONTEST
            ? array_merge($common, ['contest_title', 'contest_ends_at'])
            : $common;
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function stored(): array
    {
        return Cache::remember(self::CACHE_KEY, 300, fn () => EmailTemplate::query()
            ->get(['key', 'subject', 'heading', 'body', 'action_text', 'action_url', 'is_active'])
            ->keyBy('key')
            ->map(fn (EmailTemplate $template) => $template->only([
                'subject', 'heading', 'body', 'action_text', 'action_url', 'is_active',
            ]))
            ->all());
    }

    private function withGlobals(array $variables): array
    {
        return $variables + [
            'app_name' => 'Stylebite',
            'name' => $variables['name'] ?? 'there',
        ];
    }

    /**
     * Replace {{known}} placeholders and strip any that are left over, so an
     * admin typo never ships literal braces to a user.
     */
    private function substitute(string $copy, array $variables): string
    {
        foreach ($variables as $name => $value) {
            if (is_scalar($value) || $value === null) {
                $copy = str_replace('{{'.$name.'}}', (string) $value, $copy);
            }
        }

        return trim((string) preg_replace('/\{\{\s*[a-z0-9_]+\s*\}\}/i', '', $copy));
    }
}
