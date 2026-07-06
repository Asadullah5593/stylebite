@extends('admin.layouts.app')

@section('content')
<div class="contests-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold">Contests</span>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">Submissions</span>
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
        <div class="col-md-3 col-sm-6"><div class="glass rounded-4 p-3 h-100"><div class="text-muted small">Submissions</div><div class="fs-4 fw-bold">{{ number_format($submissionStats['total'] ?? 0) }}</div></div></div>
        <div class="col-md-3 col-sm-6"><div class="glass rounded-4 p-3 h-100"><div class="text-muted small">Approved</div><div class="fs-4 fw-bold">{{ number_format($submissionStats['approved'] ?? 0) }}</div></div></div>
        <div class="col-md-3 col-sm-6"><div class="glass rounded-4 p-3 h-100"><div class="text-muted small">Awaiting Review</div><div class="fs-4 fw-bold">{{ number_format($submissionStats['pending'] ?? 0) }}</div></div></div>
        <div class="col-md-3 col-sm-6"><div class="glass rounded-4 p-3 h-100"><div class="text-muted small">Disqualified</div><div class="fs-4 fw-bold">{{ number_format($submissionStats['disqualified'] ?? 0) }}</div></div></div>
    </div>

    <form method="GET" action="{{ route('admin.contests.submissions') }}" class="glass rounded-4 p-3 d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="position-relative flex-grow-1" style="min-width: 250px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control ps-5 border-0 bg-dark-soft rounded-3" placeholder="Search submissions, contest, user, team...">
        </div>

        <select name="submission_status" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Status</option>
            @foreach (['submitted' => 'Submitted', 'approved' => 'Approved', 'rejected' => 'Rejected', 'disqualified' => 'Disqualified'] as $value => $label)
                <option value="{{ $value }}" @selected(request('submission_status') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <button class="btn btn-outline-dynamic rounded-3 px-3" type="submit"><i class="bi bi-funnel me-2"></i>Filter</button>
        <a href="{{ route('admin.contests.submissions') }}" class="btn btn-outline-dynamic rounded-3 px-3"><i class="bi bi-arrow-clockwise me-2"></i>Reset</a>
    </form>

    <div class="glass rounded-4 overflow-hidden border-white-05">
        <div class="table-responsive scrollbar-hidden">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-white-05">
                    <tr>
                        <th class="ps-4 text-muted small fw-bold text-uppercase py-3">Submission</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Contest</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">User</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Team / Post</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Scores</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Status</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Submitted</th>
                        <th class="text-muted small fw-bold text-uppercase py-3 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($submissions as $submission)
                        @php $submissionModalId = 'submissionReview'.$submission->id; @endphp
                        <tr class="border-white-05">
                            <td class="ps-4">
                                <div class="fw-semibold small">#{{ $submission->id }}</div>
                                <div class="text-muted extra-small">Rank: {{ $submission->rank_position ?? '-' }}</div>
                            </td>
                            <td>
                                <div class="small fw-semibold">{{ $submission->contest?->title ?: 'Missing contest' }}</div>
                                <div class="text-muted extra-small">{{ str($submission->contest?->status ?: 'unknown')->title() }}</div>
                            </td>
                            <td>
                                @if ($submission->user)
                                    <a href="{{ route('admin.users.show', $submission->user) }}" class="text-decoration-none">
                                        <div class="small fw-semibold">{{ $submission->user->full_name ?: '@'.$submission->user->username }}</div>
                                        <div class="text-muted extra-small">{{ '@'.$submission->user->username }}</div>
                                    </a>
                                @else
                                    <span class="text-muted small">Missing user</span>
                                @endif
                            </td>
                            <td>
                                <div class="small">{{ $submission->team?->name ?: 'No team' }}</div>
                                <div class="text-muted extra-small">{{ str($submission->post?->caption ?: 'Missing post')->limit(42) }}</div>
                            </td>
                            <td><span class="text-muted small">J: {{ $submission->jury_score !== null ? number_format((float) $submission->jury_score, 2) : '-' }} · C: {{ $submission->community_score !== null ? number_format((float) $submission->community_score, 2) : '-' }} · F: {{ $submission->final_score !== null ? number_format((float) $submission->final_score, 2) : '-' }}</span></td>
                            <td><span class="badge {{ $submission->submission_status === 'approved' ? 'bg-success-soft text-success' : ($submission->submission_status === 'submitted' ? 'bg-info-soft text-info' : ($submission->submission_status === 'disqualified' ? 'bg-danger-soft text-danger' : 'bg-warning-soft text-warning')) }} rounded-pill">{{ str($submission->submission_status)->replace('_', ' ')->title() }}</span></td>
                            <td><span class="text-muted small">{{ $submission->submitted_at?->format('M d, Y H:i') }}</span></td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-dynamic rounded-3 px-3" type="button" data-bs-toggle="modal" data-bs-target="#{{ $submissionModalId }}">
                                    <i class="bi bi-card-checklist me-1"></i>Review
                                </button>

                                <div class="modal fade" id="{{ $submissionModalId }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                        <div class="modal-content bg-dark border border-white-10">
                                            <div class="modal-header border-white-10">
                                                <h5 class="modal-title">Submission Review #{{ $submission->id }}</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row g-3 mb-4">
                                                    <div class="col-md-6"><div class="small text-muted">Contest</div><div class="fw-semibold">{{ $submission->contest?->title ?: 'Missing contest' }}</div></div>
                                                    <div class="col-md-6"><div class="small text-muted">Entrant</div><div class="fw-semibold">{{ $submission->user?->full_name ?: ($submission->user?->username ? '@'.$submission->user->username : 'Removed account') }}</div></div>
                                                    <div class="col-md-4"><div class="small text-muted">Team</div><div>{{ $submission->team?->name ?: 'No team' }}</div></div>
                                                    <div class="col-md-4"><div class="small text-muted">Rank</div><div>{{ $submission->rank_position ?? 'N/A' }}</div></div>
                                                    <div class="col-md-4"><div class="small text-muted">Reviewed</div><div>{{ $submission->reviewed_at?->format('M d, Y H:i') ?? 'Not reviewed yet' }}</div></div>
                                                    <div class="col-md-4"><div class="small text-muted">Jury Score</div><div>{{ $submission->jury_score !== null ? number_format((float) $submission->jury_score, 2) : '-' }}</div></div>
                                                    <div class="col-md-4"><div class="small text-muted">Community Score</div><div>{{ $submission->community_score !== null ? number_format((float) $submission->community_score, 2) : '-' }}</div></div>
                                                    <div class="col-md-4"><div class="small text-muted">Final Score</div><div>{{ $submission->final_score !== null ? number_format((float) $submission->final_score, 2) : '-' }}</div></div>
                                                    <div class="col-12"><div class="small text-muted">Post Caption</div><div>{{ $submission->post?->caption ?: 'No linked post caption available.' }}</div></div>
                                                </div>
                                                <form method="POST" action="{{ route('admin.contests.submissions.update', $submission) }}" class="d-grid gap-3">
                                                    @csrf
                                                    @method('PATCH')
                                                    <div>
                                                        <label class="form-label small text-muted">Update Submission Status</label>
                                                        <select name="submission_status" class="form-select border-0 bg-dark-soft rounded-3 text-muted">
                                                            @foreach (['submitted' => 'Submitted', 'approved' => 'Approved', 'rejected' => 'Rejected', 'disqualified' => 'Disqualified'] as $value => $label)
                                                                <option value="{{ $value }}" @selected($submission->submission_status === $value)>{{ $label }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="d-flex justify-content-end gap-2">
                                                        <button type="button" class="btn btn-outline-light rounded-3 px-3" data-bs-dismiss="modal">Close</button>
                                                        <button type="submit" class="btn btn-primary rounded-3 px-3"><i class="bi bi-shield-check me-1"></i>Save Review</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center py-5 text-muted">No submissions found for the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 bg-white-05 border-top border-white-05 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="text-muted small">
                Showing <span class="text-emphasis-dynamic fw-bold">{{ $submissions->firstItem() ?? 0 }}-{{ $submissions->lastItem() ?? 0 }}</span>
                of <span class="text-emphasis-dynamic fw-bold">{{ number_format($submissions->total()) }}</span> submissions
            </div>
            {{ $submissions->links() }}
        </div>
    </div>
</div>
@endsection
