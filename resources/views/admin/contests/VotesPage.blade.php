@extends('admin.layouts.app')

@section('content')
<div class="contests-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold">Contests</span>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">Votes</span>
    </nav>

    <div class="mb-4">
        <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">Contests</h1>
        <p class="text-muted small mb-0">Contests, teams, submissions and votes</p>
    </div>

    @include('admin.contests.partials.tabs')

    <form method="GET" action="{{ route('admin.contests.votes') }}" class="glass rounded-4 p-3 d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="position-relative flex-grow-1" style="min-width: 250px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control ps-5 border-0 bg-dark-soft rounded-3" placeholder="Search contest, voter, score...">
        </div>

        <select name="vote_type" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Vote Types</option>
            @foreach (['community' => 'Community', 'jury' => 'Jury'] as $value => $label)
                <option value="{{ $value }}" @selected(request('vote_type') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <button class="btn btn-outline-dynamic rounded-3 px-3" type="submit"><i class="bi bi-funnel me-2"></i>Filter</button>
        <a href="{{ route('admin.contests.votes') }}" class="btn btn-outline-dynamic rounded-3 px-3"><i class="bi bi-arrow-clockwise me-2"></i>Reset</a>
    </form>

    <div class="glass rounded-4 overflow-hidden border-white-05">
        <div class="table-responsive scrollbar-hidden">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-white-05">
                    <tr>
                        <th class="ps-4 text-muted small fw-bold text-uppercase py-3">Vote</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Contest</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Submission</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Voter</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Type</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Score</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Created</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($votes as $vote)
                        <tr class="border-white-05">
                            <td class="ps-4">
                                <div class="fw-semibold small">#{{ $vote->id }}</div>
                                <div class="text-muted extra-small">Submission #{{ $vote->submission_id }}</div>
                            </td>
                            <td>
                                <div class="small fw-semibold">{{ $vote->contest?->title ?: 'Missing contest' }}</div>
                                <div class="text-muted extra-small">{{ str($vote->contest?->status ?: 'unknown')->title() }}</div>
                            </td>
                            <td>
                                <div class="small">{{ $vote->submission?->user?->full_name ?: '@'.$vote->submission?->user?->username }}</div>
                                <div class="text-muted extra-small">{{ str($vote->submission?->submission_status ?: 'missing')->title() }}</div>
                            </td>
                            <td>
                                @if ($vote->voter)
                                    <a href="{{ route('admin.users.show', $vote->voter) }}" class="text-decoration-none">
                                        <div class="small fw-semibold">{{ $vote->voter->full_name ?: '@'.$vote->voter->username }}</div>
                                        <div class="text-muted extra-small">{{ '@'.$vote->voter->username }}</div>
                                    </a>
                                @else
                                    <span class="text-muted small">Missing voter</span>
                                @endif
                            </td>
                            <td><span class="badge {{ $vote->vote_type === 'jury' ? 'bg-primary-soft text-primary' : 'bg-info-soft text-info' }} rounded-pill">{{ str($vote->vote_type)->title() }}</span></td>
                            <td><span class="text-muted small">{{ number_format((float) $vote->score, 2) }}</span></td>
                            <td><span class="text-muted small">{{ $vote->created_at?->format('M d, Y H:i') }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center py-5 text-muted">No votes found for the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 bg-white-05 border-top border-white-05 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="text-muted small">
                Showing <span class="text-emphasis-dynamic fw-bold">{{ $votes->firstItem() ?? 0 }}-{{ $votes->lastItem() ?? 0 }}</span>
                of <span class="text-emphasis-dynamic fw-bold">{{ number_format($votes->total()) }}</span> votes
            </div>
            {{ $votes->links() }}
        </div>
    </div>
</div>
@endsection
