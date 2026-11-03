@php
    $settingsTabs = [
        ['route' => 'admin.settings.configs', 'label' => 'App Configs', 'count' => $settingsTabCounts['configs'] ?? 0, 'match' => 'admin.settings.configs'],
        ['route' => 'admin.system_health', 'label' => 'System Health', 'count' => null, 'match' => 'admin.system_health'],
        ['route' => 'admin.settings.jobs', 'label' => 'Queue Jobs', 'count' => $settingsTabCounts['jobs'] ?? 0, 'match' => 'admin.settings.jobs'],
        ['route' => 'admin.settings.failed_jobs', 'label' => 'Failed Jobs', 'count' => $settingsTabCounts['failed_jobs'] ?? 0, 'match' => 'admin.settings.failed_jobs'],
        ['route' => 'admin.settings.job_batches', 'label' => 'Job Batches', 'count' => $settingsTabCounts['job_batches'] ?? 0, 'match' => 'admin.settings.job_batches'],
        ['route' => 'admin.settings.cache', 'label' => 'Cache', 'count' => $settingsTabCounts['cache'] ?? 0, 'match' => 'admin.settings.cache'],
        ['route' => 'admin.settings.cache_locks', 'label' => 'Cache Locks', 'count' => $settingsTabCounts['cache_locks'] ?? 0, 'match' => 'admin.settings.cache_locks'],
        ['route' => 'admin.settings.migrations', 'label' => 'Migrations', 'count' => $settingsTabCounts['migrations'] ?? 0, 'match' => 'admin.settings.migrations'],
    ];
@endphp

<div class="d-flex flex-wrap align-items-center gap-2 glass border border-white-05 rounded-4 p-2 mb-4">
    @foreach ($settingsTabs as $tab)
        @php $active = request()->routeIs($tab['match']); @endphp
        <a href="{{ route($tab['route']) }}" class="btn {{ $active ? 'btn-primary' : 'btn-outline-dynamic' }} rounded-3 px-3 py-2">
            <span>{{ $tab['label'] }}</span>
            @if ($tab['count'] !== null)
                <span class="badge rounded-pill ms-2 {{ $active ? 'bg-white text-dark' : 'bg-secondary-soft text-muted' }}">
                    {{ number_format($tab['count']) }}
                </span>
            @endif
        </a>
    @endforeach
</div>
