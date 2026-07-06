@php
    $tabs = [
        ['route' => 'admin.users.all_users', 'key' => 'all_users', 'label' => 'All Users'],
        ['route' => 'admin.users.profiles', 'key' => 'profiles', 'label' => 'Profiles'],
        ['route' => 'admin.users.settings', 'key' => 'settings', 'label' => 'Settings'],
        ['route' => 'admin.users.auth_providers', 'key' => 'auth_providers', 'label' => 'Auth Providers'],
        ['route' => 'admin.users.sessions', 'key' => 'sessions', 'label' => 'Sessions', 'class' => 'd-none d-lg-block'],
        ['route' => 'admin.users.devices', 'key' => 'devices', 'label' => 'Devices', 'class' => 'd-none d-xl-block'],
        ['route' => 'admin.users.password_resets', 'key' => 'password_resets', 'label' => 'Password Resets', 'class' => 'd-none d-xl-block'],
    ];
@endphp

<div class="mb-4">
    <div class="d-flex flex-wrap gap-1 p-1 rounded-4 glass border border-white-05">
        @foreach ($tabs as $tab)
            <a href="{{ route($tab['route']) }}" class="btn btn-sm rounded-3 px-2 py-2 fw-bold {{ request()->routeIs($tab['route']) ? 'bg-primary-gradient text-white shadow-glow' : 'text-muted hover-bg-white-10' }} {{ $tab['class'] ?? '' }}">
                {{ $tab['label'] }}
                <span class="ms-1 opacity-50 small">{{ number_format($userTabCounts[$tab['key']] ?? 0) }}</span>
            </a>
        @endforeach
    </div>
</div>
