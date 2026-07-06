@php
    $tabs = [
        ['route' => 'admin.contests.contests', 'key' => 'contests', 'label' => 'Contests'],
        ['route' => 'admin.contests.contest_rules', 'key' => 'contest_rules', 'label' => 'Rules'],
        ['route' => 'admin.contests.participants', 'key' => 'participants', 'label' => 'Participants'],
        ['route' => 'admin.contests.invitations', 'key' => 'invitations', 'label' => 'Invitations'],
        ['route' => 'admin.contests.teams', 'key' => 'teams', 'label' => 'Teams'],
        ['route' => 'admin.contests.team_members', 'key' => 'team_members', 'label' => 'Team Members'],
        ['route' => 'admin.contests.submissions', 'key' => 'submissions', 'label' => 'Submissions'],
        ['route' => 'admin.contests.votes', 'key' => 'votes', 'label' => 'Votes'],
        ['route' => 'admin.contests.leaderboards', 'key' => 'leaderboards', 'label' => 'Leaderboards'],
    ];
@endphp

<div class="mb-4">
    <div class="d-flex flex-wrap gap-1 p-1 rounded-4 glass border border-white-05">
        @foreach ($tabs as $tab)
            <a href="{{ route($tab['route']) }}" class="btn btn-sm rounded-3 px-2 py-2 fw-bold {{ request()->routeIs($tab['route']) ? 'bg-primary-gradient text-white shadow-glow' : 'text-muted hover-bg-white-10' }}">
                {{ $tab['label'] }}
                <span class="ms-1 opacity-50 small">{{ number_format($contestTabCounts[$tab['key']] ?? 0) }}</span>
            </a>
        @endforeach
    </div>
</div>
