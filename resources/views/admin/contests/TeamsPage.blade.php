@extends('admin.layouts.app')

@section('content')
<div class="contests-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold">Contests</span>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">Teams</span>
    </nav>

    <div class="mb-4">
        <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">Contests</h1>
        <p class="text-muted small mb-0">Contest teams with quick member and rank context</p>
    </div>

    @include('admin.contests.partials.tabs')

    <form method="GET" action="{{ route('admin.contests.teams') }}" class="glass rounded-4 p-3 d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="position-relative flex-grow-1" style="min-width: 250px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control ps-5 border-0 bg-dark-soft rounded-3" placeholder="Search team, contest, city, member...">
        </div>

        <select name="contest_id" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto; min-width: 220px;">
            <option value="">All Contests</option>
            @foreach ($contestOptions as $contestOption)
                <option value="{{ $contestOption->id }}" @selected((string) request('contest_id') === (string) $contestOption->id)>{{ $contestOption->title }}</option>
            @endforeach
        </select>

        <button class="btn btn-outline-dynamic rounded-3 px-3" type="submit"><i class="bi bi-funnel me-2"></i>Filter</button>
        <a href="{{ route('admin.contests.teams') }}" class="btn btn-outline-dynamic rounded-3 px-3"><i class="bi bi-arrow-clockwise me-2"></i>Reset</a>
    </form>

    <div class="glass rounded-4 overflow-hidden border-white-05">
        <div class="table-responsive scrollbar-hidden">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-white-05">
                    <tr>
                        <th class="ps-4 text-muted small fw-bold text-uppercase py-3">Team</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Contest</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Members</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Score</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Rank</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Created</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($teams as $team)
                        <tr class="border-white-05">
                            <td class="ps-4">
                                <div class="d-flex align-items-center gap-3">
                                    @if ($team->logo_url)
                                        <img src="{{ $team->logo_url }}" alt="{{ $team->name }}" class="rounded-3 border border-white-05" width="48" height="48" style="object-fit: cover;">
                                    @else
                                        <div class="rounded-3 border border-white-05 d-flex align-items-center justify-content-center text-muted" style="width:48px;height:48px;">
                                            <i class="bi bi-people"></i>
                                        </div>
                                    @endif
                                    <div>
                                        <div class="fw-semibold small">{{ $team->name }}</div>
                                        <div class="text-muted extra-small">{{ $team->city ?: 'No city set' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="small fw-semibold">{{ $team->contest?->title ?: 'Missing contest' }}</div>
                                <div class="text-muted extra-small">{{ str($team->contest?->status ?: 'unknown')->title() }}</div>
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-2 align-items-center">
                                    <span class="badge bg-secondary-soft text-muted rounded-pill">{{ number_format($team->members_count) }} members</span>
                                    <span class="badge bg-info-soft text-info rounded-pill">{{ number_format($team->submissions_count) }} submissions</span>
                                </div>
                                @if ($team->members->isNotEmpty())
                                    <div class="d-flex flex-wrap gap-2 mt-2">
                                        @foreach ($team->members->take(3) as $member)
                                            @if ($member->user)
                                                <a href="{{ route('admin.users.show', $member->user) }}" class="text-decoration-none badge bg-white-05 text-muted rounded-pill px-2 py-1">
                                                    {{ $member->user->full_name ?: '@'.$member->user->username }}
                                                </a>
                                            @endif
                                        @endforeach
                                        @if ($team->members_count > 3)
                                            <a href="{{ route('admin.contests.team_members', ['contest_team_id' => $team->id]) }}" class="text-decoration-none badge bg-white-05 text-muted rounded-pill px-2 py-1">
                                                +{{ $team->members_count - 3 }} more
                                            </a>
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td><span class="text-muted small">{{ $team->score !== null ? number_format((float) $team->score, 2) : '-' }}</span></td>
                            <td>
                                @if ($team->rank_position)
                                    <span class="badge bg-warning-soft text-warning rounded-pill">#{{ $team->rank_position }}</span>
                                @else
                                    <span class="text-muted small">Unranked</span>
                                @endif
                            </td>
                            <td><span class="text-muted small">{{ $team->created_at?->format('M d, Y H:i') }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center py-5 text-muted">No teams found for the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 bg-white-05 border-top border-white-05 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="text-muted small">
                Showing <span class="text-emphasis-dynamic fw-bold">{{ $teams->firstItem() ?? 0 }}-{{ $teams->lastItem() ?? 0 }}</span>
                of <span class="text-emphasis-dynamic fw-bold">{{ number_format($teams->total()) }}</span> teams
            </div>
            {{ $teams->links() }}
        </div>
    </div>
</div>
@endsection
