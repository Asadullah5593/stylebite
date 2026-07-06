<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EarningTransaction;
use App\Models\EarningsWallet;
use App\Models\Profile;
use App\Models\User;
use App\Models\WithdrawalRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EarningsController extends Controller
{
    public function overview(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing('profile');
        $wallet = $this->walletForUser($user);
        $contestWins = $this->contestWins($user);
        $change = $this->lastMonthIncomeChange($user);

        $creditTransactions = EarningTransaction::query()
            ->where('user_id', $user->id)
            ->where('transaction_type', 'credit')
            ->latest('processed_at')
            ->latest('id')
            ->take(3)
            ->get();

        $withdrawals = WithdrawalRequest::query()
            ->where('user_id', $user->id)
            ->latest('requested_at')
            ->latest('id')
            ->take(3)
            ->get();

        return response()->json([
            'status_code' => 1,
            'message' => 'Earnings overview fetched successfully.',
            'summary' => [
                'currency_code' => $wallet->currency_code,
                'available_balance' => (float) $wallet->available_balance,
                'pending_balance' => (float) $wallet->pending_balance,
                'contest_wins' => $contestWins,
                'last_month_income_change_percent' => $change['percent'],
                'last_month_income_change_display' => $change['display'],
                'last_month_income_amount' => $change['last_month_income_amount'],
            ],
            'earning_breakdown_preview' => $creditTransactions->map(fn (EarningTransaction $transaction) => $this->earningTransactionPayload($transaction))->values(),
            'recent_withdrawals' => $withdrawals->map(fn (WithdrawalRequest $withdrawal) => $this->withdrawalPayload($withdrawal))->values(),
        ]);
    }

    public function breakdowns(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = 10;

        $paginator = EarningTransaction::query()
            ->where('user_id', $request->user()->id)
            ->latest('processed_at')
            ->latest('id')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'status_code' => 1,
            'message' => 'Earning breakdowns fetched successfully.',
            'breakdowns' => $paginator->getCollection()
                ->map(fn (EarningTransaction $transaction) => $this->earningTransactionPayload($transaction))
                ->values(),
            'pagination' => $this->paginationPayload($paginator),
        ]);
    }

    public function withdrawals(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = 10;

        $paginator = WithdrawalRequest::query()
            ->where('user_id', $request->user()->id)
            ->latest('requested_at')
            ->latest('id')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'status_code' => 1,
            'message' => 'Withdrawals fetched successfully.',
            'withdrawals' => $paginator->getCollection()
                ->map(fn (WithdrawalRequest $withdrawal) => $this->withdrawalPayload($withdrawal))
                ->values(),
            'pagination' => $this->paginationPayload($paginator),
        ]);
    }

    public function storeWithdrawal(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'method' => ['required', 'string', 'in:bank_transfer,paypal,stripe,wallet'],
            'account_ref' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $wallet = $this->walletForUser($user);
        $amount = round((float) $validated['amount'], 2);

        if ($amount > (float) $wallet->available_balance) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Insufficient available balance for this withdrawal request.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        [$wallet, $withdrawal, $transaction] = DB::transaction(function () use ($wallet, $user, $validated, $amount) {
            $wallet->available_balance = (float) $wallet->available_balance - $amount;
            $wallet->pending_balance = (float) $wallet->pending_balance + $amount;
            $wallet->updated_balance_at = now();
            $wallet->save();

            $withdrawal = WithdrawalRequest::create([
                'wallet_id' => $wallet->id,
                'user_id' => $user->id,
                'amount' => $amount,
                'currency_code' => $wallet->currency_code,
                'method' => $validated['method'],
                'account_ref' => $validated['account_ref'] ?? null,
                'status' => 'pending',
                'requested_at' => now(),
            ]);

            $transaction = EarningTransaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $user->id,
                'transaction_type' => 'debit',
                'source_type' => 'withdrawal',
                'source_id' => $withdrawal->id,
                'amount' => $amount,
                'currency_code' => $wallet->currency_code,
                'status' => 'pending',
                'note' => 'Withdrawal request submitted',
                'metadata_json' => [
                    'title' => 'Transfer to '.ucwords(str_replace('_', ' ', $validated['method'])),
                    'reason' => 'Withdrawal Request',
                    'account_ref' => $validated['account_ref'] ?? null,
                ],
                'processed_at' => now(),
            ]);

            return [$wallet->fresh(), $withdrawal, $transaction];
        });

        return response()->json([
            'status_code' => 1,
            'message' => 'Withdrawal request submitted successfully.',
            'wallet' => [
                'currency_code' => $wallet->currency_code,
                'available_balance' => (float) $wallet->available_balance,
                'pending_balance' => (float) $wallet->pending_balance,
            ],
            'withdrawal' => $this->withdrawalPayload($withdrawal),
            'transaction' => $this->earningTransactionPayload($transaction),
        ], Response::HTTP_CREATED);
    }

    private function walletForUser(User $user): EarningsWallet
    {
        return EarningsWallet::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'currency_code' => 'PKR',
                'available_balance' => 0,
                'pending_balance' => 0,
                'lifetime_earned' => 0,
                'lifetime_withdrawn' => 0,
            ]
        );
    }

    private function contestWins(User $user): int
    {
        $profile = $user->profile ?? Profile::query()->where('user_id', $user->id)->first();

        if ($profile && $profile->contest_wins !== null) {
            return (int) $profile->contest_wins;
        }

        return $user->contestSubmissions()->where('rank_position', 1)->count();
    }

    private function lastMonthIncomeChange(User $user): array
    {
        $lastMonthStart = now()->copy()->subMonthNoOverflow()->startOfMonth();
        $lastMonthEnd = now()->copy()->subMonthNoOverflow()->endOfMonth();
        $previousMonthStart = now()->copy()->subMonthsNoOverflow(2)->startOfMonth();
        $previousMonthEnd = now()->copy()->subMonthsNoOverflow(2)->endOfMonth();

        $lastMonthIncome = (float) EarningTransaction::query()
            ->where('user_id', $user->id)
            ->where('transaction_type', 'credit')
            ->where('status', 'completed')
            ->whereBetween('processed_at', [$lastMonthStart, $lastMonthEnd])
            ->sum('amount');

        $previousMonthIncome = (float) EarningTransaction::query()
            ->where('user_id', $user->id)
            ->where('transaction_type', 'credit')
            ->where('status', 'completed')
            ->whereBetween('processed_at', [$previousMonthStart, $previousMonthEnd])
            ->sum('amount');

        if ($previousMonthIncome <= 0.0) {
            $percent = $lastMonthIncome > 0 ? 100.0 : 0.0;
        } else {
            $percent = (($lastMonthIncome - $previousMonthIncome) / $previousMonthIncome) * 100;
        }

        $rounded = round($percent, 2);

        return [
            'percent' => $rounded,
            'display' => ($rounded >= 0 ? '+' : '').$rounded.'%',
            'last_month_income_amount' => $lastMonthIncome,
        ];
    }

    private function earningTransactionPayload(EarningTransaction $transaction): array
    {
        $metadata = is_array($transaction->metadata_json) ? $transaction->metadata_json : [];
        $isCredit = $transaction->transaction_type === 'credit';
        $title = $metadata['title'] ?? $transaction->note ?? $this->sourceTypeLabel($transaction->source_type);
        $reason = $metadata['reason'] ?? $this->sourceTypeReason($transaction->source_type);
        $amount = (float) $transaction->amount;

        return [
            'id' => $transaction->id,
            'transaction_type' => $transaction->transaction_type,
            'source_type' => $transaction->source_type,
            'source_id' => $transaction->source_id,
            'status' => $transaction->status,
            'title' => $title,
            'reason' => $reason,
            'note' => $transaction->note,
            'currency_code' => $transaction->currency_code,
            'amount' => $amount,
            'amount_display' => ($isCredit ? '+' : '-').' '.($transaction->currency_code ?? 'PKR').' '.number_format($amount, 0),
            'amount_direction' => $isCredit ? 'credited' : 'debited',
            'processed_at' => optional($transaction->processed_at)->toDateTimeString(),
            'created_at' => optional($transaction->created_at)->toDateTimeString(),
        ];
    }

    private function withdrawalPayload(WithdrawalRequest $withdrawal): array
    {
        return [
            'id' => $withdrawal->id,
            'amount' => (float) $withdrawal->amount,
            'currency_code' => $withdrawal->currency_code,
            'amount_display' => '- '.$withdrawal->currency_code.' '.number_format((float) $withdrawal->amount, 0),
            'method' => $withdrawal->method,
            'title' => 'Transfer to '.$this->withdrawalMethodLabel($withdrawal->method),
            'account_ref' => $withdrawal->account_ref,
            'status' => $withdrawal->status,
            'requested_at' => optional($withdrawal->requested_at)->toDateTimeString(),
            'processed_at' => optional($withdrawal->processed_at)->toDateTimeString(),
            'failure_reason' => $withdrawal->failure_reason,
        ];
    }

    private function sourceTypeLabel(string $sourceType): string
    {
        return match ($sourceType) {
            'contest_reward' => 'Contest Reward',
            'engagement_bonus' => 'Creator Bonus',
            'referral_bonus' => 'Referral Bonus',
            'withdrawal' => 'Withdrawal',
            default => 'Adjustment',
        };
    }

    private function sourceTypeReason(string $sourceType): string
    {
        return match ($sourceType) {
            'contest_reward' => 'Contest Win',
            'engagement_bonus' => 'Engagement Reward',
            'referral_bonus' => 'Referral Reward',
            'withdrawal' => 'Withdrawal Request',
            default => 'Balance Adjustment',
        };
    }

    private function withdrawalMethodLabel(string $method): string
    {
        return match ($method) {
            'bank_transfer' => 'Bank',
            'paypal' => 'PayPal',
            'stripe' => 'Stripe',
            default => 'Wallet',
        };
    }

    private function paginationPayload(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
            'has_more_pages' => $paginator->hasMorePages(),
        ];
    }
}
