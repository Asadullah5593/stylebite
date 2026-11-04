@extends('admin.layouts.app')

@section('content')
<div class="users-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold">Users</span>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">Verification</span>
    </nav>

    <div class="mb-4">
        <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">Verification Requests</h1>
        <p class="text-muted small mb-0">Users request a badge in the app; approve here to grant the verified check.</p>
    </div>

    @include('admin.users.partials.tabs')

    @if (session('status'))
        <div class="glass rounded-4 p-3 mb-4 border border-primary-soft bg-primary-soft-opaque">
            <i class="bi bi-check-circle me-2 text-success"></i>{{ session('status') }}
        </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <div class="glass rounded-4 p-3 h-100">
                <div class="text-muted small">Total Requests</div>
                <div class="fs-5 fw-bold">{{ number_format($verificationStats['total'] ?? 0) }}</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="glass rounded-4 p-3 h-100">
                <div class="text-muted small">Pending</div>
                <div class="fs-5 fw-bold">{{ number_format($verificationStats['pending'] ?? 0) }}</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="glass rounded-4 p-3 h-100">
                <div class="text-muted small">Approved</div>
                <div class="fs-5 fw-bold">{{ number_format($verificationStats['approved'] ?? 0) }}</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="glass rounded-4 p-3 h-100">
                <div class="text-muted small">Rejected</div>
                <div class="fs-5 fw-bold">{{ number_format($verificationStats['rejected'] ?? 0) }}</div>
            </div>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.users.verification_requests') }}" class="glass rounded-4 p-3 d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="position-relative flex-grow-1" style="min-width: 250px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control ps-5 border-0 bg-dark-soft rounded-3" placeholder="Search username, email, name...">
        </div>

        <select name="status" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'] as $value => $label)
                <option value="{{ $value }}" @selected(($statusFilter ?? 'pending') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <button class="btn btn-outline-dynamic rounded-3 px-3" type="submit"><i class="bi bi-funnel me-2"></i>Filter</button>
        <a href="{{ route('admin.users.verification_requests', ['status' => 'pending']) }}" class="btn btn-outline-dynamic rounded-3 px-3">Pending only</a>
        <a href="{{ route('admin.users.verification_requests') }}" class="btn btn-outline-dynamic rounded-3 px-3"><i class="bi bi-arrow-clockwise me-2"></i>Reset</a>
    </form>

    <div class="glass rounded-4 overflow-hidden border-white-05">
        <div class="table-responsive scrollbar-hidden">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-white-05">
                    <tr>
                        <th class="ps-4 text-muted small fw-bold text-uppercase py-3">User</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Vibe Count</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Contact Verified</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Requested</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Status</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Note</th>
                        <th class="text-muted small fw-bold text-uppercase py-3 text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($requests as $profile)
                        @php
                            $user = $profile->user;
                            $name = $user?->full_name ?: ($user?->username ?? 'Unknown');
                            $avatar = $user?->avatar_url;
                            $avatarUrl = $avatar ? (str_starts_with($avatar, 'http') || str_starts_with($avatar, '/') ? $avatar : asset($avatar)) : null;
                            $status = $profile->verification_status;
                            $vibeCount = (int) ($profile->vibe_count ?? $profile->follower_count ?? 0);
                            $vibeFlow = (int) ($profile->following_count ?? 0);
                            $modalId = 'verification-profile-'.$profile->id;
                        @endphp
                        <tr>
                            <td class="ps-4 py-3">
                                @if ($user)
                                    <button
                                        type="button"
                                        class="btn btn-link text-decoration-none text-start p-0 text-reset"
                                        data-bs-toggle="modal"
                                        data-bs-target="#{{ $modalId }}"
                                    >
                                        <div class="d-flex align-items-center gap-3">
                                            @if ($avatarUrl)
                                                <img src="{{ $avatarUrl }}" alt="{{ $name }}" class="rounded-circle" width="40" height="40" style="object-fit: cover;">
                                            @else
                                                <div class="avatar-fallback" style="width:40px;height:40px;">{{ str($name)->substr(0, 1)->upper() }}</div>
                                            @endif
                                            <div>
                                                <div class="fw-bold text-emphasis-dynamic">{{ $name }}</div>
                                                <div class="small text-muted">
                                                    {{ '@'.$user->username }} · {{ $user->email }}
                                                </div>
                                                <div class="extra-small text-primary mt-1">View profile insights</div>
                                            </div>
                                        </div>
                                    </button>
                                @else
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar-fallback" style="width:40px;height:40px;">?</div>
                                        <div>
                                            <div class="fw-bold">{{ $name }}</div>
                                            <div class="small text-muted">Profile #{{ $profile->id }}</div>
                                        </div>
                                    </div>
                                @endif
                            </td>
                            <td class="py-3">
                                <div class="fw-bold">{{ number_format($vibeCount) }}</div>
                                <div class="extra-small text-muted">Flow {{ number_format($vibeFlow) }}</div>
                            </td>
                            <td class="py-3">
                                <div class="small">
                                    Email: {{ $user?->email_verified_at ? 'Yes' : 'No' }}<br>
                                    Phone: {{ $user?->phone_verified_at ? 'Yes' : 'No' }}
                                </div>
                            </td>
                            <td class="py-3">
                                {{ $profile->verification_requested_at?->format('M d, Y H:i') ?? '—' }}
                            </td>
                            <td class="py-3">
                                @if ($status === 'pending')
                                    <span class="badge bg-warning-soft text-warning rounded-pill">Pending</span>
                                @elseif ($status === 'approved')
                                    <span class="badge bg-success-soft text-success rounded-pill">Approved</span>
                                @else
                                    <span class="badge bg-danger-soft text-danger rounded-pill">Rejected</span>
                                @endif
                            </td>
                            <td class="py-3">
                                <span class="small text-muted">{{ $profile->verification_admin_note ?: '—' }}</span>
                            </td>
                            <td class="py-3 text-end pe-4">
                                @if ($user && $status === 'pending')
                                    <div class="d-flex justify-content-end gap-2 flex-wrap">
                                        <form method="POST" action="{{ route('admin.users.verification.approve', $user) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button class="btn btn-sm btn-outline-success rounded-3" type="submit">
                                                <i class="bi bi-patch-check me-1"></i>Approve
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.users.verification.reject', $user) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button class="btn btn-sm btn-outline-danger rounded-3" type="submit">
                                                <i class="bi bi-x-circle me-1"></i>Reject
                                            </button>
                                        </form>
                                    </div>
                                @elseif ($user)
                                    <a href="{{ route('admin.users.show', $user) }}" class="btn btn-sm btn-outline-dynamic rounded-3">View user</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">No verification requests found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @foreach ($requests as $profile)
        @continue(! $profile->user)
        @php
            $user = $profile->user;
            $name = $user->full_name ?: ($user->username ?? 'Unknown');
            $avatar = $user->avatar_url;
            $avatarUrl = $avatar ? (str_starts_with($avatar, 'http') || str_starts_with($avatar, '/') ? $avatar : asset($avatar)) : null;
            $status = $profile->verification_status;
            $vibeCount = (int) ($profile->vibe_count ?? $profile->follower_count ?? 0);
            $vibeFlow = (int) ($profile->following_count ?? 0);
            $modalId = 'verification-profile-'.$profile->id;
        @endphp
        <div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}-label" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content glass border border-white-05 rounded-4 overflow-hidden">
                    <div class="modal-header border-white-05">
                        <h5 class="modal-title fw-bold" id="{{ $modalId }}-label">Profile insights</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="d-flex align-items-center gap-3 mb-4">
                            @if ($avatarUrl)
                                <img src="{{ $avatarUrl }}" alt="{{ $name }}" class="rounded-circle border border-2 border-primary-soft" width="64" height="64" style="object-fit: cover;">
                            @else
                                <div class="avatar-fallback border border-2 border-primary-soft" style="width:64px;height:64px;font-size:1.4rem;">{{ str($name)->substr(0, 1)->upper() }}</div>
                            @endif
                            <div>
                                <div class="fw-bold fs-5">{{ $name }}</div>
                                <div class="text-muted small">{{ '@'.$user->username }} · {{ $user->email }}</div>
                                <div class="text-muted extra-small mt-1">
                                    Joined {{ $user->created_at?->format('M d, Y') ?? '—' }}
                                    · Last seen {{ $user->last_seen_at?->diffForHumans() ?? 'never' }}
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            @foreach ([
                                ['label' => 'Vibe Count', 'value' => number_format($vibeCount), 'hint' => 'Followers'],
                                ['label' => 'Vibe Flow', 'value' => number_format($vibeFlow), 'hint' => 'Following'],
                                ['label' => 'Posts', 'value' => number_format((int) ($profile->post_count ?? 0)), 'hint' => 'Published'],
                                ['label' => 'Style Points', 'value' => number_format((int) ($profile->style_points ?? 0)), 'hint' => 'Score'],
                                ['label' => 'Contest Wins', 'value' => number_format((int) ($profile->contest_wins ?? 0)), 'hint' => 'Wins'],
                                ['label' => 'Contest Entries', 'value' => number_format((int) ($profile->contest_entries ?? 0)), 'hint' => 'Entered'],
                            ] as $stat)
                                <div class="col-6 col-md-4">
                                    <div class="rounded-4 p-3 border border-white-05 h-100 bg-white-05">
                                        <div class="extra-small text-muted text-uppercase fw-bold">{{ $stat['label'] }}</div>
                                        <div class="fs-5 fw-bold">{{ $stat['value'] }}</div>
                                        <div class="extra-small text-muted">{{ $stat['hint'] }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="row g-3 small">
                            <div class="col-md-6">
                                <span class="text-muted d-block">Display name</span>
                                {{ $profile->display_name ?: 'Not set' }}
                            </div>
                            <div class="col-md-6">
                                <span class="text-muted d-block">Headline</span>
                                {{ $profile->headline ?: 'Not set' }}
                            </div>
                            <div class="col-md-6">
                                <span class="text-muted d-block">City / Country</span>
                                {{ trim(($profile->city ?? '').(($profile->city && $profile->country) ? ', ' : '').($profile->country ?? '')) ?: 'Not set' }}
                            </div>
                            <div class="col-md-6">
                                <span class="text-muted d-block">Visibility</span>
                                {{ $profile->visibility ? str($profile->visibility)->replace('_', ' ')->title() : 'Not set' }}
                            </div>
                            <div class="col-md-6">
                                <span class="text-muted d-block">Email verified</span>
                                {{ $user->email_verified_at?->format('M d, Y H:i') ?? 'No' }}
                            </div>
                            <div class="col-md-6">
                                <span class="text-muted d-block">Phone verified</span>
                                {{ $user->phone_verified_at?->format('M d, Y H:i') ?? 'No' }}
                            </div>
                            <div class="col-12">
                                <span class="text-muted d-block">Bio</span>
                                {{ $profile->bio ?: 'No bio yet.' }}
                            </div>
                            @if ($profile->verification_admin_note)
                                <div class="col-12">
                                    <span class="text-muted d-block">Admin note</span>
                                    {{ $profile->verification_admin_note }}
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="modal-footer border-white-05 d-flex flex-wrap gap-2 justify-content-between">
                        <a href="{{ route('admin.users.show', $user) }}" class="btn btn-outline-dynamic rounded-3">
                            Open full user page
                        </a>
                        <div class="d-flex gap-2 flex-wrap">
                            @if ($status === 'pending')
                                <form method="POST" action="{{ route('admin.users.verification.approve', $user) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button class="btn btn-outline-success rounded-3" type="submit">
                                        <i class="bi bi-patch-check me-1"></i>Approve
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.users.verification.reject', $user) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button class="btn btn-outline-danger rounded-3" type="submit">
                                        <i class="bi bi-x-circle me-1"></i>Reject
                                    </button>
                                </form>
                            @endif
                            <button type="button" class="btn btn-outline-dynamic rounded-3" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    <div class="mt-3">
        {{ $requests->links() }}
    </div>
</div>
@endsection
