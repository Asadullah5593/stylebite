@php
    $tabs = [
        ['route' => 'admin.notifications.notifications', 'key' => 'notifications', 'label' => 'Notifications'],
        ['route' => 'admin.notifications.push_logs', 'key' => 'push_logs', 'label' => 'Push Logs'],
        ['route' => 'admin.notifications.saved_searches', 'key' => 'saved_searches', 'label' => 'Saved Searches'],
    ];
@endphp

<div class="mb-4">
    <div class="d-flex flex-wrap gap-1 p-1 rounded-4 glass border border-white-05">
        @foreach ($tabs as $tab)
            <a href="{{ route($tab['route']) }}" class="btn btn-sm rounded-3 px-2 py-2 fw-bold {{ request()->routeIs($tab['route']) ? 'bg-primary-gradient text-white shadow-glow' : 'text-muted hover-bg-white-10' }}">
                {{ $tab['label'] }}
                <span class="ms-1 opacity-50 small">{{ number_format($notificationTabCounts[$tab['key']] ?? 0) }}</span>
            </a>
        @endforeach
    </div>
</div>
