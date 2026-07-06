@php
    $healthTabs = [
        ['route' => 'admin.settings.jobs', 'label' => 'Queue Jobs', 'count' => $settingsTabCounts['jobs'] ?? 0],
        ['route' => 'admin.settings.failed_jobs', 'label' => 'Failed Jobs', 'count' => $settingsTabCounts['failed_jobs'] ?? 0],
        ['route' => 'admin.settings.job_batches', 'label' => 'Job Batches', 'count' => $settingsTabCounts['job_batches'] ?? 0],
        ['route' => 'admin.settings.cache', 'label' => 'Cache', 'count' => $settingsTabCounts['cache'] ?? 0],
        ['route' => 'admin.settings.cache_locks', 'label' => 'Cache Locks', 'count' => $settingsTabCounts['cache_locks'] ?? 0],
        ['route' => 'admin.settings.migrations', 'label' => 'Migrations', 'count' => $settingsTabCounts['migrations'] ?? 0],
    ];
@endphp

<div class="d-flex flex-wrap gap-2 mb-4">
    @foreach ($healthTabs as $tab)
        <a href="{{ route($tab['route']) }}" class="btn {{ request()->routeIs($tab['route']) ? 'btn-primary' : 'btn-outline-dynamic' }} rounded-3 px-3 py-2">
            <span>{{ $tab['label'] }}</span>
            <span class="badge rounded-pill ms-2 {{ request()->routeIs($tab['route']) ? 'bg-white text-dark' : 'bg-white-10 text-white' }}">
                {{ number_format($tab['count']) }}
            </span>
        </a>
    @endforeach
</div>
