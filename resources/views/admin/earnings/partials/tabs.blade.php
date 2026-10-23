@php
    $earningsTabCounts = $earningsTabCounts
        ?? \App\Http\Controllers\Admin\EarningsController::tabCounts();
    $tabs = [
        ['route' => 'admin.earnings.wallets', 'params' => [], 'key' => 'wallets', 'label' => 'Wallets'],
        ['route' => 'admin.earnings.contest_prizes', 'params' => ['status' => 'pending'], 'key' => 'contest_prizes', 'label' => 'Contest Prizes'],
        ['route' => 'admin.earnings.transactions', 'params' => [], 'key' => 'transactions', 'label' => 'Transactions'],
        ['route' => 'admin.earnings.withdrawals', 'params' => [], 'key' => 'withdrawals', 'label' => 'Withdrawals'],
        ['route' => 'admin.earnings.reconciliation', 'params' => [], 'key' => 'reconciliation', 'label' => 'Reconciliation'],
    ];
@endphp

<div class="mb-4">
    <div class="d-flex flex-wrap gap-1 p-1 rounded-4 glass border border-white-05">
        @foreach ($tabs as $tab)
            <a href="{{ route($tab['route'], $tab['params']) }}" class="btn btn-sm rounded-3 px-2 py-2 fw-bold {{ request()->routeIs($tab['route']) ? 'bg-primary-gradient text-white shadow-glow' : 'text-muted hover-bg-white-10' }}">
                {{ $tab['label'] }}
                @if ($tab['key'] === 'contest_prizes' && ($earningsTabCounts[$tab['key']] ?? 0) > 0)
                    <span class="ms-1 badge text-bg-warning">{{ number_format($earningsTabCounts[$tab['key']]) }} pending</span>
                @else
                    <span class="ms-1 opacity-50 small">{{ number_format($earningsTabCounts[$tab['key']] ?? 0) }}</span>
                @endif
            </a>
        @endforeach
    </div>
</div>
