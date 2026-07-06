@extends('admin.layouts.app')

@section('content')
@php
    $name = $user->full_name ?: $user->username;
    $avatar = $user->avatar_url;
    $avatarUrl = $avatar ? (str_starts_with($avatar, 'http') || str_starts_with($avatar, '/') ? $avatar : asset($avatar)) : null;
@endphp
<div class="users-page">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold">Account</span>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">My Profile</span>
    </nav>

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <div class="d-flex align-items-center gap-3">
            @if ($avatarUrl)
                <img src="{{ $avatarUrl }}" alt="{{ $name }}" class="rounded-circle border border-2 border-primary-soft shadow-sm" width="72" height="72" style="object-fit: cover;">
            @else
                <div class="avatar-fallback border border-2 border-primary-soft shadow-sm" style="width:72px;height:72px;font-size:1.5rem;">{{ str($name)->substr(0, 1)->upper() }}</div>
            @endif
            <div>
                <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">{{ $name }}</h1>
                <p class="text-muted small mb-0">{{ '@'.$user->username }} · {{ $user->email }}</p>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.account.settings') }}" class="btn btn-outline-dynamic rounded-3">
                <i class="bi bi-sliders me-2"></i>Edit Account
            </a>
            <a href="{{ route('admin.settings.configs') }}" class="btn btn-outline-dynamic rounded-3">
                <i class="bi bi-gear me-2"></i>App Settings
            </a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        @foreach ([
            ['label' => 'Role', 'value' => str($user->role)->title(), 'icon' => 'bi-shield-check'],
            ['label' => 'Status', 'value' => str($user->status)->title(), 'icon' => 'bi-activity'],
            ['label' => 'Posts', 'value' => number_format($user->posts_count), 'icon' => 'bi-file-earmark-text'],
            ['label' => 'Memories', 'value' => number_format($user->memories_count), 'icon' => 'bi-journal-richtext'],
            ['label' => 'Followers', 'value' => number_format($user->followers_count), 'icon' => 'bi-people'],
            ['label' => 'Following', 'value' => number_format($user->following_count), 'icon' => 'bi-person-plus'],
        ] as $tile)
            <div class="col-6 col-lg-2">
                <div class="glass rounded-4 p-3 detail-tile border border-white-05">
                    <i class="bi {{ $tile['icon'] }} text-primary mb-2 d-block"></i>
                    <div class="text-muted extra-small text-uppercase fw-bold">{{ $tile['label'] }}</div>
                    <div class="fw-bold text-emphasis-dynamic">{{ $tile['value'] }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-4">
        <div class="col-12 col-xl-5">
            <div class="glass rounded-4 p-4 h-100 border border-white-05">
                <h3 class="h6 fw-bold mb-3">Account Details</h3>
                <div class="d-grid gap-3 small">
                    <div><span class="text-muted d-block">Full Name</span>{{ $user->full_name ?: 'Not set' }}</div>
                    <div><span class="text-muted d-block">Email</span>{{ $user->email }}</div>
                    <div><span class="text-muted d-block">Locale / Timezone</span>{{ $user->locale ?: 'en' }} · {{ $user->timezone ?: 'UTC' }}</div>
                    <div><span class="text-muted d-block">Last Login</span>{{ $user->last_login_at?->format('M d, Y H:i') ?? 'Never' }}</div>
                    <div><span class="text-muted d-block">Last Seen</span>{{ $user->last_seen_at?->format('M d, Y H:i') ?? 'Never' }}</div>
                    <div><span class="text-muted d-block">Created</span>{{ $user->created_at?->format('M d, Y H:i') }}</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-7">
            <div class="glass rounded-4 p-4 h-100 border border-white-05">
                <h3 class="h6 fw-bold mb-3">Profile Details</h3>
                <div class="row g-3 small">
                    <div class="col-md-6"><span class="text-muted d-block">Display Name</span>{{ $user->profile?->display_name ?: 'Not set' }}</div>
                    <div class="col-md-6"><span class="text-muted d-block">Headline</span>{{ $user->profile?->headline ?: 'Not set' }}</div>
                    <div class="col-md-6"><span class="text-muted d-block">City</span>{{ $user->profile?->city ?: 'Not set' }}</div>
                    <div class="col-md-6"><span class="text-muted d-block">Country</span>{{ $user->profile?->country ?: 'Not set' }}</div>
                    <div class="col-md-6"><span class="text-muted d-block">Website</span>{{ $user->profile?->website_url ?: 'Not set' }}</div>
                    <div class="col-md-6"><span class="text-muted d-block">Badges</span>{{ number_format($user->profileBadges->count()) }}</div>
                    <div class="col-12"><span class="text-muted d-block">Bio</span>{{ $user->profile?->bio ?: 'No bio added yet.' }}</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
