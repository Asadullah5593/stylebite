@extends('admin.layouts.app')

@section('content')
<div class="earnings-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold">Earnings</span>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">Wallets</span>
    </nav>

    <div class="mb-4">
        <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">Earnings</h1>
        <p class="text-muted small mb-0">Wallet balances, history counts, and linked creator accounts</p>
    </div>

    @include('admin.earnings.partials.tabs')

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <div class="glass rounded-4 p-3 h-100">
                <div class="text-muted small">Wallets</div>
                <div class="fs-4 fw-bold">{{ number_format($walletStats['total'] ?? 0) }}</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="glass rounded-4 p-3 h-100">
                <div class="text-muted small">Available</div>
                <div class="fs-4 fw-bold">{{ number_format($walletStats['available'] ?? 0, 2) }}</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="glass rounded-4 p-3 h-100">
                <div class="text-muted small">Pending</div>
                <div class="fs-4 fw-bold">{{ number_format($walletStats['pending'] ?? 0, 2) }}</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="glass rounded-4 p-3 h-100">
                <div class="text-muted small">Lifetime Earned</div>
                <div class="fs-4 fw-bold">{{ number_format($walletStats['earned'] ?? 0, 2) }}</div>
            </div>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.earnings.wallets') }}" class="glass rounded-4 p-3 d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="position-relative flex-grow-1" style="min-width: 250px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control ps-5 border-0 bg-dark-soft rounded-3" placeholder="Search wallet owner, email, currency...">
        </div>

        <select name="currency_code" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto; min-width: 160px;">
            <option value="">All Currencies</option>
            @foreach ($currencyOptions as $currencyOption)
                <option value="{{ $currencyOption }}" @selected(request('currency_code') === $currencyOption)>{{ $currencyOption }}</option>
            @endforeach
        </select>

        <button class="btn btn-outline-dynamic rounded-3 px-3" type="submit"><i class="bi bi-funnel me-2"></i>Filter</button>
        <a href="{{ route('admin.earnings.wallets') }}" class="btn btn-outline-dynamic rounded-3 px-3"><i class="bi bi-arrow-clockwise me-2"></i>Reset</a>
        <a href="{{ route('admin.earnings.transactions.export', request()->query()) }}" class="btn btn-outline-dynamic rounded-3 px-3"><i class="bi bi-download me-2"></i>Export CSV</a>
    </form>

    <div class="glass rounded-4 overflow-hidden border-white-05">
        <div class="table-responsive scrollbar-hidden">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-white-05">
                    <tr>
                        <th class="ps-4 text-muted small fw-bold text-uppercase py-3">User</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Currency</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Available</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Pending</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Lifetime</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Activity</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Updated</th>
                        <th class="text-muted small fw-bold text-uppercase py-3 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($wallets as $wallet)
                        <tr class="border-white-05">
                            <td class="ps-4">
                                @if ($wallet->user)
                                    <a href="{{ route('admin.users.show', $wallet->user) }}" class="text-decoration-none d-flex align-items-center gap-3">
                                        @if ($wallet->user->avatar_url)
                                            <img src="{{ $wallet->user->avatar_url }}" alt="{{ $wallet->user->username }}" class="rounded-circle border border-white-05" width="42" height="42" style="object-fit: cover;">
                                        @else
                                            <div class="rounded-circle border border-white-05 d-flex align-items-center justify-content-center text-muted" style="width:42px;height:42px;">
                                                <i class="bi bi-person"></i>
                                            </div>
                                        @endif
                                        <div>
                                            <div class="small fw-semibold">{{ $wallet->user->full_name ?: '@'.$wallet->user->username }}</div>
                                            <div class="text-muted extra-small">{{ '@'.$wallet->user->username }}</div>
                                        </div>
                                    </a>
                                @else
                                    <span class="text-muted small">Missing user</span>
                                @endif
                            </td>
                            <td><span class="badge bg-secondary-soft text-muted rounded-pill">{{ $wallet->currency_code }}</span></td>
                            <td><span class="fw-semibold">{{ number_format((float) $wallet->available_balance, 2) }}</span></td>
                            <td><span class="text-warning">{{ number_format((float) $wallet->pending_balance, 2) }}</span></td>
                            <td>
                                <div class="small fw-semibold">Earned {{ number_format((float) $wallet->lifetime_earned, 2) }}</div>
                                <div class="text-muted extra-small">Withdrawn {{ number_format((float) $wallet->lifetime_withdrawn, 2) }}</div>
                            </td>
                            <td>
                                <div class="small">{{ number_format($wallet->transactions_count) }} transactions</div>
                                <div class="text-muted extra-small">{{ number_format($wallet->withdrawal_requests_count) }} withdrawals</div>
                            </td>
                            <td><span class="text-muted small">{{ $wallet->updated_balance_at?->format('M d, Y H:i') ?? '-' }}</span></td>
                            <td class="text-end">
                                <a href="{{ route('admin.earnings.show', $wallet) }}" class="btn btn-sm btn-outline-dynamic rounded-3 px-3">
                                    <i class="bi bi-wallet2 me-1"></i>Details
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center py-5 text-muted">No wallets found for the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 bg-white-05 border-top border-white-05 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="text-muted small">
                Showing <span class="text-emphasis-dynamic fw-bold">{{ $wallets->firstItem() ?? 0 }}-{{ $wallets->lastItem() ?? 0 }}</span>
                of <span class="text-emphasis-dynamic fw-bold">{{ number_format($wallets->total()) }}</span> wallets
            </div>
            {{ $wallets->links() }}
        </div>
    </div>
</div>
@endsection
