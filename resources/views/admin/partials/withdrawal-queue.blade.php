{{--
    Withdrawal queue panel — pending/processing finance requests. Shown on
    both the Overview and Money tabs, so it lives in a partial.

    $withdrawalQueue — user, method, amount, status, time
--}}
<div class="card glass border-0 rounded-4 p-3 p-xl-4 h-100 list-shell">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="panel-title mb-1">Withdrawal Queue</h3>
            <p class="text-muted extra-small mb-0">Finance requests currently pending or processing</p>
        </div>
        <a href="{{ route('admin.earnings.withdrawals') }}" class="panel-link">Finance queue</a>
    </div>
    <div class="d-flex flex-column gap-2 bottom-card-list">
        @forelse ($withdrawalQueue as $withdrawal)
            <a href="{{ route('admin.earnings.withdrawals') }}" class="bottom-list-row queue-row text-decoration-none">
                <div class="queue-icon success"><i class="bi bi-cash-stack"></i></div>
                <div class="flex-grow-1 min-w-0">
                    <p class="recent-title mb-1">{{ $withdrawal['user'] }}</p>
                    <p class="text-muted extra-small mb-0">{{ $withdrawal['method'] }} · {{ $withdrawal['amount'] }}</p>
                </div>
                <div class="text-end">
                    <span class="pill-status payout-status {{ str_replace('_', '-', $withdrawal['status']) }}">{{ str($withdrawal['status'])->replace('_', ' ')->title() }}</span>
                    <div class="text-muted extra-small mt-1">{{ $withdrawal['time'] }}</div>
                </div>
            </a>
        @empty
            <div class="text-muted small">No pending withdrawal requests right now.</div>
        @endforelse
    </div>
</div>
