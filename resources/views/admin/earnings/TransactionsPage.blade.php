@extends('admin.layouts.app')

@section('content')
<div class="earnings-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold">Earnings</span>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">Transactions</span>
    </nav>

    <div class="mb-4">
        <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">Earnings</h1>
        <p class="text-muted small mb-0">Credits, debits, payout links, and transaction metadata preview</p>
    </div>

    @include('admin.earnings.partials.tabs')

    @if (session('status'))
        <div class="alert alert-success rounded-3 border-0 mb-4">{{ session('status') }}</div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-2">
            <div class="glass rounded-4 p-3 h-100">
                <div class="text-muted small">Total</div>
                <div class="fs-5 fw-bold">{{ number_format($transactionStats['total'] ?? 0) }}</div>
            </div>
        </div>
        <div class="col-6 col-xl-2">
            <div class="glass rounded-4 p-3 h-100">
                <div class="text-muted small">Credits</div>
                <div class="fs-5 fw-bold">{{ number_format($transactionStats['credits'] ?? 0) }}</div>
            </div>
        </div>
        <div class="col-6 col-xl-2">
            <div class="glass rounded-4 p-3 h-100">
                <div class="text-muted small">Debits</div>
                <div class="fs-5 fw-bold">{{ number_format($transactionStats['debits'] ?? 0) }}</div>
            </div>
        </div>
        <div class="col-6 col-xl-2">
            <div class="glass rounded-4 p-3 h-100">
                <div class="text-muted small">Completed</div>
                <div class="fs-5 fw-bold">{{ number_format($transactionStats['completed'] ?? 0) }}</div>
            </div>
        </div>
        <div class="col-6 col-xl-2">
            <div class="glass rounded-4 p-3 h-100">
                <div class="text-muted small">Pending</div>
                <div class="fs-5 fw-bold">{{ number_format($transactionStats['pending'] ?? 0) }}</div>
            </div>
        </div>
        <div class="col-6 col-xl-2">
            <div class="glass rounded-4 p-3 h-100">
                <div class="text-muted small">Page Amount</div>
                <div class="fs-5 fw-bold">{{ number_format((float) $transactions->getCollection()->sum('amount'), 2) }}</div>
            </div>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.earnings.transactions') }}" class="glass rounded-4 p-3 d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="position-relative flex-grow-1" style="min-width: 250px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control ps-5 border-0 bg-dark-soft rounded-3" placeholder="Search note, user, type, status...">
        </div>

        <select name="status" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Status</option>
            @foreach (['pending' => 'Pending', 'completed' => 'Completed', 'failed' => 'Failed', 'reversed' => 'Reversed'] as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <select name="transaction_type" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Types</option>
            @foreach (['credit' => 'Credit', 'debit' => 'Debit'] as $value => $label)
                <option value="{{ $value }}" @selected(request('transaction_type') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <select name="source_type" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Sources</option>
            @foreach (['contest_reward' => 'Contest Reward', 'engagement_bonus' => 'Engagement Bonus', 'referral_bonus' => 'Referral Bonus', 'withdrawal' => 'Withdrawal', 'adjustment' => 'Adjustment'] as $value => $label)
                <option value="{{ $value }}" @selected(request('source_type') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <button class="btn btn-outline-dynamic rounded-3 px-3" type="submit"><i class="bi bi-funnel me-2"></i>Filter</button>
        <a href="{{ route('admin.earnings.transactions') }}" class="btn btn-outline-dynamic rounded-3 px-3"><i class="bi bi-arrow-clockwise me-2"></i>Reset</a>
        <a href="{{ route('admin.earnings.transactions.export', request()->query()) }}" class="btn btn-outline-dynamic rounded-3 px-3"><i class="bi bi-download me-2"></i>Export CSV</a>
    </form>

    <div class="glass rounded-4 overflow-hidden border-white-05">
        <div class="table-responsive scrollbar-hidden">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-white-05">
                    <tr>
                        <th class="ps-4 text-muted small fw-bold text-uppercase py-3">Transaction</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">User</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Type</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Source</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Amount</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Status</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Meta</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Created</th>
                        <th class="text-muted small fw-bold text-uppercase py-3 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transactions as $transaction)
                        <tr class="border-white-05">
                            <td class="ps-4">
                                <div class="small fw-semibold">#{{ $transaction->id }}</div>
                                <div class="text-muted extra-small">{{ $transaction->note ?: 'No note' }}</div>
                            </td>
                            <td>
                                @if ($transaction->user)
                                    <a href="{{ route('admin.users.show', $transaction->user) }}" class="text-decoration-none">
                                        <div class="small fw-semibold">{{ $transaction->user->full_name ?: '@'.$transaction->user->username }}</div>
                                        <div class="text-muted extra-small">{{ '@'.$transaction->user->username }}</div>
                                    </a>
                                @else
                                    <span class="text-muted small">Missing user</span>
                                @endif
                            </td>
                            <td><span class="badge {{ $transaction->transaction_type === 'credit' ? 'bg-success-soft text-success' : 'bg-warning-soft text-warning' }} rounded-pill">{{ str($transaction->transaction_type)->title() }}</span></td>
                            <td>
                                <div class="small">{{ str($transaction->source_type)->replace('_', ' ')->title() }}</div>
                                <div class="text-muted extra-small">Wallet #{{ $transaction->wallet_id }}</div>
                            </td>
                            <td><span class="fw-semibold">{{ number_format((float) $transaction->amount, 2) }} {{ $transaction->currency_code }}</span></td>
                            <td><span class="badge {{ $transaction->status === 'completed' ? 'bg-success-soft text-success' : ($transaction->status === 'pending' ? 'bg-warning-soft text-warning' : 'bg-danger-soft text-danger') }} rounded-pill">{{ str($transaction->status)->title() }}</span></td>
                            <td style="min-width: 220px;">
                                @if ($transaction->metadata_json)
                                    <button class="btn btn-sm btn-outline-dynamic rounded-3 px-3" type="button" data-bs-toggle="modal" data-bs-target="#transactionMeta{{ $transaction->id }}">
                                        <i class="bi bi-card-text me-1"></i>Preview
                                    </button>
                                    <div class="modal fade" id="transactionMeta{{ $transaction->id }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                            <div class="modal-content bg-dark border border-white-10">
                                                <div class="modal-header border-white-10">
                                                    <h5 class="modal-title">Transaction #{{ $transaction->id }} metadata</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <pre class="mb-0 small text-white bg-black rounded-3 p-3 border border-white-10" style="white-space: pre-wrap;">{{ json_encode($transaction->metadata_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <span class="text-muted small">No metadata</span>
                                @endif
                            </td>
                            <td><span class="text-muted small">{{ $transaction->created_at?->format('M d, Y H:i') }}</span></td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-2 flex-wrap justify-content-end">
                                    @if ($transaction->wallet)
                                        <a href="{{ route('admin.earnings.show', $transaction->wallet) }}" class="btn btn-sm btn-outline-dynamic rounded-3 px-3">
                                            <i class="bi bi-wallet2 me-1"></i>Wallet
                                        </a>
                                    @endif
                                    @if ($transaction->status === 'completed' && $transaction->source_type !== 'withdrawal')
                                        <form method="POST" action="{{ route('admin.earnings.transactions.reverse', $transaction) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-3 px-3">
                                                <i class="bi bi-arrow-counterclockwise me-1"></i>Reverse
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center py-5 text-muted">No transactions found for the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 bg-white-05 border-top border-white-05 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="text-muted small">
                Showing <span class="text-emphasis-dynamic fw-bold">{{ $transactions->firstItem() ?? 0 }}-{{ $transactions->lastItem() ?? 0 }}</span>
                of <span class="text-emphasis-dynamic fw-bold">{{ number_format($transactions->total()) }}</span> transactions
            </div>
            {{ $transactions->links() }}
        </div>
    </div>
</div>
@endsection
