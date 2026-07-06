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

                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <div class="border border-white-05 rounded-4 p-3 h-100">
                            <div class="text-muted small mb-1">Available Balance</div>
                            <div class="fs-4 fw-bold">{{ number_format((float) $wallet->available_balance, 2) }} {{ $wallet->currency_code }}</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="border border-white-05 rounded-4 p-3 h-100">
                            <div class="text-muted small mb-1">Pending Balance</div>
                            <div class="fs-4 fw-bold text-warning">{{ number_format((float) $wallet->pending_balance, 2) }} {{ $wallet->currency_code }}</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="border border-white-05 rounded-4 p-3 h-100">
                            <div class="text-muted small mb-1">Lifetime Earned</div>
                            <div class="fw-semibold">{{ number_format((float) $wallet->lifetime_earned, 2) }}</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="border border-white-05 rounded-4 p-3 h-100">
                            <div class="text-muted small mb-1">Lifetime Withdrawn</div>
                            <div class="fw-semibold">{{ number_format((float) $wallet->lifetime_withdrawn, 2) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="glass rounded-4 p-4 h-100">
                <h2 class="h6 fw-bold mb-3">Manual Adjustment</h2>
                <form method="POST" action="{{ route('admin.earnings.adjustments.store', $wallet) }}" class="d-flex flex-column gap-3">
                    @csrf
                    <select name="transaction_type" class="form-select border-0 bg-dark-soft rounded-3 text-muted">
                        <option value="credit">Credit</option>
                        <option value="debit">Debit</option>
                    </select>
                    <input type="number" step="0.01" min="0.01" name="amount" class="form-control border-0 bg-dark-soft rounded-3" placeholder="Amount">
                    <textarea name="note" rows="3" class="form-control border-0 bg-dark-soft rounded-3" placeholder="Reason for adjustment"></textarea>
                    <button type="submit" class="btn btn-outline-dynamic rounded-3 px-3">
                        <i class="bi bi-plus-circle me-2"></i>Apply adjustment
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3 col-sm-6"><div class="glass rounded-4 p-3 h-100"><div class="text-muted small">Reserved Withdrawals</div><div class="fs-5 fw-bold">{{ number_format($walletAudit['reserved_withdrawals'] ?? 0, 2) }} {{ $wallet->currency_code }}</div></div></div>
        <div class="col-md-3 col-sm-6"><div class="glass rounded-4 p-3 h-100"><div class="text-muted small">Pending Gap</div><div class="fs-5 fw-bold {{ ($walletAudit['pending_balance_gap'] ?? 0) == 0.0 ? '' : 'text-warning' }}">{{ number_format($walletAudit['pending_balance_gap'] ?? 0, 2) }}</div></div></div>
        <div class="col-md-3 col-sm-6"><div class="glass rounded-4 p-3 h-100"><div class="text-muted small">Completed Credits</div><div class="fs-5 fw-bold">{{ number_format($walletAudit['completed_credits'] ?? 0, 2) }}</div></div></div>
        <div class="col-md-3 col-sm-6"><div class="glass rounded-4 p-3 h-100"><div class="text-muted small">Completed Debits</div><div class="fs-5 fw-bold">{{ number_format($walletAudit['completed_debits'] ?? 0, 2) }}</div></div></div>
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
                                <tr class="border-white-05">
                                    <td class="ps-4">
                                        <div class="small fw-semibold">#{{ $transaction->id }}</div>
                                        <div class="text-muted extra-small">{{ $transaction->note ?: 'No note' }}</div>
                                    </td>
                                    <td><span class="badge {{ $transaction->transaction_type === 'credit' ? 'bg-success-soft text-success' : 'bg-warning-soft text-warning' }} rounded-pill">{{ str($transaction->transaction_type)->title() }}</span></td>
                                    <td><span class="fw-semibold">{{ number_format((float) $transaction->amount, 2) }} {{ $transaction->currency_code }}</span></td>
                                    <td><span class="badge {{ $transaction->status === 'completed' ? 'bg-success-soft text-success' : ($transaction->status === 'reversed' ? 'bg-danger-soft text-danger' : 'bg-warning-soft text-warning') }} rounded-pill">{{ str($transaction->status)->title() }}</span></td>
                                    <td class="text-end">
                                        @if ($transaction->status === 'completed' && $transaction->source_type !== 'withdrawal')
                                            <form method="POST" action="{{ route('admin.earnings.transactions.reverse', $transaction) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-3 px-3">
                                                    <i class="bi bi-arrow-counterclockwise me-1"></i>Reverse
                                                </button>
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
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($wallet->withdrawalRequests as $withdrawal)
                                <tr class="border-white-05">
                                    <td class="ps-4">
                                        <div class="small fw-semibold">#{{ $withdrawal->id }}</div>
                                        <div class="text-muted extra-small">{{ $withdrawal->account_ref ?: 'No account ref' }}</div>
                                        <div class="text-muted extra-small">{{ $withdrawal->payoutTransaction ? 'Txn #'.$withdrawal->payoutTransaction->id : 'No linked payout txn' }}</div>
                                    </td>
                                    <td><span class="fw-semibold">{{ number_format((float) $withdrawal->amount, 2) }} {{ $withdrawal->currency_code }}</span></td>
                                    <td><span class="badge {{ $withdrawal->status === 'completed' ? 'bg-success-soft text-success' : ($withdrawal->status === 'processing' ? 'bg-info-soft text-info' : ($withdrawal->status === 'pending' ? 'bg-warning-soft text-warning' : 'bg-danger-soft text-danger')) }} rounded-pill">{{ str($withdrawal->status)->title() }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center py-5 text-muted">No recent withdrawals found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
