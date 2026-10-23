@php
    $pendingContestPrizeCount = $pendingContestPrizeCount
        ?? (\App\Http\Controllers\Admin\EarningsController::tabCounts()['contest_prizes'] ?? 0);
@endphp

@if ($pendingContestPrizeCount > 0)
    <div class="alert alert-warning border-0 rounded-4 d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <div class="fw-bold">{{ number_format($pendingContestPrizeCount) }} contest prize{{ $pendingContestPrizeCount === 1 ? '' : 's' }} waiting for approval</div>
            <div class="small mb-0">Approve pending prizes to move money into Available Balance so winners can withdraw.</div>
        </div>
        <a href="{{ route('admin.earnings.contest_prizes', ['status' => 'pending']) }}" class="btn btn-success rounded-3 px-3 fw-bold">
            <i class="bi bi-check2-circle me-1"></i>Review &amp; Approve
        </a>
    </div>
@endif
