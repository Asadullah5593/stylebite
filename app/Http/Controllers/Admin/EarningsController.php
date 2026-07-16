<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\EarningTransaction;
use App\Models\EarningsWallet;
use App\Models\WithdrawalRequest;
use App\Services\CurrencyConverter;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EarningsController extends Controller
{
    public function wallets(Request $request): View
    {
        $wallets = EarningsWallet::query()
            ->with('user:id,username,full_name,email,avatar_url')
            ->withCount(['transactions', 'withdrawalRequests'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('currency_code', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($query) => $query
                            ->where('username', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('currency_code'), fn ($query) => $query->where('currency_code', $request->string('currency_code')))
            ->latest('updated_at')
            ->paginate(10)
            ->withQueryString();

        $currencyOptions = EarningsWallet::query()
            ->select('currency_code')
            ->distinct()
            ->orderBy('currency_code')
            ->pluck('currency_code');

        $walletStats = [
            'total' => EarningsWallet::count(),
            'available' => round((float) EarningsWallet::sum('available_balance'), 2),
            'pending' => round((float) EarningsWallet::sum('pending_balance'), 2),
            'earned' => round((float) EarningsWallet::sum('lifetime_earned'), 2),
            'withdrawn' => round((float) EarningsWallet::sum('lifetime_withdrawn'), 2),
        ];

        return view('admin.earnings.WalletsPage', compact('wallets', 'currencyOptions', 'walletStats'));
    }

    public function showWallet(EarningsWallet $wallet, CurrencyConverter $converter): View
    {
        $wallet->load([
            'user:id,username,full_name,email,avatar_url',
            'transactions' => fn ($query) => $query->latest('created_at')->limit(10),
            'withdrawalRequests' => fn ($query) => $query->with('payoutTransaction:id,source_id,status,amount,currency_code,processed_at')->latest('requested_at')->limit(10),
        ]);

        $reservedWithdrawalTotal = (float) WithdrawalRequest::query()
            ->where('wallet_id', $wallet->id)
            ->whereIn('status', ['pending', 'processing'])
            ->sum('amount');

        $walletAudit = [
            'reserved_withdrawals' => round($reservedWithdrawalTotal, 2),
            'pending_balance_gap' => round((float) $wallet->pending_balance - $reservedWithdrawalTotal, 2),
            'completed_credits' => round((float) EarningTransaction::query()
                ->where('wallet_id', $wallet->id)
                ->where('transaction_type', 'credit')
                ->where('status', 'completed')
                ->sum('amount'), 2),
            'completed_debits' => round((float) EarningTransaction::query()
                ->where('wallet_id', $wallet->id)
                ->where('transaction_type', 'debit')
                ->where('status', 'completed')
                ->sum('amount'), 2),
            'completed_withdrawals' => round((float) WithdrawalRequest::query()
                ->where('wallet_id', $wallet->id)
                ->where('status', 'completed')
                ->sum('amount'), 2),
        ];

        // Adjustments are entered in the base currency; show the admin what it
        // converts to for this wallet before they submit.
        $baseCurrency = $converter->baseCurrency();
        $sample = $converter->convert(1, $baseCurrency, $wallet->currency_code);
        $fx = [
            'base_currency' => $baseCurrency,
            'rate' => $sample['rate'] ?? null,
            'rate_at' => $sample['rate_at'] ?? null,
            'rates_fetched_at' => $converter->ratesFetchedAt(),
        ];

        return view('admin.earnings.ShowWalletPage', compact('wallet', 'walletAudit', 'fx'));
    }

    public function transactions(Request $request): View
    {
        $transactions = EarningTransaction::query()
            ->with('user:id,username,full_name,email', 'wallet:id,currency_code')
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('note', 'like', "%{$search}%")
                        ->orWhere('transaction_type', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhere('source_type', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($query) => $query
                            ->where('username', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('transaction_type'), fn ($query) => $query->where('transaction_type', $request->string('transaction_type')))
            ->when($request->filled('source_type'), fn ($query) => $query->where('source_type', $request->string('source_type')))
            ->latest('created_at')
            ->paginate(10)
            ->withQueryString();

        $transactionStats = [
            'total' => EarningTransaction::count(),
            'credits' => EarningTransaction::where('transaction_type', 'credit')->count(),
            'debits' => EarningTransaction::where('transaction_type', 'debit')->count(),
            'completed' => EarningTransaction::where('status', 'completed')->count(),
            'pending' => EarningTransaction::where('status', 'pending')->count(),
        ];

        return view('admin.earnings.TransactionsPage', compact('transactions', 'transactionStats'));
    }

    public function withdrawals(Request $request): View
    {
        $withdrawals = WithdrawalRequest::query()
            ->with('user:id,username,full_name,email', 'wallet:id,currency_code,available_balance,pending_balance', 'payoutTransaction:id,source_id,status,amount,currency_code,processed_at')
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('currency_code', 'like', "%{$search}%")
                        ->orWhere('account_ref', 'like', "%{$search}%")
                        ->orWhere('failure_reason', 'like', "%{$search}%")
                        ->orWhere('method', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($query) => $query
                            ->where('username', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('method'), fn ($query) => $query->where('method', $request->string('method')))
            ->latest('requested_at')
            ->paginate(10)
            ->withQueryString();

        $withdrawalStats = [
            'total' => WithdrawalRequest::count(),
            'pending' => WithdrawalRequest::where('status', 'pending')->count(),
            'processing' => WithdrawalRequest::where('status', 'processing')->count(),
            'completed' => WithdrawalRequest::where('status', 'completed')->count(),
            'failed' => WithdrawalRequest::where('status', 'failed')->count(),
            'rejected' => WithdrawalRequest::where('status', 'rejected')->count(),
            'missing_transactions' => WithdrawalRequest::query()
                ->whereDoesntHave('payoutTransaction')
                ->count(),
        ];

        return view('admin.earnings.WithdrawalsPage', compact('withdrawals', 'withdrawalStats'));
    }

    public function reconciliation(Request $request): View
    {
        $rows = $this->buildReconciliationRows($request);
        $paginatedRows = $this->paginateReconciliationRows($rows, $request);

        $reconciliationStats = [
            'wallets' => $rows->count(),
            'mismatched_pending' => $rows->where('pending_gap', '!=', 0.0)->count(),
            'missing_transactions' => $rows->where('missing_withdrawal_transactions', '>', 0)->count(),
            'negative_available' => $rows->where('available_balance', '<', 0)->count(),
        ];

        return view('admin.earnings.ReconciliationPage', [
            'reconciliationRows' => $paginatedRows,
            'reconciliationStats' => $reconciliationStats,
        ]);
    }

    public function exportTransactions(Request $request): StreamedResponse
    {
        $transactions = $this->filteredTransactionsQuery($request)->get();
        $filename = 'earnings-transactions-'.now()->format('Ymd-His').'.csv';

        $this->logActivity('earnings_transactions_exported', 'earnings_transaction', null, [
            'exported_count' => $transactions->count(),
            'filters' => $request->only(['q', 'status', 'transaction_type', 'source_type']),
        ]);

        return response()->streamDownload(function () use ($transactions) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'User', 'Wallet', 'Type', 'Source', 'Amount', 'Currency', 'Status', 'Note', 'Created']);

            foreach ($transactions as $transaction) {
                fputcsv($handle, [
                    $transaction->id,
                    $transaction->user?->full_name ?: $transaction->user?->username,
                    $transaction->wallet_id,
                    $transaction->transaction_type,
                    $transaction->source_type,
                    $transaction->amount,
                    $transaction->currency_code,
                    $transaction->status,
                    $transaction->note,
                    $transaction->created_at?->toDateTimeString(),
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportWithdrawals(Request $request): StreamedResponse
    {
        $withdrawals = $this->filteredWithdrawalsQuery($request)->get();
        $filename = 'earnings-withdrawals-'.now()->format('Ymd-His').'.csv';

        $this->logActivity('earnings_withdrawals_exported', 'withdrawal_request', null, [
            'exported_count' => $withdrawals->count(),
            'filters' => $request->only(['q', 'status', 'method']),
        ]);

        return response()->streamDownload(function () use ($withdrawals) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'User', 'Wallet', 'Amount', 'Currency', 'Method', 'Account Ref', 'Status', 'Failure Reason', 'Requested At', 'Processed At']);

            foreach ($withdrawals as $withdrawal) {
                fputcsv($handle, [
                    $withdrawal->id,
                    $withdrawal->user?->full_name ?: $withdrawal->user?->username,
                    $withdrawal->wallet_id,
                    $withdrawal->amount,
                    $withdrawal->currency_code,
                    $withdrawal->method,
                    $withdrawal->account_ref,
                    $withdrawal->status,
                    $withdrawal->failure_reason,
                    $withdrawal->requested_at?->toDateTimeString(),
                    $withdrawal->processed_at?->toDateTimeString(),
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportReconciliation(Request $request): StreamedResponse
    {
        $rows = $this->buildReconciliationRows($request);
        $filename = 'earnings-reconciliation-'.now()->format('Ymd-His').'.csv';

        $this->logActivity('earnings_reconciliation_exported', 'earnings_wallet', null, [
            'exported_count' => $rows->count(),
            'filters' => $request->only(['q', 'currency_code', 'issue']),
        ]);

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Wallet ID', 'User', 'Currency', 'Available', 'Pending', 'Reserved Withdrawals', 'Pending Gap', 'Missing Withdrawal Transactions', 'Completed Withdrawals', 'Completed Credits', 'Completed Debits', 'Issues']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['wallet_id'],
                    $row['user_name'],
                    $row['currency_code'],
                    $row['available_balance'],
                    $row['pending_balance'],
                    $row['reserved_withdrawals'],
                    $row['pending_gap'],
                    $row['missing_withdrawal_transactions'],
                    $row['completed_withdrawals'],
                    $row['completed_credits'],
                    $row['completed_debits'],
                    implode('; ', $row['issues']),
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function updateWithdrawal(Request $request, WithdrawalRequest $withdrawal)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending,processing,completed,failed,rejected'],
            'failure_reason' => ['nullable', 'string', 'max:255'],
        ]);

        if (in_array($validated['status'], ['failed', 'rejected'], true) && blank($validated['failure_reason'])) {
            return back()->with('status', 'Failure or rejection reason is required for failed/rejected withdrawals.');
        }

        $original = [];

        DB::transaction(function () use ($withdrawal, $validated, &$original) {
            $withdrawal = WithdrawalRequest::query()
                ->whereKey($withdrawal->id)
                ->lockForUpdate()
                ->firstOrFail();

            $wallet = EarningsWallet::query()
                ->whereKey($withdrawal->wallet_id)
                ->lockForUpdate()
                ->firstOrFail();

            $transaction = EarningTransaction::query()
                ->where('source_type', 'withdrawal')
                ->where('source_id', $withdrawal->id)
                ->lockForUpdate()
                ->first();

            $original = [
                'status' => $withdrawal->status,
                'failure_reason' => $withdrawal->failure_reason,
                'processed_at' => $withdrawal->processed_at?->toDateTimeString(),
                'wallet_available_balance' => (float) $wallet->available_balance,
                'wallet_pending_balance' => (float) $wallet->pending_balance,
                'wallet_lifetime_withdrawn' => (float) $wallet->lifetime_withdrawn,
            ];

            $amount = round((float) $withdrawal->amount, 2);
            $fromReserved = in_array($withdrawal->status, ['pending', 'processing'], true);
            $toReserved = in_array($validated['status'], ['pending', 'processing'], true);
            $toFinalized = $validated['status'] === 'completed';
            $toReturned = in_array($validated['status'], ['failed', 'rejected'], true);

            if ($withdrawal->status !== $validated['status']) {
                if (! $fromReserved && $toReserved) {
                    if ((float) $wallet->available_balance < $amount) {
                        abort(422, 'Insufficient available balance to reserve this withdrawal.');
                    }

                    $wallet->available_balance = round((float) $wallet->available_balance - $amount, 2);
                    $wallet->pending_balance = round((float) $wallet->pending_balance + $amount, 2);
                }

                if ($fromReserved && ! $toReserved) {
                    $wallet->pending_balance = round((float) $wallet->pending_balance - $amount, 2);
                }

                if ($fromReserved && $toFinalized) {
                    $wallet->lifetime_withdrawn = round((float) $wallet->lifetime_withdrawn + $amount, 2);
                }

                if ($fromReserved && $toReturned) {
                    $wallet->available_balance = round((float) $wallet->available_balance + $amount, 2);
                }

                if (! $fromReserved && $toReturned && $withdrawal->status === 'completed') {
                    if ((float) $wallet->lifetime_withdrawn < $amount) {
                        abort(422, 'Cannot revert completed withdrawal because lifetime withdrawn would become negative.');
                    }

                    $wallet->available_balance = round((float) $wallet->available_balance + $amount, 2);
                    $wallet->lifetime_withdrawn = round((float) $wallet->lifetime_withdrawn - $amount, 2);
                }

                if (! $fromReserved && $toReserved && $withdrawal->status === 'completed') {
                    if ((float) $wallet->lifetime_withdrawn < $amount) {
                        abort(422, 'Cannot move completed withdrawal back to reserved because lifetime withdrawn would become negative.');
                    }

                    $wallet->pending_balance = round((float) $wallet->pending_balance + $amount, 2);
                    $wallet->lifetime_withdrawn = round((float) $wallet->lifetime_withdrawn - $amount, 2);
                }
            }

            $withdrawal->status = $validated['status'];
            $withdrawal->failure_reason = $toReturned ? $validated['failure_reason'] : null;
            $withdrawal->processed_at = in_array($validated['status'], ['completed', 'failed', 'rejected'], true)
                ? now()
                : null;
            $withdrawal->save();

            $wallet->updated_balance_at = now();
            $wallet->save();

            if ($transaction) {
                $transaction->status = $this->mapWithdrawalToTransactionStatus($withdrawal->status);
                $transaction->processed_at = $withdrawal->processed_at;
                $transaction->updated_at = now();
                $transaction->save();
            }
        });

        $this->logActivity('withdrawal_request_updated', 'withdrawal_request', $withdrawal->id, [
            'user_id' => $withdrawal->user_id,
            'wallet_id' => $withdrawal->wallet_id,
            'old' => $original,
            'new' => [
                'status' => $validated['status'],
                'failure_reason' => in_array($validated['status'], ['failed', 'rejected'], true) ? $validated['failure_reason'] : null,
            ],
        ]);

        return back()->with('status', 'Withdrawal updated successfully.');
    }

    public function storeAdjustment(Request $request, EarningsWallet $wallet, CurrencyConverter $converter)
    {
        $validated = $request->validate([
            'transaction_type' => ['required', 'in:credit,debit'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'note' => ['required', 'string', 'max:255'],
        ]);

        // Amounts are entered in the base currency (e.g. USD) and converted to
        // the wallet's currency using today's rate. The converted value is
        // frozen on the transaction — it is never re-converted later.
        $baseCurrency = $converter->baseCurrency();
        $baseAmount = round((float) $validated['amount'], 2);
        $conversion = $converter->convert($baseAmount, $baseCurrency, $wallet->currency_code);

        if ($conversion === null) {
            return back()->with('status', "No exchange rate available for {$baseCurrency} → {$wallet->currency_code}. Run stylebite:sync-currency-rates and try again.");
        }

        DB::transaction(function () use ($wallet, $validated, $baseCurrency, $baseAmount, $conversion) {
            $wallet->refresh();

            $amount = round((float) $conversion['amount'], 2);

            if ($validated['transaction_type'] === 'debit' && (float) $wallet->available_balance < $amount) {
                abort(422, 'Insufficient available balance for this manual debit.');
            }

            EarningTransaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $wallet->user_id,
                'transaction_type' => $validated['transaction_type'],
                'source_type' => 'adjustment',
                'amount' => $amount,
                'currency_code' => $wallet->currency_code,
                'base_amount' => $baseAmount,
                'base_currency_code' => $baseCurrency,
                'fx_rate' => $conversion['rate'],
                'fx_rate_at' => $conversion['rate_at'],
                'status' => 'completed',
                'note' => $validated['note'],
                'metadata_json' => [
                    'reason' => 'Manual admin adjustment',
                    'wallet_id' => $wallet->id,
                ],
                'processed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($validated['transaction_type'] === 'credit') {
                $wallet->available_balance = round((float) $wallet->available_balance + $amount, 2);
                $wallet->lifetime_earned = round((float) $wallet->lifetime_earned + $amount, 2);
            } else {
                $wallet->available_balance = round((float) $wallet->available_balance - $amount, 2);
            }

            $wallet->updated_balance_at = now();
            $wallet->save();
        });

        $this->logActivity('wallet_manual_adjustment_created', 'earnings_wallet', $wallet->id, [
            'user_id' => $wallet->user_id,
            'transaction_type' => $validated['transaction_type'],
            'base_amount' => $baseAmount,
            'base_currency_code' => $baseCurrency,
            'amount' => round((float) $conversion['amount'], 2),
            'currency_code' => $wallet->currency_code,
            'fx_rate' => $conversion['rate'],
            'note' => $validated['note'],
        ]);

        $converted = number_format((float) $conversion['amount'], 2).' '.$wallet->currency_code;

        return back()->with('status', "Manual adjustment applied: {$baseAmount} {$baseCurrency} = {$converted} (rate ".rtrim(rtrim(number_format($conversion['rate'], 6, '.', ''), '0'), '.').').');
    }

    public function reverseTransaction(EarningTransaction $transaction)
    {
        if ($transaction->status !== 'completed' || $transaction->source_type === 'withdrawal') {
            return back()->with('status', 'This transaction cannot be reversed from the admin panel.');
        }

        DB::transaction(function () use ($transaction) {
            $transaction->loadMissing('wallet');
            $wallet = $transaction->wallet;

            if (! $wallet) {
                abort(404, 'Wallet not found for this transaction.');
            }

            $amount = round((float) $transaction->amount, 2);

            if ($transaction->transaction_type === 'credit' && (float) $wallet->available_balance < $amount) {
                abort(422, 'Insufficient available balance to reverse this credit transaction.');
            }

            $transaction->status = 'reversed';
            $transaction->updated_at = now();
            $transaction->save();

            EarningTransaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $wallet->user_id,
                'transaction_type' => $transaction->transaction_type === 'credit' ? 'debit' : 'credit',
                'source_type' => 'adjustment',
                'source_id' => $transaction->id,
                'amount' => $amount,
                'currency_code' => $transaction->currency_code,
                // Mirror the original conversion so the reversal is auditable too.
                'base_amount' => $transaction->base_amount,
                'base_currency_code' => $transaction->base_currency_code,
                'fx_rate' => $transaction->fx_rate,
                'fx_rate_at' => $transaction->fx_rate_at,
                'status' => 'completed',
                'note' => 'Reversal for transaction #'.$transaction->id,
                'metadata_json' => [
                    'reason' => 'Admin reversal',
                    'reversed_transaction_id' => $transaction->id,
                ],
                'processed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($transaction->transaction_type === 'credit') {
                $wallet->available_balance = round((float) $wallet->available_balance - $amount, 2);
            } else {
                $wallet->available_balance = round((float) $wallet->available_balance + $amount, 2);
            }

            $wallet->updated_balance_at = now();
            $wallet->save();
        });

        $this->logActivity('earning_transaction_reversed', 'earning_transaction', $transaction->id, [
            'wallet_id' => $transaction->wallet_id,
            'user_id' => $transaction->user_id,
            'amount' => round((float) $transaction->amount, 2),
            'transaction_type' => $transaction->transaction_type,
        ]);

        return back()->with('status', 'Transaction reversed successfully.');
    }

    public static function tabCounts(): array
    {
        return [
            'wallets' => EarningsWallet::count(),
            'transactions' => EarningTransaction::count(),
            'withdrawals' => WithdrawalRequest::count(),
            'reconciliation' => EarningsWallet::count(),
        ];
    }

    private function mapWithdrawalToTransactionStatus(string $withdrawalStatus): string
    {
        return match ($withdrawalStatus) {
            'completed' => 'completed',
            'failed', 'rejected' => 'failed',
            default => 'pending',
        };
    }

    private function filteredTransactionsQuery(Request $request)
    {
        return EarningTransaction::query()
            ->with('user:id,username,full_name,email', 'wallet:id,currency_code')
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('note', 'like', "%{$search}%")
                        ->orWhere('transaction_type', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhere('source_type', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($query) => $query
                            ->where('username', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('transaction_type'), fn ($query) => $query->where('transaction_type', $request->string('transaction_type')))
            ->when($request->filled('source_type'), fn ($query) => $query->where('source_type', $request->string('source_type')))
            ->latest('created_at');
    }

    private function filteredWithdrawalsQuery(Request $request)
    {
        return WithdrawalRequest::query()
            ->with('user:id,username,full_name,email', 'wallet:id,currency_code,available_balance,pending_balance')
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('currency_code', 'like', "%{$search}%")
                        ->orWhere('account_ref', 'like', "%{$search}%")
                        ->orWhere('failure_reason', 'like', "%{$search}%")
                        ->orWhere('method', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($query) => $query
                            ->where('username', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('method'), fn ($query) => $query->where('method', $request->string('method')))
            ->latest('requested_at');
    }

    private function buildReconciliationRows(Request $request): Collection
    {
        return EarningsWallet::query()
            ->with('user:id,username,full_name,email')
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('currency_code', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($query) => $query
                            ->where('username', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('currency_code'), fn ($query) => $query->where('currency_code', $request->string('currency_code')))
            ->orderByDesc('updated_at')
            ->get()
            ->map(function (EarningsWallet $wallet) {
                $reservedWithdrawals = round((float) WithdrawalRequest::query()
                    ->where('wallet_id', $wallet->id)
                    ->whereIn('status', ['pending', 'processing'])
                    ->sum('amount'), 2);

                $missingWithdrawalTransactions = WithdrawalRequest::query()
                    ->where('wallet_id', $wallet->id)
                    ->whereDoesntHave('payoutTransaction')
                    ->count();

                $completedCredits = round((float) EarningTransaction::query()
                    ->where('wallet_id', $wallet->id)
                    ->where('transaction_type', 'credit')
                    ->where('status', 'completed')
                    ->sum('amount'), 2);

                $completedDebits = round((float) EarningTransaction::query()
                    ->where('wallet_id', $wallet->id)
                    ->where('transaction_type', 'debit')
                    ->where('status', 'completed')
                    ->sum('amount'), 2);

                $completedWithdrawals = round((float) WithdrawalRequest::query()
                    ->where('wallet_id', $wallet->id)
                    ->where('status', 'completed')
                    ->sum('amount'), 2);

                $pendingGap = round((float) $wallet->pending_balance - $reservedWithdrawals, 2);

                $issues = [];
                if ($pendingGap != 0.0) {
                    $issues[] = 'Pending balance mismatch';
                }
                if ($missingWithdrawalTransactions > 0) {
                    $issues[] = 'Missing withdrawal transactions';
                }
                if ((float) $wallet->available_balance < 0) {
                    $issues[] = 'Negative available balance';
                }

                return [
                    'wallet_id' => $wallet->id,
                    'user_name' => $wallet->user?->full_name ?: '@'.($wallet->user?->username ?? 'unknown'),
                    'user' => $wallet->user,
                    'currency_code' => $wallet->currency_code,
                    'available_balance' => round((float) $wallet->available_balance, 2),
                    'pending_balance' => round((float) $wallet->pending_balance, 2),
                    'reserved_withdrawals' => $reservedWithdrawals,
                    'pending_gap' => $pendingGap,
                    'missing_withdrawal_transactions' => $missingWithdrawalTransactions,
                    'completed_withdrawals' => $completedWithdrawals,
                    'completed_credits' => $completedCredits,
                    'completed_debits' => $completedDebits,
                    'issues' => $issues,
                    'updated_balance_at' => $wallet->updated_balance_at,
                ];
            })
            ->when($request->filled('issue'), function (Collection $rows) use ($request) {
                $issue = $request->string('issue')->toString();

                return $rows->filter(function (array $row) use ($issue) {
                    return match ($issue) {
                        'pending_gap' => $row['pending_gap'] != 0.0,
                        'missing_transactions' => $row['missing_withdrawal_transactions'] > 0,
                        'negative_available' => $row['available_balance'] < 0,
                        default => true,
                    };
                })->values();
            });
    }

    private function paginateReconciliationRows(Collection $rows, Request $request)
    {
        $page = max((int) $request->integer('page', 1), 1);
        $perPage = 10;
        $items = $rows->slice(($page - 1) * $perPage, $perPage)->values();

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $rows->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
    }

    private function logActivity(string $eventName, ?string $entityType, ?int $entityId, array $metadata = []): void
    {
        ActivityLog::create([
            'user_id' => auth()->id(),
            'actor_type' => 'admin',
            'event_name' => $eventName,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata_json' => $metadata ?: null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }
}
