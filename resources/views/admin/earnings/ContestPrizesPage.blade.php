@extends('admin.layouts.app')

@section('content')
<div class="earnings-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold">Earnings</span>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">Contest Prizes</span>
    </nav>

    <div class="mb-4">
        <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">Contest Prizes</h1>
        <p class="text-muted small mb-0">Pending contest winnings await admin approval before becoming withdrawable</p>
    </div>

    @include('admin.earnings.partials.tabs')

    @if (session('status'))
        <div class="alert alert-success rounded-3 border-0 mb-4">{{ session('status') }}</div>
    @endif

    @include('admin.earnings.partials.pending-prizes-alert', [
        'pendingContestPrizeCount' => $prizeStats['pending'] ?? 0,
    ])

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <div class="glass rounded-4 p-3 h-100">
                <div class="text-muted small">Total Contest Rewards</div>
                <div class="fs-5 fw-bold">{{ number_format($prizeStats['total'] ?? 0) }}</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="glass rounded-4 p-3 h-100">
                <div class="text-muted small">Pending Approval</div>
                <div class="fs-5 fw-bold">{{ number_format($prizeStats['pending'] ?? 0) }}</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="glass rounded-4 p-3 h-100">
                <div class="text-muted small">Approved</div>
                <div class="fs-5 fw-bold">{{ number_format($prizeStats['approved'] ?? 0) }}</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="glass rounded-4 p-3 h-100">
                <div class="text-muted small">Pending Amount</div>
                <div class="fs-5 fw-bold">{{ number_format($prizeStats['pending_amount'] ?? 0, 2) }}</div>
            </div>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.earnings.contest_prizes') }}" class="glass rounded-4 p-3 d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="position-relative flex-grow-1" style="min-width: 250px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control ps-5 border-0 bg-dark-soft rounded-3" placeholder="Search winner, contest note, status...">
        </div>

        <select name="status" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Status</option>
            @foreach (['pending' => 'Pending Approval', 'completed' => 'Approved', 'failed' => 'Failed', 'reversed' => 'Reversed'] as $value => $label)
                <option value="{{ $value }}" @selected(($statusFilter ?? request('status')) === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <button class="btn btn-outline-dynamic rounded-3 px-3" type="submit"><i class="bi bi-funnel me-2"></i>Filter</button>
        <a href="{{ route('admin.earnings.contest_prizes', ['status' => 'pending']) }}" class="btn btn-outline-dynamic rounded-3 px-3">Pending only</a>
        <a href="{{ route('admin.earnings.contest_prizes') }}" class="btn btn-outline-dynamic rounded-3 px-3"><i class="bi bi-arrow-clockwise me-2"></i>Reset</a>
    </form>

    <div class="glass rounded-4 overflow-hidden border-white-05">
        <div class="table-responsive scrollbar-hidden">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-white-05">
                    <tr>
                        <th class="ps-4 text-muted small fw-bold text-uppercase py-3">Reward</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Winner</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Contest</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Place</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Amount</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Status</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Completed</th>
                        <th class="text-muted small fw-bold text-uppercase py-3 text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($prizes as $prize)
                        @php
                            $place = $places->get((int) $prize->source_id);
                            $contest = $place?->contest;
                            $metadata = is_array($prize->metadata_json) ? $prize->metadata_json : [];
                            $placeNumber = (int) ($place?->place ?? ($metadata['place'] ?? 0));
                        @endphp
                        <tr>
                            <td class="ps-4 py-3">
                                <div class="fw-bold">#{{ $prize->id }}</div>
                                <div class="small text-muted">Place ID {{ $prize->source_id }}</div>
                            </td>
                            <td class="py-3">
                                <div class="fw-bold">{{ $prize->user?->full_name ?: '@'.($prize->user?->username ?? 'unknown') }}</div>
                                <div class="small text-muted">ID {{ $prize->user_id }} · {{ '@'.($prize->user?->username ?? 'unknown') }}</div>
                            </td>
                            <td class="py-3">
                                <div class="fw-bold">{{ $contest?->title ?? ($metadata['title'] ?? $prize->note ?? 'Contest') }}</div>
                                <div class="small text-muted">Contest #{{ $contest?->id ?? ($metadata['contest_id'] ?? '—') }}</div>
                            </td>
                            <td class="py-3">
                                {{ $placeNumber > 0 ? $placeNumber : '—' }}
                            </td>
                            <td class="py-3">
                                <div class="fw-bold">{{ number_format((float) $prize->amount, 2) }} {{ $prize->currency_code }}</div>
                            </td>
                            <td class="py-3">
                                @if ($prize->status === 'pending')
                                    <span class="badge bg-warning-subtle">Pending Approval</span>
                                @elseif ($prize->status === 'completed')
                                    <span class="badge bg-success-subtle">Approved</span>
                                @else
                                    <span class="badge bg-info-subtle">{{ ucfirst($prize->status) }}</span>
                                @endif
                                @if (!empty($metadata['approved_by_admin_id']))
                                    <div class="small text-muted mt-1">Admin #{{ $metadata['approved_by_admin_id'] }}</div>
                                @endif
                            </td>
                            <td class="py-3 small text-muted">
                                {{ optional($contest?->result_at ?? $contest?->updated_at ?? $prize->created_at)->toDateTimeString() }}
                            </td>
                            <td class="py-3 text-end pe-4">
                                @if ($prize->status === 'pending')
                                    <form method="POST" action="{{ route('admin.earnings.contest_prizes.approve', $prize) }}" class="d-inline"
                                          onsubmit="return confirm('Approve this contest prize and move it to Available Balance so the winner can withdraw?');">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-success rounded-3 fw-bold px-3">
                                            <i class="bi bi-check2-circle me-1"></i>Approve Prize
                                        </button>
                                    </form>
                                @else
                                    <span class="small text-muted">Approved</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-5">No contest prize rewards found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $prizes->links() }}
    </div>
</div>
@endsection
