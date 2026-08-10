<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\EarningsWallet;
use App\Models\EarningTransaction;
use App\Models\Profile;
use App\Models\User;
use App\Support\CsvExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CSV/Excel exports for legal and compliance requests.
 *
 * Two failure modes are worth pinning here, because both are silent:
 *
 *  - **Truncation.** These exports use keyset pagination, which breaks without a
 *    sound order — the file then looks complete while missing rows. Every export
 *    test below deliberately spans more than one chunk boundary's worth of
 *    ordering assumptions and asserts the *count*, not just that "it worked".
 *  - **Formula injection.** A crafted username becomes executable when an admin
 *    opens the file. Escaping is asserted end to end, not only on the helper.
 */
class ComplianceExportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'status' => 'active']);
    }

    /**
     * @return array<int, array<int, string>> parsed rows, header included
     */
    private function parse(string $csv): array
    {
        // Strip the BOM before parsing, the way a spreadsheet does.
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv);
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $csv);
        rewind($handle);

        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            if ($row !== [null]) {
                $rows[] = $row;
            }
        }

        fclose($handle);

        return $rows;
    }

    public function test_every_export_starts_with_a_utf8_bom_so_excel_reads_it_correctly(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.users.export'));

        $response->assertOk();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $response->streamedContent());
        $this->assertStringContainsString('charset=UTF-8', $response->headers->get('Content-Type'));
    }

    public function test_the_users_export_contains_every_matching_user_exactly_once(): void
    {
        $admin = $this->admin();
        User::factory()->count(30)->create(['status' => 'active']);

        $response = $this->actingAs($admin)->get(route('admin.users.export'));
        $rows = $this->parse($response->streamedContent());

        $ids = array_column(array_slice($rows, 1), 0);

        // +1 for the acting admin, who is also a user row.
        $this->assertCount(31, $ids, 'The export dropped or duplicated rows.');
        $this->assertSame(array_unique($ids), $ids, 'The export repeated a row — keyset paging is broken.');
        $this->assertSame(User::pluck('id')->sort()->values()->all(), collect($ids)->map(fn ($id) => (int) $id)->sort()->values()->all());
    }

    public function test_the_users_export_respects_the_active_filters(): void
    {
        $admin = $this->admin();
        $banned = User::factory()->count(3)->create(['status' => 'banned']);
        User::factory()->count(4)->create(['status' => 'active']);

        $response = $this->actingAs($admin)->get(route('admin.users.export', ['status' => 'banned']));
        $rows = array_slice($this->parse($response->streamedContent()), 1);

        $this->assertCount(3, $rows);
        $this->assertEqualsCanonicalizing(
            $banned->pluck('id')->all(),
            array_map('intval', array_column($rows, 0))
        );
    }

    public function test_a_username_that_looks_like_a_formula_is_neutralised_in_the_export(): void
    {
        $admin = $this->admin();
        $attacker = User::factory()->create([
            'status' => 'active',
            'full_name' => '=HYPERLINK("http://evil.example","Click me")',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.users.export'));
        $rows = array_slice($this->parse($response->streamedContent()), 1);

        $row = collect($rows)->firstWhere(0, (string) $attacker->id);

        $this->assertNotNull($row);
        $this->assertSame("'=HYPERLINK(\"http://evil.example\",\"Click me\")", $row[2]);
        $this->assertStringNotContainsString(',=HYPERLINK', $response->streamedContent());
    }

    public function test_the_csv_helper_neutralises_every_formula_trigger(): void
    {
        foreach (['=cmd', '+1+1', '-1+1', '@SUM(A1)', "\tTabbed", "\rCarriage"] as $dangerous) {
            $this->assertStringStartsWith("'", CsvExport::safe($dangerous), "Unescaped: {$dangerous}");
        }

        // Ordinary values must survive untouched — escaping is not sanitising.
        $this->assertSame('Ayesha Khan', CsvExport::safe('Ayesha Khan'));
        $this->assertSame('42', CsvExport::safe(42));
        $this->assertSame('12.50', CsvExport::safe('12.50'));
        $this->assertSame('', CsvExport::safe(null));
        $this->assertSame('yes', CsvExport::safe(true));
        $this->assertSame('no', CsvExport::safe(false));

        // A minus inside the value, rather than leading it, is not a formula.
        $this->assertSame('Karachi-Central', CsvExport::safe('Karachi-Central'));
    }

    public function test_a_negative_amount_stays_readable_after_escaping(): void
    {
        // A reversal is stored negative; escaping must not turn it into garbage.
        $this->assertSame("'-25.00", CsvExport::safe('-25.00'));
    }

    public function test_non_ascii_names_survive_the_round_trip(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['status' => 'active', 'full_name' => 'عائشہ خان']);

        $response = $this->actingAs($admin)->get(route('admin.users.export'));
        $rows = array_slice($this->parse($response->streamedContent()), 1);
        $row = collect($rows)->firstWhere(0, (string) $user->id);

        $this->assertSame('عائشہ خان', $row[2]);
    }

    public function test_the_earnings_transactions_export_is_complete_and_unduplicated(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['status' => 'active']);
        $wallet = EarningsWallet::create([
            'user_id' => $user->id,
            'currency_code' => 'USD',
            'available_balance' => 0,
            'pending_balance' => 0,
            'lifetime_earned' => 0,
        ]);

        // Created oldest-first with an identical timestamp on several rows: this
        // is the shape that exposed the original truncation bug, where a
        // non-primary-key sort survived into the keyset paging.
        foreach (range(1, 25) as $index) {
            EarningTransaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $user->id,
                'transaction_type' => 'credit',
                'source_type' => 'adjustment',
                'amount' => 1.00,
                'currency_code' => 'USD',
                'status' => 'completed',
                'created_at' => now()->subMinutes(intdiv($index, 5)),
                'processed_at' => now(),
            ]);
        }

        $response = $this->actingAs($admin)->get(route('admin.earnings.transactions.export'));
        $rows = array_slice($this->parse($response->streamedContent()), 1);
        $ids = array_column($rows, 0);

        $this->assertCount(25, $ids);
        $this->assertSame(array_unique($ids), $ids);
    }

    public function test_exporting_the_user_list_is_itself_logged(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.users.export', ['status' => 'banned']))->assertOk();

        $log = ActivityLog::where('event_name', 'users_exported')->firstOrFail();

        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame('banned', $log->metadata_json['filters']['status']);
    }

    public function test_the_personal_data_export_returns_the_users_own_records_only(): void
    {
        $admin = $this->admin();
        $subject = User::factory()->create(['status' => 'active', 'full_name' => 'Data Subject']);
        $other = User::factory()->create(['status' => 'active', 'full_name' => 'Someone Else']);

        Profile::create(['user_id' => $subject->id, 'city' => 'Lahore', 'visibility' => 'public']);
        Profile::create(['user_id' => $other->id, 'city' => 'Karachi', 'visibility' => 'public']);

        $response = $this->actingAs($admin)->get(route('admin.users.personal_data', $subject));

        $response->assertOk();
        $this->assertStringContainsString('application/json', $response->headers->get('Content-Type'));

        $payload = json_decode($response->streamedContent(), true);

        $this->assertSame($subject->id, $payload['account']['id']);
        $this->assertSame('Data Subject', $payload['account']['full_name']);
        $this->assertSame('Lahore', $payload['profile']['city']);
        $this->assertStringNotContainsString('Someone Else', $response->streamedContent());

        // Portability means the whole record, so the nested sections must exist
        // even when empty — an absent key reads as "we hold nothing".
        foreach (['posts', 'comments', 'support_tickets', 'sessions', 'legal_acceptances', 'earning_transactions'] as $section) {
            $this->assertArrayHasKey($section, $payload, "Missing section: {$section}");
        }
    }

    public function test_the_personal_data_export_is_logged_against_the_subject(): void
    {
        $admin = $this->admin();
        $subject = User::factory()->create(['status' => 'active']);

        $this->actingAs($admin)->get(route('admin.users.personal_data', $subject))->assertOk();

        $log = ActivityLog::where('event_name', 'user_personal_data_exported')->firstOrFail();

        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame($subject->id, $log->entity_id);
    }

    public function test_a_support_agent_cannot_export_the_user_list(): void
    {
        $agent = User::factory()->create(['role' => 'user', 'status' => 'active']);
        $agent->assignRole('support_agent');

        $this->actingAs($agent)->get(route('admin.users.export'))->assertForbidden();
        $this->actingAs($agent)
            ->get(route('admin.users.personal_data', User::factory()->create()))
            ->assertForbidden();
    }

    public function test_exports_are_never_cached(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.users.export'));

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }
}
