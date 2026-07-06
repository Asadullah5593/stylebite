@extends('admin.layouts.app')

@section('content')
<div class="contests-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold">Contests</span>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">Team Members</span>
    </nav>

    <div class="mb-4">
        <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">Contests</h1>
        <p class="text-muted small mb-0">Team rosters with user links and joined history</p>
    </div>

    @include('admin.contests.partials.tabs')

    <form method="GET" action="{{ route('admin.contests.team_members') }}" class="glass rounded-4 p-3 d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="position-relative flex-grow-1" style="min-width: 250px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control ps-5 border-0 bg-dark-soft rounded-3" placeholder="Search user, team, contest, role...">
        </div>

        <select name="role" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Roles</option>
            @foreach (['captain' => 'Captain', 'member' => 'Member'] as $value => $label)
                <option value="{{ $value }}" @selected(request('role') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <select name="contest_team_id" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto; min-width: 220px;">
            <option value="">All Teams</option>
            @foreach ($teamOptions as $teamOption)
                <option value="{{ $teamOption->id }}" @selected((string) request('contest_team_id') === (string) $teamOption->id)>{{ $teamOption->name }}</option>
            @endforeach
        </select>

        <button class="btn btn-outline-dynamic rounded-3 px-3" type="submit"><i class="bi bi-funnel me-2"></i>Filter</button>
        <a href="{{ route('admin.contests.team_members') }}" class="btn btn-outline-dynamic rounded-3 px-3"><i class="bi bi-arrow-clockwise me-2"></i>Reset</a>
    </form>

    <div class="glass rounded-4 overflow-hidden border-white-05">
        <div class="table-responsive scrollbar-hidden">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-white-05">
                    <tr>
                        <th class="ps-4 text-muted small fw-bold text-uppercase py-3">User</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Team</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Contest</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Role</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Joined</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($teamMembers as $teamMember)
                        <tr class="border-white-05">
                            <td class="ps-4">
                                @if ($teamMember->user)
                                    <a href="{{ route('admin.users.show', $teamMember->user) }}" class="text-decoration-none d-flex align-items-center gap-3">
                                        @if ($teamMember->user->avatar_url)
                                            <img src="{{ $teamMember->user->avatar_url }}" alt="{{ $teamMember->user->username }}" class="rounded-circle border border-white-05" width="42" height="42" style="object-fit: cover;">
                                        @else
                                            <div class="rounded-circle border border-white-05 d-flex align-items-center justify-content-center text-muted" style="width:42px;height:42px;">
                                                <i class="bi bi-person"></i>
                                            </div>
                                        @endif
                                        <div>
                                            <div class="small fw-semibold">{{ $teamMember->user->full_name ?: '@'.$teamMember->user->username }}</div>
                                            <div class="text-muted extra-small">{{ '@'.$teamMember->user->username }}</div>
                                        </div>
                                    </a>
                                @else
                                    <span class="text-muted small">Missing user</span>
                                @endif
                            </td>
                            <td>
                                <div class="small fw-semibold">{{ $teamMember->team?->name ?: 'Missing team' }}</div>
                                <div class="text-muted extra-small">{{ $teamMember->team?->city ?: 'No city set' }}</div>
                            </td>
                            <td>
                                <div class="small fw-semibold">{{ $teamMember->team?->contest?->title ?: 'Missing contest' }}</div>
                                <div class="text-muted extra-small">
                                    @if ($teamMember->team?->rank_position)
                                        Rank #{{ $teamMember->team->rank_position }}
                                    @else
                                        Unranked team
                                    @endif
                                </div>
                            </td>
                            <td>
                                <span class="badge {{ $teamMember->role === 'captain' ? 'bg-primary-soft text-primary' : 'bg-secondary-soft text-muted' }} rounded-pill">
                                    {{ str($teamMember->role)->title() }}
                                </span>
                            </td>
                            <td><span class="text-muted small">{{ $teamMember->joined_at?->format('M d, Y H:i') }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center py-5 text-muted">No team members found for the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 bg-white-05 border-top border-white-05 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="text-muted small">
                Showing <span class="text-emphasis-dynamic fw-bold">{{ $teamMembers->firstItem() ?? 0 }}-{{ $teamMembers->lastItem() ?? 0 }}</span>
                of <span class="text-emphasis-dynamic fw-bold">{{ number_format($teamMembers->total()) }}</span> team members
            </div>
            {{ $teamMembers->links() }}
        </div>
    </div>
</div>
@endsection
