@extends('admin.layouts.app')

@section('content')
<div class="contests-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold">Contests</span>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">Participants</span>
    </nav>

    <div class="mb-4">
        <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">Contests</h1>
        <p class="text-muted small mb-0">Contests, teams, submissions and votes</p>
    </div>

    @include('admin.contests.partials.tabs')

    @if (session('status'))
        <div class="alert alert-success rounded-3 border-0 mb-4">{{ session('status') }}</div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-md-3 col-sm-6"><div class="glass rounded-4 p-3 h-100"><div class="text-muted small">Participants</div><div class="fs-4 fw-bold">{{ number_format($participantStats['total'] ?? 0) }}</div></div></div>
        <div class="col-md-3 col-sm-6"><div class="glass rounded-4 p-3 h-100"><div class="text-muted small">Approved</div><div class="fs-4 fw-bold">{{ number_format($participantStats['approved'] ?? 0) }}</div></div></div>
        <div class="col-md-3 col-sm-6"><div class="glass rounded-4 p-3 h-100"><div class="text-muted small">Pending Review</div><div class="fs-4 fw-bold">{{ number_format($participantStats['pending'] ?? 0) }}</div></div></div>
        <div class="col-md-3 col-sm-6"><div class="glass rounded-4 p-3 h-100"><div class="text-muted small">Banned</div><div class="fs-4 fw-bold">{{ number_format($participantStats['banned'] ?? 0) }}</div></div></div>
    </div>

    <form method="GET" action="{{ route('admin.contests.participants') }}" class="glass rounded-4 p-3 d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="position-relative flex-grow-1" style="min-width: 250px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control ps-5 border-0 bg-dark-soft rounded-3" placeholder="Search participant, contest, role...">
        </div>

        <select name="status" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Status</option>
            @foreach (['joined' => 'Joined', 'approved' => 'Approved', 'rejected' => 'Rejected', 'withdrawn' => 'Withdrawn', 'banned' => 'Banned'] as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <button class="btn btn-outline-dynamic rounded-3 px-3" type="submit"><i class="bi bi-funnel me-2"></i>Filter</button>
        <a href="{{ route('admin.contests.participants') }}" class="btn btn-outline-dynamic rounded-3 px-3"><i class="bi bi-arrow-clockwise me-2"></i>Reset</a>
    </form>

    <div class="glass rounded-4 overflow-hidden border-white-05">
        <div class="table-responsive scrollbar-hidden">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-white-05">
                    <tr>
                        <th class="ps-4 text-muted small fw-bold text-uppercase py-3">Participant</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Contest</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Role</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Status</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Score</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Joined</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Approved</th>
                        <th class="text-muted small fw-bold text-uppercase py-3 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($participants as $participant)
                        @php $reviewModalId = 'participantReview'.$participant->id; @endphp
                        <tr class="border-white-05">
                            <td class="ps-4">
                                @if ($participant->user)
                                    <a href="{{ route('admin.users.show', $participant->user) }}" class="text-decoration-none">
                                        <div class="small fw-semibold">{{ $participant->user->full_name ?: '@'.$participant->user->username }}</div>
                                        <div class="text-muted extra-small">{{ '@'.$participant->user->username }}</div>
                                    </a>
                                @else
                                    <span class="text-muted small">Missing user</span>
                                @endif
                            </td>
                            <td>
                                <div class="small fw-semibold">{{ $participant->contest?->title ?: 'Missing contest' }}</div>
                                <div class="text-muted extra-small">{{ str($participant->contest?->status ?: 'unknown')->title() }}</div>
                            </td>
                            <td><span class="badge bg-info-soft text-info rounded-pill">{{ str($participant->participant_role)->replace('_', ' ')->title() }}</span></td>
                            <td><span class="badge {{ $participant->status === 'approved' ? 'bg-success-soft text-success' : ($participant->status === 'banned' ? 'bg-danger-soft text-danger' : 'bg-warning-soft text-warning') }} rounded-pill">{{ str($participant->status)->title() }}</span></td>
                            <td><span class="text-muted small">{{ $participant->total_score !== null ? number_format((float) $participant->total_score, 2) : '-' }}</span></td>
                            <td><span class="text-muted small">{{ $participant->joined_at?->format('M d, Y H:i') }}</span></td>
                            <td><span class="text-muted small">{{ $participant->approved_at?->format('M d, Y H:i') ?? '-' }}</span></td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-dynamic rounded-3 px-3" type="button" data-bs-toggle="modal" data-bs-target="#{{ $reviewModalId }}">
                                    <i class="bi bi-person-gear me-1"></i>Review
                                </button>

                                <div class="modal fade" id="{{ $reviewModalId }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                        <div class="modal-content bg-dark border border-white-10">
                                            <div class="modal-header border-white-10">
                                                <h5 class="modal-title">Participant Review #{{ $participant->id }}</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row g-3 mb-4">
                                                    <div class="col-md-6"><div class="small text-muted">Contest</div><div class="fw-semibold">{{ $participant->contest?->title ?: 'Missing contest' }}</div></div>
                                                    <div class="col-md-6"><div class="small text-muted">Participant</div><div class="fw-semibold">{{ $participant->user?->full_name ?: ($participant->user?->username ? '@'.$participant->user->username : 'Removed account') }}</div></div>
                                                    <div class="col-md-4"><div class="small text-muted">Role</div><div>{{ str($participant->participant_role)->replace('_', ' ')->title() }}</div></div>
                                                    <div class="col-md-4"><div class="small text-muted">Score</div><div>{{ $participant->total_score !== null ? number_format((float) $participant->total_score, 2) : 'Not scored' }}</div></div>
                                                    <div class="col-md-4"><div class="small text-muted">Rank</div><div>{{ $participant->rank_position ?? 'N/A' }}</div></div>
                                                    <div class="col-md-6"><div class="small text-muted">Joined</div><div>{{ $participant->joined_at?->format('M d, Y H:i') ?? '-' }}</div></div>
                                                    <div class="col-md-6"><div class="small text-muted">Approved</div><div>{{ $participant->approved_at?->format('M d, Y H:i') ?? 'Not approved yet' }}</div></div>
                                                </div>
                                                <form method="POST" action="{{ route('admin.contests.participants.update', $participant) }}" class="d-grid gap-3">
                                                    @csrf
                                                    @method('PATCH')
                                                    <div>
                                                        <label class="form-label small text-muted">Update Status</label>
                                                        <select name="status" class="form-select border-0 bg-dark-soft rounded-3 text-muted">
                                                            @foreach (['joined' => 'Joined', 'approved' => 'Approved', 'rejected' => 'Rejected', 'withdrawn' => 'Withdrawn', 'banned' => 'Banned'] as $value => $label)
                                                                <option value="{{ $value }}" @selected($participant->status === $value)>{{ $label }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="d-flex justify-content-end gap-2">
                                                        <button type="button" class="btn btn-outline-light rounded-3 px-3" data-bs-dismiss="modal">Close</button>
                                                        <button type="submit" class="btn btn-primary rounded-3 px-3"><i class="bi bi-check2-circle me-1"></i>Save Status</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center py-5 text-muted">No participants found for the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 bg-white-05 border-top border-white-05 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="text-muted small">
                Showing <span class="text-emphasis-dynamic fw-bold">{{ $participants->firstItem() ?? 0 }}-{{ $participants->lastItem() ?? 0 }}</span>
                of <span class="text-emphasis-dynamic fw-bold">{{ number_format($participants->total()) }}</span> participants
            </div>
            {{ $participants->links() }}
        </div>
    </div>
</div>
@endsection
