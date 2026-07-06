@extends('admin.layouts.app')

@section('content')
@php
    $name = $user->full_name ?: $user->username;
    $avatar = $user->avatar_url;
    $avatarUrl = $avatar ? (str_starts_with($avatar, 'http') || str_starts_with($avatar, '/') ? $avatar : asset($avatar)) : null;
    $statusLabel = $user->status === 'inactive' ? 'Suspended' : str($user->status)->title();
    $hasVerifiedBadge = $user->profileBadges->contains(fn ($badge) => $badge->badge_key === 'verified_user');
    $assignedBadgeKeys = $user->profileBadges->pluck('badge_key')->all();
@endphp

<div class="users-page">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <a href="{{ route('admin.users.all_users') }}" class="text-decoration-none text-reset fw-bold">Users</a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">{{ $name }}</span>
    </nav>

    @if (session('status'))
        <div class="glass rounded-4 p-3 mb-4 border border-primary-soft bg-primary-soft-opaque">
            <i class="bi bi-check-circle me-2 text-success"></i>{{ session('status') }}
        </div>
    @endif

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
            <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-outline-dynamic rounded-3">
                <i class="bi bi-pencil me-2"></i>Edit
            </a>
            @if ($user->trashed())
                <form method="POST" action="{{ route('admin.users.restore', $user) }}" id="restore-user-{{ $user->id }}">
                    @csrf
                    @method('PATCH')
                    <button class="btn btn-outline-success rounded-3" type="button" onclick="confirmAction('restore-user-{{ $user->id }}', 'Restore this user?', 'This will bring the account back into the admin list and reactivate access.')">
                        <i class="bi bi-arrow-counterclockwise me-2"></i>Restore
                    </button>
                </form>
            @else
                <form method="POST" action="{{ route('admin.users.badge.verified', $user) }}">
                    @csrf
                    @method('PATCH')
                    <button class="btn btn-outline-dynamic rounded-3 {{ $hasVerifiedBadge ? 'text-info' : '' }}" type="submit">
                        <i class="bi bi-patch-check me-2"></i>{{ $hasVerifiedBadge ? 'Remove Verified' : 'Add Verified' }}
                    </button>
                </form>
                <form method="POST" action="{{ route('admin.users.status', $user) }}" id="activate-user-{{ $user->id }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="action" value="activate">
                    <button class="btn btn-outline-success rounded-3" type="button" onclick="confirmAction('activate-user-{{ $user->id }}', 'Activate this user?', 'This will restore normal access immediately.')">
                        <i class="bi bi-check-circle me-2"></i>Activate
                    </button>
                </form>
                <form method="POST" action="{{ route('admin.users.status', $user) }}" id="suspend-user-{{ $user->id }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="action" value="suspend">
                    <button class="btn btn-outline-dynamic rounded-3 text-warning" type="button" onclick="confirmAction('suspend-user-{{ $user->id }}', 'Suspend this user?', 'This disables the account without permanently banning it.')">
                        <i class="bi bi-slash-circle me-2"></i>Suspend
                    </button>
                </form>
                <form method="POST" action="{{ route('admin.users.status', $user) }}" id="ban-user-{{ $user->id }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="action" value="ban">
                    <button class="btn btn-outline-danger rounded-3" type="button" onclick="confirmAction('ban-user-{{ $user->id }}', 'Ban this user?', 'This blocks access and marks the account as banned until an admin activates it again.')">
                        <i class="bi bi-shield-x me-2"></i>Ban
                    </button>
                </form>
                <form method="POST" action="{{ route('admin.users.destroy', $user) }}" id="delete-user-{{ $user->id }}">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-danger rounded-3" type="button" onclick="confirmAction('delete-user-{{ $user->id }}', 'Delete this user?', 'This will remove the user from the admin list. Please confirm before continuing.')">
                        <i class="bi bi-trash3 me-2"></i>Delete
                    </button>
                </form>
            @endif
        </div>
    </div>

    @include('admin.users.partials.tabs')

    <div class="row g-3 mb-4">
        @foreach ([
            ['label' => 'Status', 'value' => $statusLabel, 'icon' => 'bi-activity'],
            ['label' => 'Role', 'value' => str($user->role)->title(), 'icon' => 'bi-shield-check'],
            ['label' => 'Posts', 'value' => number_format($user->posts_count), 'icon' => 'bi-file-earmark-text'],
            ['label' => 'Followers', 'value' => number_format($user->followers_count), 'icon' => 'bi-people'],
            ['label' => 'Sessions', 'value' => number_format($user->sessions_count), 'icon' => 'bi-laptop'],
            ['label' => 'Devices', 'value' => number_format($user->device_tokens_count), 'icon' => 'bi-phone'],
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
                    <div><span class="text-muted d-block">Email Verified</span>{{ $user->email_verified_at?->format('M d, Y H:i') ?? 'Not verified' }}</div>
                    <div><span class="text-muted d-block">Phone</span>{{ trim(($user->phone_country_code ?? '').' '.($user->phone_number ?? '')) ?: 'Not provided' }}</div>
                    <div><span class="text-muted d-block">Locale / Timezone</span>{{ $user->locale }} · {{ $user->timezone }}</div>
                    <div><span class="text-muted d-block">Last Login</span>{{ $user->last_login_at?->diffForHumans() ?? 'Never' }}</div>
                    <div><span class="text-muted d-block">Last Seen</span>{{ $user->last_seen_at?->diffForHumans() ?? 'Never' }}</div>
                    <div><span class="text-muted d-block">Created</span>{{ $user->created_at?->format('M d, Y H:i') }}</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-7">
            <div class="glass rounded-4 p-4 h-100 border border-white-05">
                <h3 class="h6 fw-bold mb-3">Profile</h3>
                @if ($user->profile)
                    <div class="row g-3 small">
                        <div class="col-md-6"><span class="text-muted d-block">Display Name</span>{{ $user->profile->display_name ?? 'Not set' }}</div>
                        <div class="col-md-6"><span class="text-muted d-block">Visibility</span>{{ str($user->profile->visibility)->replace('_', ' ')->title() }}</div>
                        <div class="col-md-6"><span class="text-muted d-block">City</span>{{ $user->profile->city ?? 'Not set' }}</div>
                        <div class="col-md-6"><span class="text-muted d-block">Country</span>{{ $user->profile->country ?? 'Not set' }}</div>
                        <div class="col-12"><span class="text-muted d-block">Bio</span>{{ $user->profile->bio ?? 'No bio yet.' }}</div>
                    </div>
                @else
                    <p class="text-muted mb-0">No profile record exists for this user yet.</p>
                @endif
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="glass rounded-4 p-4 h-100 border border-white-05">
                <h3 class="h6 fw-bold mb-3">Identity Controls</h3>
                <div class="d-grid gap-3 small">
                    <div class="d-flex justify-content-between align-items-center border-bottom border-white-05 pb-2">
                        <span>Verified Badge</span>
                        <span class="badge {{ $hasVerifiedBadge ? 'bg-info-soft text-info' : 'bg-secondary-soft text-muted' }} rounded-pill">{{ $hasVerifiedBadge ? 'Enabled' : 'Not set' }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center border-bottom border-white-05 pb-2">
                        <span>Total Badges</span>
                        <span class="badge bg-primary-soft text-primary rounded-pill">{{ number_format($user->profileBadges->count()) }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center border-bottom border-white-05 pb-2">
                        <span>Two Factor</span>
                        <span class="badge {{ $user->is_two_factor_enabled ? 'bg-success-soft text-success' : 'bg-secondary-soft text-muted' }} rounded-pill">{{ $user->is_two_factor_enabled ? 'Enabled' : 'Disabled' }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span>Activity Visibility</span>
                        <span class="badge {{ $user->settings?->show_activity_status ? 'bg-success-soft text-success' : 'bg-secondary-soft text-muted' }} rounded-pill">{{ $user->settings?->show_activity_status ? 'Visible' : 'Hidden' }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="glass rounded-4 p-4 h-100 border border-white-05">
                <h3 class="h6 fw-bold mb-3">Auth Providers</h3>
                @forelse ($user->authProviders as $provider)
                    <div class="d-flex justify-content-between py-2 border-bottom border-white-05 small">
                        <span class="fw-bold text-capitalize">{{ $provider->provider }}</span>
                        <span class="text-muted">{{ $provider->provider_email ?? $provider->provider_user_id }}</span>
                    </div>
                @empty
                    <p class="text-muted mb-0">No linked auth providers.</p>
                @endforelse
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="glass rounded-4 p-4 h-100 border border-white-05">
                <h3 class="h6 fw-bold mb-3">Recent Password Resets</h3>
                @forelse ($user->passwordResets as $reset)
                    <div class="d-flex justify-content-between align-items-center gap-2 py-2 border-bottom border-white-05 small">
                        <div>
                            <div class="fw-semibold">{{ $reset->created_at?->format('M d, Y H:i') }}</div>
                            <div class="text-muted extra-small">{{ $reset->used_at ? 'Already used' : 'Pending reset' }}</div>
                        </div>
                        @if (! $reset->used_at)
                            <form method="POST" action="{{ route('admin.users.password_resets.expire', [$user, $reset]) }}">
                                @csrf
                                @method('PATCH')
                                <button class="btn btn-sm btn-outline-warning rounded-3" type="submit">
                                    <i class="bi bi-hourglass-split me-1"></i>Expire
                                </button>
                            </form>
                        @endif
                    </div>
                @empty
                    <p class="text-muted mb-0">No password reset requests recorded.</p>
                @endforelse
            </div>
        </div>

        <div class="col-12 col-xl-6">
            <div class="glass rounded-4 p-4 h-100 border border-white-05">
                <h3 class="h6 fw-bold mb-3">Recent Sessions</h3>
                @forelse ($user->sessions->take(5) as $session)
                    <div class="d-flex justify-content-between align-items-center gap-3 py-2 border-bottom border-white-05 small">
                        <div>
                            <div>{{ $session->device_name ?? 'Unknown device' }} · {{ strtoupper($session->platform) }}</div>
                            <div class="text-muted extra-small">{{ $session->ip_address ?: 'No IP' }} · {{ $session->last_seen_at?->diffForHumans() ?? 'Never' }}</div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge {{ $session->revoked_at ? 'bg-secondary-soft text-muted' : 'bg-success-soft text-success' }} rounded-pill">{{ $session->revoked_at ? 'Revoked' : 'Active' }}</span>
                            @if (! $session->revoked_at)
                                <form method="POST" action="{{ route('admin.users.sessions.revoke', [$user, $session]) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button class="btn btn-sm btn-outline-warning rounded-3" type="submit">
                                        <i class="bi bi-shield-x me-1"></i>Revoke
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="text-muted mb-0">No sessions recorded.</p>
                @endforelse
            </div>
        </div>

        <div class="col-12 col-xl-6">
            <div class="glass rounded-4 p-4 h-100 border border-white-05">
                <h3 class="h6 fw-bold mb-3">Recent Devices</h3>
                @forelse ($user->deviceTokens->take(5) as $device)
                    <div class="d-flex justify-content-between align-items-center gap-3 py-2 border-bottom border-white-05 small">
                        <div>
                            <div>{{ strtoupper($device->platform) }} · {{ $device->app_version ?: 'No app version' }}</div>
                            <div class="text-muted extra-small">{{ $device->device_id }} · {{ $device->last_used_at?->diffForHumans() ?? 'Never used' }}</div>
                        </div>
                        <form method="POST" action="{{ route('admin.users.devices.toggle', [$user, $device]) }}">
                            @csrf
                            @method('PATCH')
                            <button class="btn btn-sm {{ $device->is_active ? 'btn-outline-warning' : 'btn-outline-success' }} rounded-3" type="submit">
                                <i class="bi {{ $device->is_active ? 'bi-phone-vibrate' : 'bi-phone' }} me-1"></i>{{ $device->is_active ? 'Disable' : 'Enable' }}
                            </button>
                        </form>
                    </div>
                @empty
                    <p class="text-muted mb-0">No device tokens recorded.</p>
                @endforelse
            </div>
        </div>

        <div class="col-12">
            <div class="glass rounded-4 p-4 border border-white-05">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-3">
                    <div>
                        <h3 class="h6 fw-bold mb-1">Badge Manager</h3>
                        <p class="text-muted small mb-0">Assign or remove common profile badges without leaving the user detail page.</p>
                    </div>
                </div>
                <div class="row g-3">
                    @foreach ($badgeCatalog as $badgeKey => $badge)
                        @php $isAssigned = in_array($badgeKey, $assignedBadgeKeys, true); @endphp
                        <div class="col-md-6 col-xl-4">
                            <div class="glass rounded-4 p-3 h-100 border border-white-05">
                                <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                                    <div>
                                        <div class="fw-semibold">{{ $badge['title'] }}</div>
                                        <div class="text-muted extra-small font-monospace">{{ $badgeKey }}</div>
                                    </div>
                                    <span class="badge {{ $isAssigned ? 'bg-success-soft text-success' : 'bg-secondary-soft text-muted' }} rounded-pill">
                                        {{ $isAssigned ? 'Assigned' : 'Available' }}
                                    </span>
                                </div>
                                <form method="POST" action="{{ route('admin.users.badges.update', $user) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="badge_key" value="{{ $badgeKey }}">
                                    <input type="hidden" name="action" value="{{ $isAssigned ? 'remove' : 'attach' }}">
                                    <button type="submit" class="btn btn-sm {{ $isAssigned ? 'btn-outline-danger' : 'btn-outline-dynamic' }} rounded-3 px-3">
                                        <i class="bi {{ $isAssigned ? 'bi-trash3' : 'bi-plus-lg' }} me-1"></i>{{ $isAssigned ? 'Remove Badge' : 'Assign Badge' }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade confirm-modal" id="confirmUserActionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-white-05">
                <h5 class="modal-title" id="confirmUserActionTitle">Confirm action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-muted" id="confirmUserActionText"></div>
            <div class="modal-footer border-white-05">
                <button type="button" class="btn btn-outline-dynamic rounded-3" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn bg-primary-gradient text-white rounded-3 border-0" id="confirmUserActionButton">Confirm</button>
            </div>
        </div>
    </div>
</div>

@include('admin.users.partials.theme')

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modalElement = document.getElementById('confirmUserActionModal');
    const titleElement = document.getElementById('confirmUserActionTitle');
    const textElement = document.getElementById('confirmUserActionText');
    const confirmButton = document.getElementById('confirmUserActionButton');
    const confirmModal = new bootstrap.Modal(modalElement);
    let pendingFormId = null;

    window.confirmAction = function(formId, title, text) {
        pendingFormId = formId;
        titleElement.textContent = title;
        textElement.textContent = text;
        confirmModal.show();
    };

    confirmButton.addEventListener('click', function() {
        if (pendingFormId) {
            document.getElementById(pendingFormId).submit();
        }
    });
});
</script>
@endsection
