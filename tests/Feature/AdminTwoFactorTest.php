<?php

namespace Tests\Feature;

use App\Http\Controllers\AdminAuthController;
use App\Mail\GlobalAppMail;
use App\Models\ActivityLog;
use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private function staff(array $attributes = []): User
    {
        return User::factory()->create($attributes + [
            'email' => 'staff@example.com',
            'password_hash' => Hash::make('secret-password'),
            'role' => 'admin',
            'status' => 'active',
        ]);
    }

    /**
     * Complete the password step and pull the emailed code back out.
     */
    private function passwordStep(string $email = 'staff@example.com'): string
    {
        $this->post('/admin/login', [
            'email' => $email,
            'password' => 'secret-password',
        ])->assertRedirect(route('admin.login.otp'));

        $code = null;

        Mail::assertSent(GlobalAppMail::class, function (GlobalAppMail $mail) use (&$code): bool {
            if ($mail->subjectLine !== 'Your Stylebite admin login code') {
                return false;
            }

            $code = $mail->highlightCode;

            return true;
        });

        $this->assertNotNull($code, 'An admin login code should have been emailed.');

        return $code;
    }

    public function test_password_alone_does_not_sign_an_admin_in(): void
    {
        Mail::fake();
        $this->staff();

        $this->post('/admin/login', [
            'email' => 'staff@example.com',
            'password' => 'secret-password',
        ])->assertRedirect(route('admin.login.otp'));

        // Crucially: not authenticated yet.
        $this->assertGuest();

        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));

        $this->assertDatabaseHas('email_verification_tokens', [
            'email' => 'staff@example.com',
            'purpose' => 'admin_login',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'event_name' => 'admin_login_2fa_challenged',
        ]);
    }

    public function test_correct_code_completes_the_sign_in(): void
    {
        Mail::fake();
        $admin = $this->staff();
        $code = $this->passwordStep();

        $this->get(route('admin.login.otp'))
            ->assertOk()
            ->assertSee('Check your email');

        $this->post(route('admin.login.otp.verify'), ['code' => $code])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
        $this->get(route('admin.dashboard'))->assertOk();

        $signedIn = ActivityLog::where('event_name', 'admin_signed_in')->latest('id')->first();
        $this->assertNotNull($signedIn);
        $this->assertTrue($signedIn->metadata_json['two_factor']);
    }

    public function test_wrong_code_is_refused_and_audited(): void
    {
        Mail::fake();
        $this->staff();
        $code = $this->passwordStep();

        $wrong = $code === '000000' ? '111111' : '000000';

        $this->post(route('admin.login.otp.verify'), ['code' => $wrong])
            ->assertSessionHasErrors('code');

        $this->assertGuest();

        $this->assertDatabaseHas('activity_logs', [
            'event_name' => 'admin_login_2fa_failed',
        ]);
    }

    public function test_five_wrong_codes_end_the_attempt(): void
    {
        Mail::fake();
        $this->staff();
        $code = $this->passwordStep();
        $wrong = $code === '000000' ? '111111' : '000000';

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('admin.login.otp.verify'), ['code' => $wrong])
                ->assertSessionHasErrors('code');
        }

        // Even the right code cannot rescue a locked attempt.
        $this->post(route('admin.login.otp.verify'), ['code' => $code])
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseHas('activity_logs', ['event_name' => 'admin_login_2fa_locked']);
    }

    public function test_a_code_cannot_be_reused(): void
    {
        Mail::fake();
        $this->staff();
        $code = $this->passwordStep();

        $this->post(route('admin.login.otp.verify'), ['code' => $code])
            ->assertRedirect(route('admin.dashboard'));

        $this->post('/admin/logout')->assertRedirect(route('admin.login'));

        // Replaying the same code must not get back in.
        $this->post(route('admin.login.otp.verify'), ['code' => $code])
            ->assertRedirect(route('admin.login'));

        $this->assertGuest();
    }

    public function test_the_otp_screen_is_unreachable_without_passing_the_password_step(): void
    {
        $this->get(route('admin.login.otp'))
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors('email');

        $this->post(route('admin.login.otp.verify'), ['code' => '123456'])
            ->assertRedirect(route('admin.login'));

        $this->assertGuest();
    }

    public function test_an_account_banned_between_the_two_steps_cannot_complete_sign_in(): void
    {
        Mail::fake();
        $admin = $this->staff();
        $code = $this->passwordStep();

        $admin->forceFill(['status' => 'banned'])->save();

        $this->post(route('admin.login.otp.verify'), ['code' => $code])
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_resend_is_rate_limited_then_issues_a_new_code(): void
    {
        Mail::fake();
        $this->staff();
        $this->passwordStep();

        $this->post(route('admin.login.otp.resend'))
            ->assertRedirect()
            ->assertSessionHas('status', fn ($message) => str_contains($message, 'wait'));

        Mail::assertSentCount(1);

        $this->travel(61)->seconds();

        $this->post(route('admin.login.otp.resend'))
            ->assertRedirect()
            ->assertSessionHas('status', fn ($message) => str_contains($message, 'new code'));

        Mail::assertSentCount(2);
    }

    public function test_the_admin_login_email_copy_is_editable_like_any_other_template(): void
    {
        Mail::fake();

        EmailTemplate::where('key', 'auth.admin_login_code')->update([
            'subject' => 'Your Stylebite admin login code',
            'heading' => 'Staff sign-in',
            'body' => 'Code valid for {{expiry_minutes}} minutes.',
        ]);

        app(\App\Services\EmailTemplates::class)->forgetCache();

        $this->staff();
        $this->passwordStep();

        Mail::assertSent(GlobalAppMail::class, fn (GlobalAppMail $mail) => $mail->heading === 'Staff sign-in'
            && str_contains($mail->contentText, '10 minutes'));
    }

    public function test_the_dashboard_session_expires_24_hours_after_sign_in(): void
    {
        Mail::fake();
        $admin = $this->staff();
        $code = $this->passwordStep();

        $this->post(route('admin.login.otp.verify'), ['code' => $code])
            ->assertRedirect(route('admin.dashboard'));

        // Still fine well inside the window, even after activity.
        $this->travel(23)->hours();
        $this->get(route('admin.dashboard'))->assertOk();

        // Past the cap it ends, regardless of how active the session has been —
        // Laravel's own lifetime is idle-based, which would never expire here.
        $this->travel(2)->hours();
        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_session_predating_the_feature_is_stamped_rather_than_kicked_out(): void
    {
        config(['auth.admin_two_factor' => false]);
        $admin = $this->staff();

        $this->actingAs($admin);
        session()->forget(AdminAuthController::LOGGED_IN_AT_KEY);

        $this->get(route('admin.dashboard'))->assertOk();

        $this->assertNotNull(session(AdminAuthController::LOGGED_IN_AT_KEY));
    }

    public function test_two_factor_can_be_switched_off_for_the_panel(): void
    {
        config(['auth.admin_two_factor' => false]);
        Mail::fake();

        $admin = $this->staff();

        $this->post('/admin/login', [
            'email' => 'staff@example.com',
            'password' => 'secret-password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
        Mail::assertNothingSent();
    }
}
