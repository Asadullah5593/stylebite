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
                        @endphp
                        <tr>
                            <td class="ps-4 py-3">
                                <div class="d-flex align-items-center gap-3">
                                    @if ($avatarUrl)
                                        <img src="{{ $avatarUrl }}" alt="{{ $name }}" class="rounded-circle" width="40" height="40" style="object-fit: cover;">
                                    @else
                                        <div class="avatar-fallback" style="width:40px;height:40px;">{{ str($name)->substr(0, 1)->upper() }}</div>
                                    @endif
                                    <div>
                                        <div class="fw-bold">{{ $name }}</div>
                                        <div class="small text-muted">
                                            @if ($user)
                                                <a href="{{ route('admin.users.show', $user) }}" class="text-decoration-none">{{ '@'.$user->username }}</a>
                                                · {{ $user->email }}
                                            @else
                                                Profile #{{ $profile->id }}
                                            @endif
                                        </div>
                                    </div>
                                </div>
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
                            <td colspan="6" class="text-center text-muted py-5">No verification requests found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $requests->links() }}
    </div>
</div>
@endsection
