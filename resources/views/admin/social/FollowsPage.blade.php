@extends('admin.layouts.app')

@section('content')
<div class="social-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold">Social Graph</span>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">Follows</span>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">Social Graph</h1>
            <p class="text-muted small mb-0">Follow relationships between users, including pending and removed records.</p>
        </div>
    </div>

    @include('admin.social.partials.tabs')

    @if (session('status'))
        <div class="alert alert-success rounded-3 border-0 mb-4">{{ session('status') }}</div>
    @endif

    <form method="GET" action="{{ route('admin.social.follows') }}" class="glass rounded-4 p-3 d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="position-relative flex-grow-1" style="min-width: 250px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control ps-5 border-0 bg-dark-soft rounded-3" placeholder="Search by user, email, or follow ID...">
        </div>

        <select name="status" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Statuses</option>
            @foreach (['accepted' => 'Accepted', 'pending' => 'Pending', 'rejected' => 'Rejected', 'blocked' => 'Blocked'] as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <select name="record_state" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Record States</option>
            <option value="active" @selected(request('record_state') === 'active')>Active Only</option>
            <option value="deleted" @selected(request('record_state') === 'deleted')>Removed Only</option>
        </select>

        <button class="btn btn-outline-dynamic rounded-3 px-3" type="submit"><i class="bi bi-funnel me-2"></i>Filter</button>
        <a href="{{ route('admin.social.follows') }}" class="btn btn-outline-dynamic rounded-3 px-3"><i class="bi bi-arrow-clockwise me-2"></i>Reset</a>
    </form>

    <div class="glass rounded-4 overflow-hidden border-white-05">
        <div class="table-responsive scrollbar-hidden">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-white-05">
                    <tr>
                        <th class="ps-4 text-muted small fw-bold text-uppercase py-3">Follow</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Follower</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Following</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Status</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Created</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">State</th>
                        <th class="text-muted small fw-bold text-uppercase py-3 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($follows as $follow)
                        @php
                            $statusClass = match ($follow->status) {
                                'accepted' => 'bg-success-soft text-success',
                                'pending' => 'bg-warning-soft text-warning',
                                'blocked' => 'bg-danger-soft text-danger',
                                default => 'bg-secondary-soft text-muted',
                            };
                        @endphp
                        <tr class="border-white-05">
                            <td class="ps-4">
                                <div class="fw-bold small">#{{ $follow->id }}</div>
                                <div class="text-muted extra-small">Updated {{ $follow->updated_at?->format('M d, Y H:i') ?? '-' }}</div>
                            </td>
                            <td>
                                @if ($follow->follower)
                                    <a href="{{ route('admin.users.show', $follow->follower->id) }}" class="text-decoration-none text-reset d-flex align-items-center gap-3">
                                        <img src="{{ $follow->follower->avatar_url ?: 'https://ui-avatars.com/api/?name='.urlencode($follow->follower->full_name ?: $follow->follower->username ?: 'User').'&background=1f172a&color=ffffff' }}" alt="{{ $follow->follower->username }}" class="rounded-circle border border-white-05" width="42" height="42" style="object-fit: cover;">
                                        <div>
                                            <div class="small fw-semibold">{{ $follow->follower->full_name ?: 'User #'.$follow->follower->id }}</div>
                                            <div class="text-muted extra-small">{{ '@'.$follow->follower->username }}</div>
                                        </div>
                                    </a>
                                @else
                                    <span class="text-muted small">User unavailable</span>
                                @endif
                            </td>
                            <td>
                                @if ($follow->following)
                                    <a href="{{ route('admin.users.show', $follow->following->id) }}" class="text-decoration-none text-reset d-flex align-items-center gap-3">
                                        <img src="{{ $follow->following->avatar_url ?: 'https://ui-avatars.com/api/?name='.urlencode($follow->following->full_name ?: $follow->following->username ?: 'User').'&background=1f172a&color=ffffff' }}" alt="{{ $follow->following->username }}" class="rounded-circle border border-white-05" width="42" height="42" style="object-fit: cover;">
                                        <div>
                                            <div class="small fw-semibold">{{ $follow->following->full_name ?: 'User #'.$follow->following->id }}</div>
                                            <div class="text-muted extra-small">{{ '@'.$follow->following->username }}</div>
                                        </div>
                                    </a>
                                @else
                                    <span class="text-muted small">User unavailable</span>
                                @endif
                            </td>
                            <td><span class="badge {{ $statusClass }} rounded-pill">{{ str($follow->status)->title() }}</span></td>
                            <td><span class="text-muted small">{{ $follow->created_at?->format('M d, Y H:i') ?? '-' }}</span></td>
                            <td>
                                @if ($follow->deleted_at)
                                    <span class="badge bg-danger-soft text-danger rounded-pill">Removed</span>
                                @else
                                    <span class="badge bg-info-soft text-info rounded-pill">Active</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-2 flex-wrap justify-content-end">
                                    @if ($follow->follower)
                                        <a href="{{ route('admin.users.show', $follow->follower->id) }}" class="btn btn-sm btn-outline-dynamic rounded-3 px-3">
                                            <i class="bi bi-person me-1"></i>Follower
                                        </a>
                                    @endif
                                    @if ($follow->following)
                                        <a href="{{ route('admin.users.show', $follow->following->id) }}" class="btn btn-sm btn-outline-dynamic rounded-3 px-3">
                                            <i class="bi bi-person-badge me-1"></i>Following
                                        </a>
                                    @endif
                                    @if (! $follow->deleted_at)
                                        <form method="POST" action="{{ route('admin.social.follows.delete', $follow) }}" onsubmit="return confirm('Remove this follow relationship?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-3 px-3">
                                                <i class="bi bi-trash3 me-1"></i>Remove
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center py-5 text-muted">No follow records found for the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 bg-white-05 border-top border-white-05 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="text-muted small">
                Showing <span class="text-emphasis-dynamic fw-bold">{{ $follows->firstItem() ?? 0 }}-{{ $follows->lastItem() ?? 0 }}</span>
                of <span class="text-emphasis-dynamic fw-bold">{{ number_format($follows->total()) }}</span> follow records
            </div>
            {{ $follows->links() }}
        </div>
    </div>
</div>
@endsection
