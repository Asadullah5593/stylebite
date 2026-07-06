@extends('admin.layouts.app')

@section('content')
<div class="earnings-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold">Earnings</span>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">Reconciliation</span>
    </nav>

    <div class="mb-4">
        <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">Earnings</h1>
        <p class="text-muted small mb-0">Formal wallet reconciliation checks with exportable audit rows</p>
    </div>

    @include('admin.earnings.partials.tabs')

    <div class="row g-3 mb-4">
        <div class="col-md-3 col-sm-6"><div class="glass rounded-4 p-3 h-100"><div class="text-muted small">Wallets Checked</div><div class="fs-4 fw-bold">{{ number_format($reconciliationStats['wallets'] ?? 0) }}</div></div></div>
        <div class="col-md-3 col-sm-6"><div class="glass rounded-4 p-3 h-100"><div class="text-muted small">Pending Gaps</div><div class="fs-4 fw-bold">{{ number_format($reconciliationStats['mismatched_pending'] ?? 0) }}</div></div></div>
        <div class="col-md-3 col-sm-6"><div class="glass rounded-4 p-3 h-100"><div class="text-muted small">Missing Linked Txn</div><div class="fs-4 fw-bold">{{ number_format($reconciliationStats['missing_transactions'] ?? 0) }}</div></div></div>
        <div class="col-md-3 col-sm-6"><div class="glass rounded-4 p-3 h-100"><div class="text-muted small">Negative Available</div><div class="fs-4 fw-bold">{{ number_format($reconciliationStats['negative_available'] ?? 0) }}</div></div></div>
    </div>

    <form method="GET" action="{{ route('admin.earnings.reconciliation') }}" class="glass rounded-4 p-3 d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="position-relative flex-grow-1" style="min-width: 250px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control ps-5 border-0 bg-dark-soft rounded-3" placeholder="Search wallet owner or currency...">
        </div>

        <select name="currency_code" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Currencies</option>
            @foreach ($reconciliationRows->pluck('currency_code')->unique()->filter()->values() as $currencyCode)
                <option value="{{ $currencyCode }}" @selected(request('currency_code') === $currencyCode)>{{ $currencyCode }}</option>
            @endforeach
        </select>

        <select name="issue" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Results</option>
            <option value="pending_gap" @selected(request('issue') === 'pending_gap')>Pending Gaps</option>
            <option value="missing_transactions" @selected(request('issue') === 'missing_transactions')>Missing Transactions</option>
            <option value="negative_available" @selected(request('issue') === 'negative_available')>Negative Available</option>
        </select>

        <button class="btn btn-outline-dynamic rounded-3 px-3" type="submit"><i class="bi bi-funnel me-2"></i>Filter</button>
        <a href="{{ route('admin.earnings.reconciliation') }}" class="btn btn-outline-dynamic rounded-3 px-3"><i class="bi bi-arrow-clockwise me-2"></i>Reset</a>
        <a href="{{ route('admin.earnings.reconciliation.export', request()->query()) }}" class="btn btn-outline-dynamic rounded-3 px-3"><i class="bi bi-download me-2"></i>Export CSV</a>
    </form>

    <div class="glass rounded-4 overflow-hidden border-white-05">
        <div class="table-responsive scrollbar-hidden">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-white-05">
                    <tr>
                        <th class="ps-4 text-muted small fw-bold text-uppercase py-3">Wallet</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Balances</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Withdrawals</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Transactions</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Issues</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Updated</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reconciliationRows as $row)
                        <tr class="border-white-05">
                            <td class="ps-4">
                                <div class="small fw-semibold">{{ $row['user_name'] }}</div>
                                <div class="text-muted extra-small">Wallet #{{ $row['wallet_id'] }} · {{ $row['currency_code'] }}</div>
                                @if ($row['user'])
                                    <a href="{{ route('admin.users.show', $row['user']) }}" class="text-decoration-none extra-small">Open user</a>
                                @endif
                            </td>
                            <td>
                                <div class="small">Available {{ number_format((float) $row['available_balance'], 2) }}</div>
                                <div class="text-muted extra-small">Pending {{ number_format((float) $row['pending_balance'], 2) }}</div>
                                <div class="text-muted extra-small">Gap {{ number_format((float) $row['pending_gap'], 2) }}</div>
                            </td>
                            <td>
                                <div class="small">Reserved {{ number_format((float) $row['reserved_withdrawals'], 2) }}</div>
                                <div class="text-muted extra-small">Completed {{ number_format((float) $row['completed_withdrawals'], 2) }}</div>
                                <div class="text-muted extra-small">Missing linked {{ number_format((int) $row['missing_withdrawal_transactions']) }}</div>
                            </td>
                            <td>
                                <div class="small">Credits {{ number_format((float) $row['completed_credits'], 2) }}</div>
                                <div class="text-muted extra-small">Debits {{ number_format((float) $row['completed_debits'], 2) }}</div>
                            </td>
                            <td style="min-width: 220px;">
                                @if (count($row['issues']))
                                    <div class="d-flex flex-wrap gap-2">
                                        @foreach ($row['issues'] as $issue)
                                            <span class="badge bg-warning-soft text-warning rounded-pill">{{ $issue }}</span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="badge bg-success-soft text-success rounded-pill">Balanced</span>
                                @endif
                            </td>
                            <td><span class="text-muted small">{{ $row['updated_balance_at']?->format('M d, Y H:i') ?? '-' }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center py-5 text-muted">No reconciliation rows found for the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 bg-white-05 border-top border-white-05 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="text-muted small">
                Showing <span class="text-emphasis-dynamic fw-bold">{{ $reconciliationRows->firstItem() ?? 0 }}-{{ $reconciliationRows->lastItem() ?? 0 }}</span>
                of <span class="text-emphasis-dynamic fw-bold">{{ number_format($reconciliationRows->total()) }}</span> reconciliation rows
            </div>
            {{ $reconciliationRows->links() }}
        </div>
    </div>
</div>
@endsection
