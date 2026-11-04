@extends('admin.layouts.app')

@section('content')
<div class="earnings-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <a href="{{ route('admin.earnings.wallets') }}" class="text-decoration-none text-reset fw-bold">Earnings</a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">Wallet Detail</span>
    </nav>

    <div class="mb-4">
        <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">Wallet Detail</h1>
        <p class="text-muted small mb-0">Balances, recent movements, and manual adjustment tools</p>
    </div>

    @include('admin.earnings.partials.tabs')

    @if (session('status'))
        <div class="alert alert-success rounded-3 border-0 mb-4">{{ session('status') }}</div>
    @endif

    <div class="row g-4 mb-4">
        <div class="col-12 col-lg-8">
            <div class="glass rounded-4 p-4 h-100">
                <div class="d-flex align-items-center gap-3 mb-4">
                    @if ($wallet->user?->avatar_url)
                        <img src="{{ $wallet->user->avatar_url }}" alt="{{ $wallet->user->username }}" class="rounded-circle border border-white-05" width="56" height="56" style="object-fit: cover;">
                    @else
                        <div class="rounded-circle border border-white-05 d-flex align-items-center justify-content-center text-muted" style="width:56px;height:56px;">
                            <i class="bi bi-person"></i>
                        </div>
                    @endif
                    <div>
                        <div class="fw-semibold">{{ $wallet->user?->full_name ?: ($wallet->user?->username ? '@'.$wallet->user->username : 'Removed account') }}</div>
                        <div class="text-muted small">{{ $wallet->user?->email ?: 'No email' }}</div>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.earnings.balances.update', $wallet) }}" class="d-flex flex-column gap-3">
                    @csrf
                    @method('PATCH')
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <div class="border border-white-05 rounded-4 p-3 h-100">
                                <label class="text-muted small mb-1" for="available_balance">Available Balance ({{ $wallet->currency_code }})</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    name="available_balance"
                                    id="available_balance"
                                    value="{{ old('available_balance', number_format((float) $wallet->available_balance, 2, '.', '')) }}"
                                    class="form-control form-control-lg border-0 bg-dark-soft rounded-3 fw-bold @error('available_balance') is-invalid @enderror"
                                    required
                                >
                                @error('available_balance')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="border border-white-05 rounded-4 p-3 h-100">
                                <label class="text-muted small mb-1" for="pending_balance">Pending Balance ({{ $wallet->currency_code }})</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    name="pending_balance"
                                    id="pending_balance"
                                    value="{{ old('pending_balance', number_format((float) $wallet->pending_balance, 2, '.', '')) }}"
                                    class="form-control form-control-lg border-0 bg-dark-soft rounded-3 fw-bold text-warning @error('pending_balance') is-invalid @enderror"
                                    required
                                >
                                @error('pending_balance')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="border border-white-05 rounded-4 p-3 h-100">
                                <label class="text-muted small mb-1" for="lifetime_earned">Lifetime Earned ({{ $wallet->currency_code }})</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    name="lifetime_earned"
                                    id="lifetime_earned"
                                    value="{{ old('lifetime_earned', number_format((float) $wallet->lifetime_earned, 2, '.', '')) }}"
                                    class="form-control border-0 bg-dark-soft rounded-3 @error('lifetime_earned') is-invalid @enderror"
                                    required
                                >
                                @error('lifetime_earned')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="border border-white-05 rounded-4 p-3 h-100">
                                <label class="text-muted small mb-1" for="lifetime_withdrawn">Lifetime Withdrawn ({{ $wallet->currency_code }})</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    name="lifetime_withdrawn"
                                    id="lifetime_withdrawn"
                                    value="{{ old('lifetime_withdrawn', number_format((float) $wallet->lifetime_withdrawn, 2, '.', '')) }}"
                                    class="form-control border-0 bg-dark-soft rounded-3 @error('lifetime_withdrawn') is-invalid @enderror"
                                    required
                                >
                                @error('lifetime_withdrawn')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <label class="form-label small text-muted" for="currency_code">Currency</label>
                            <input
                                type="text"
                                name="currency_code"
                                id="currency_code"
                                maxlength="3"
                                value="{{ old('currency_code', $wallet->currency_code) }}"
                                class="form-control border-0 bg-dark-soft rounded-3 text-uppercase @error('currency_code') is-invalid @enderror"
                                required
                            >
                            @error('currency_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-8">
                            <label class="form-label small text-muted" for="balance_note">Reason (shown in transaction history)</label>
                            <input
                                type="text"
                                name="note"
                                id="balance_note"
                                maxlength="255"
                                value="{{ old('note') }}"
                                class="form-control border-0 bg-dark-soft rounded-3"
                                placeholder="e.g. Correct balance after contest 19 clawback"
                            >
                        </div>
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <button type="submit" class="btn btn-primary-gradient rounded-3 px-4"
                                onclick="return confirm('Save these wallet balances? The user will see them in the app immediately on refresh.');">
                            <i class="bi bi-save me-2"></i>Save balances
                        </button>
                        <span class="text-muted extra-small">App earnings screens read these fields directly.</span>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="glass rounded-4 p-4 h-100">
                <h2 class="h6 fw-bold mb-3">Manual Adjustment</h2>
                @if (empty($fx['rate']))
                    <div class="alert alert-warning rounded-3 small py-2">
                        No exchange rate available for <strong>{{ $fx['base_currency'] }} → {{ $wallet->currency_code }}</strong>.
                        Run <code>php artisan stylebite:sync-currency-rates</code> first.
                    </div>
                @endif
                <form method="POST" action="{{ route('admin.earnings.adjustments.store', $wallet) }}" class="d-flex flex-column gap-3">
                    @csrf
                    <select name="transaction_type" class="form-select border-0 bg-dark-soft rounded-3 text-muted">
                        <option value="credit">Credit</option>
                        <option value="debit">Debit</option>
                    </select>
                    <div>
                        <label class="form-label small text-muted">Amount in {{ $fx['base_currency'] }}</label>
                        <input type="number" step="0.01" min="0.01" name="amount" id="adjustment-amount"
                               class="form-control border-0 bg-dark-soft rounded-3"
                               placeholder="Amount in {{ $fx['base_currency'] }}">
                        <div class="form-text text-muted" id="adjustment-preview">
                            @if ($fx['rate'])
                                Converted to <strong>{{ $wallet->currency_code }}</strong> at today's rate
                                (1 {{ $fx['base_currency'] }} = {{ rtrim(rtrim(number_format($fx['rate'], 6, '.', ''), '0'), '.') }} {{ $wallet->currency_code }}).
                            @endif
                        </div>
                        @if ($fx['rates_fetched_at'])
                            <div class="form-text text-muted small">Rates updated {{ $fx['rates_fetched_at']->diffForHumans() }}.</div>
                        @endif
                    </div>
                    <textarea name="note" rows="3" class="form-control border-0 bg-dark-soft rounded-3" placeholder="Reason for adjustment"></textarea>
                    <button type="submit" class="btn btn-outline-dynamic rounded-3 px-3" @disabled(empty($fx['rate']))>
                        <i class="bi bi-plus-circle me-2"></i>Apply adjustment
                    </button>
                </form>
                @if ($fx['rate'])
                    <script>
                        (function () {
                            const rate = {{ $fx['rate'] }};
                            const input = document.getElementById('adjustment-amount');
                            const preview = document.getElementById('adjustment-preview');
                            const baseCode = @json($fx['base_currency']);
                            const walletCode = @json($wallet->currency_code);

                            if (!input || !preview) return;

                            const baseText = preview.innerHTML;

                            input.addEventListener('input', function () {
                                const value = parseFloat(input.value);

                                if (!value || value <= 0) {
                                    preview.innerHTML = baseText;
                                    return;
                                }

                                const converted = (value * rate).toLocaleString(undefined, {
                                    minimumFractionDigits: 2, maximumFractionDigits: 2,
                                });

                                preview.innerHTML = '<strong>' + value + ' ' + baseCode + ' = ' + converted + ' ' + walletCode + '</strong> will be applied.';
                            });
                        })();
                    </script>
                @endif
            </div>
        </div>
    </div>

    <div class="mb-2">
        <div class="text-muted small">Ledger totals below update when you edit transactions / withdrawals. Change pending withdrawals to move reserved amounts; edit or reverse credits/debits to change completed totals.</div>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-sm-6"><div class="glass rounded-4 p-3 h-100"><div class="text-muted small">Reserved Withdrawals</div><div class="fs-5 fw-bold">{{ number_format($walletAudit['reserved_withdrawals'] ?? 0, 2) }} {{ $wallet->currency_code }}</div><div class="text-muted extra-small mt-1">From pending/processing withdrawals</div></div></div>
        <div class="col-md-3 col-sm-6"><div class="glass rounded-4 p-3 h-100"><div class="text-muted small">Pending Gap</div><div class="fs-5 fw-bold {{ ($walletAudit['pending_balance_gap'] ?? 0) == 0.0 ? '' : 'text-warning' }}">{{ number_format($walletAudit['pending_balance_gap'] ?? 0, 2) }}</div><div class="text-muted extra-small mt-1">Pending balance − reserved − pending prizes</div></div></div>
        <div class="col-md-3 col-sm-6"><div class="glass rounded-4 p-3 h-100"><div class="text-muted small">Completed Credits</div><div class="fs-5 fw-bold">{{ number_format($walletAudit['completed_credits'] ?? 0, 2) }}</div><div class="text-muted extra-small mt-1">Edit transaction rows below</div></div></div>
        <div class="col-md-3 col-sm-6"><div class="glass rounded-4 p-3 h-100"><div class="text-muted small">Completed Debits</div><div class="fs-5 fw-bold">{{ number_format($walletAudit['completed_debits'] ?? 0, 2) }}</div><div class="text-muted extra-small mt-1">Edit transaction rows below</div></div></div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-xl-7">
            <div class="glass rounded-4 overflow-hidden border-white-05">
                <div class="p-4 border-bottom border-white-05 d-flex align-items-center justify-content-between">
                    <h2 class="h6 fw-bold mb-0">Recent Transactions</h2>
                    <a href="{{ route('admin.earnings.transactions', ['q' => $wallet->user?->username]) }}" class="btn btn-sm btn-outline-dynamic rounded-3 px-3">Open full list</a>
                </div>
                <div class="table-responsive scrollbar-hidden">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-white-05">
                            <tr>
                                <th class="ps-4 text-muted small fw-bold text-uppercase py-3">Transaction</th>
                                <th class="text-muted small fw-bold text-uppercase py-3">Type</th>
                                <th class="text-muted small fw-bold text-uppercase py-3">Amount</th>
                                <th class="text-muted small fw-bold text-uppercase py-3">Status</th>
                                <th class="text-muted small fw-bold text-uppercase py-3 text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($wallet->transactions as $transaction)
                                <tr class="border-white-05 align-top">
                                    <td class="ps-4" colspan="5">
                                        <form method="POST" action="{{ route('admin.earnings.transactions.update', $transaction) }}" class="row g-2 align-items-end py-2">
                                            @csrf
                                            @method('PATCH')
                                            <div class="col-md-1">
                                                <div class="small fw-semibold">#{{ $transaction->id }}</div>
                                                <div class="text-muted extra-small">{{ $transaction->source_type }}</div>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label extra-small text-muted mb-0">Type</label>
                                                <select name="transaction_type" class="form-select form-select-sm border-0 bg-dark-soft rounded-3">
                                                    <option value="credit" @selected($transaction->transaction_type === 'credit')>Credit</option>
                                                    <option value="debit" @selected($transaction->transaction_type === 'debit')>Debit</option>
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label extra-small text-muted mb-0">Source</label>
                                                <input type="text" name="source_type" value="{{ $transaction->source_type }}" class="form-control form-control-sm border-0 bg-dark-soft rounded-3" maxlength="50" required>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label extra-small text-muted mb-0">Amount</label>
                                                <input type="number" step="0.01" min="0" name="amount" value="{{ number_format((float) $transaction->amount, 2, '.', '') }}" class="form-control form-control-sm border-0 bg-dark-soft rounded-3" required>
                                            </div>
                                            <div class="col-md-1">
                                                <label class="form-label extra-small text-muted mb-0">CCY</label>
                                                <input type="text" name="currency_code" value="{{ $transaction->currency_code }}" maxlength="3" class="form-control form-control-sm border-0 bg-dark-soft rounded-3 text-uppercase" required>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label extra-small text-muted mb-0">Status</label>
                                                <select name="status" class="form-select form-select-sm border-0 bg-dark-soft rounded-3">
                                                    @foreach (['pending','completed','failed','reversed'] as $status)
                                                        <option value="{{ $status }}" @selected($transaction->status === $status)>{{ str($status)->title() }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label extra-small text-muted mb-0">Note</label>
                                                <input type="text" name="note" value="{{ $transaction->note }}" maxlength="255" class="form-control form-control-sm border-0 bg-dark-soft rounded-3">
                                            </div>
                                            <div class="col-12 d-flex flex-wrap gap-2 justify-content-end">
                                                <button type="submit" class="btn btn-sm btn-outline-dynamic rounded-3 px-3">
                                                    <i class="bi bi-save me-1"></i>Save txn
                                                </button>
                                                @if (
                                                    $transaction->status === 'pending'
                                                    && $transaction->source_type === 'contest_reward'
                                                    && $transaction->transaction_type === 'credit'
                                                )
                                                    <button type="submit" form="approve-{{ $transaction->id }}" class="btn btn-sm btn-success rounded-3 px-3 fw-bold">
                                                        <i class="bi bi-check2-circle me-1"></i>Release to Available
                                                    </button>
                                                @endif
                                                @if ($transaction->status === 'completed' && $transaction->source_type !== 'withdrawal')
                                                    <button type="submit" form="reverse-{{ $transaction->id }}" class="btn btn-sm btn-outline-danger rounded-3 px-3">
                                                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reverse
                                                    </button>
                                                @endif
                                            </div>
                                        </form>
                                        @if (
                                            $transaction->status === 'pending'
                                            && $transaction->source_type === 'contest_reward'
                                            && $transaction->transaction_type === 'credit'
                                        )
                                            <form id="approve-{{ $transaction->id }}" method="POST" action="{{ route('admin.earnings.contest_prizes.approve', $transaction) }}"
                                                  onsubmit="return confirm('Approve this contest prize and move it to Available Balance?');" class="d-none">
                                                @csrf
                                            </form>
                                        @endif
                                        @if ($transaction->status === 'completed' && $transaction->source_type !== 'withdrawal')
                                            <form id="reverse-{{ $transaction->id }}" method="POST" action="{{ route('admin.earnings.transactions.reverse', $transaction) }}" class="d-none">
                                                @csrf
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center py-5 text-muted">No recent transactions found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-5">
            <div class="glass rounded-4 overflow-hidden border-white-05">
                <div class="p-4 border-bottom border-white-05">
                    <h2 class="h6 fw-bold mb-0">Recent Withdrawals</h2>
                </div>
                <div class="table-responsive scrollbar-hidden">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-white-05">
                            <tr>
                                <th class="ps-4 text-muted small fw-bold text-uppercase py-3">Request</th>
                                <th class="text-muted small fw-bold text-uppercase py-3">Amount</th>
                                <th class="text-muted small fw-bold text-uppercase py-3">Status</th>
                                <th class="text-muted small fw-bold text-uppercase py-3 text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($wallet->withdrawalRequests as $withdrawal)
                                <tr class="border-white-05 align-top">
                                    <td class="ps-4" colspan="4">
                                        <div class="small fw-semibold mb-1">#{{ $withdrawal->id }} · {{ number_format((float) $withdrawal->amount, 2) }} {{ $withdrawal->currency_code }}</div>
                                        <div class="text-muted extra-small mb-2">
                                            {{ $withdrawal->bank_name ?: 'Bank' }}
                                            · {{ $withdrawal->bank_account_number ?: ($withdrawal->account_ref ?: 'No bank details') }}
                                            · {{ $withdrawal->payoutTransaction ? 'Txn #'.$withdrawal->payoutTransaction->id : 'No linked payout txn' }}
                                        </div>
                                        <form method="POST" action="{{ route('admin.earnings.withdrawals.update', $withdrawal) }}" class="row g-2 align-items-end">
                                            @csrf
                                            @method('PATCH')
                                            <div class="col-md-4">
                                                <label class="form-label extra-small text-muted mb-0">Status</label>
                                                <select name="status" class="form-select form-select-sm border-0 bg-dark-soft rounded-3">
                                                    @foreach (['pending' => 'Pending', 'processing' => 'Processing', 'completed' => 'Completed', 'failed' => 'Failed', 'rejected' => 'Rejected'] as $value => $label)
                                                        <option value="{{ $value }}" @selected($withdrawal->status === $value)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-5">
                                                <label class="form-label extra-small text-muted mb-0">Failure / rejection reason</label>
                                                <input type="text" name="failure_reason" value="{{ $withdrawal->failure_reason }}" maxlength="255" class="form-control form-control-sm border-0 bg-dark-soft rounded-3" placeholder="Required for failed/rejected">
                                            </div>
                                            <div class="col-md-3 d-flex flex-wrap gap-2">
                                                <button type="submit" class="btn btn-sm btn-outline-dynamic rounded-3 px-3">
                                                    <i class="bi bi-save me-1"></i>Save
                                                </button>
                                                @if (in_array($withdrawal->status, ['pending', 'processing'], true))
                                                    <button type="submit" form="complete-wd-{{ $withdrawal->id }}" class="btn btn-sm btn-success rounded-3 px-3">
                                                        <i class="bi bi-check2-circle me-1"></i>Done
                                                    </button>
                                                @endif
                                            </div>
                                        </form>
                                        @if (in_array($withdrawal->status, ['pending', 'processing'], true))
                                            <form id="complete-wd-{{ $withdrawal->id }}" method="POST" action="{{ route('admin.earnings.withdrawals.complete', $withdrawal) }}" class="d-none"
                                                  onsubmit="return confirm('Confirm you already sent {{ number_format((float) $withdrawal->amount, 2) }} {{ $withdrawal->currency_code }} to this user\'s account. The user will be notified.');">
                                                @csrf
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center py-5 text-muted">No recent withdrawals found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
