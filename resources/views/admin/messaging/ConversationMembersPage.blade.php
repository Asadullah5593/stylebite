@extends('admin.layouts.app')

@section('content')
<div class="messaging-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold">Messaging</span>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">Conversation Members</span>
    </nav>

    <div class="mb-4">
        <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">Messaging</h1>
        <p class="text-muted small mb-0">Member roles, participation state, and last-read context</p>
    </div>

    @include('admin.messaging.partials.tabs')

    <form method="GET" action="{{ route('admin.messaging.members') }}" class="glass rounded-4 p-3 d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="position-relative flex-grow-1" style="min-width: 250px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control ps-5 border-0 bg-dark-soft rounded-3" placeholder="Search user, conversation, role...">
        </div>

        <select name="role" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Roles</option>
            @foreach (['member' => 'Member', 'admin' => 'Admin', 'owner' => 'Owner'] as $value => $label)
                <option value="{{ $value }}" @selected(request('role') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <select name="status" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Status</option>
            @foreach (['active' => 'Active', 'left' => 'Left', 'removed' => 'Removed', 'blocked' => 'Blocked'] as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <button class="btn btn-outline-dynamic rounded-3 px-3" type="submit"><i class="bi bi-funnel me-2"></i>Filter</button>
        <a href="{{ route('admin.messaging.members') }}" class="btn btn-outline-dynamic rounded-3 px-3"><i class="bi bi-arrow-clockwise me-2"></i>Reset</a>
    </form>

    <div class="glass rounded-4 overflow-hidden border-white-05">
        <div class="table-responsive scrollbar-hidden">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-white-05">
                    <tr>
                        <th class="ps-4 text-muted small fw-bold text-uppercase py-3">User</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Conversation</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Role</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Status</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Last Read</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Joined</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($members as $member)
                        <tr class="border-white-05">
                            <td class="ps-4">
                                @if ($member->user)
                                    <a href="{{ route('admin.users.show', $member->user) }}" class="text-decoration-none d-flex align-items-center gap-3">
                                        @if ($member->user->avatar_url)
                                            <img src="{{ $member->user->avatar_url }}" alt="{{ $member->user->username }}" class="rounded-circle border border-white-05" width="42" height="42" style="object-fit: cover;">
                                        @else
                                            <div class="rounded-circle border border-white-05 d-flex align-items-center justify-content-center text-muted" style="width:42px;height:42px;">
                                                <i class="bi bi-person"></i>
                                            </div>
                                        @endif
                                        <div>
                                            <div class="small fw-semibold">{{ $member->user->full_name ?: '@'.$member->user->username }}</div>
                                            <div class="text-muted extra-small">{{ '@'.$member->user->username }}</div>
                                        </div>
                                    </a>
                                @else
                                    <span class="text-muted small">Missing user</span>
                                @endif
                            </td>
                            <td>
                                <div class="small fw-semibold">{{ $member->conversation?->title ?: 'Conversation #'.$member->conversation_id }}</div>
                                <div class="text-muted extra-small">{{ str($member->conversation?->type ?: 'unknown')->title() }}</div>
                            </td>
                            <td><span class="badge {{ $member->role === 'owner' ? 'bg-primary-soft text-primary' : ($member->role === 'admin' ? 'bg-info-soft text-info' : 'bg-secondary-soft text-muted') }} rounded-pill">{{ str($member->role)->title() }}</span></td>
                            <td>
                                <span class="badge {{ $member->status === 'active' ? 'bg-success-soft text-success' : ($member->status === 'left' ? 'bg-secondary-soft text-muted' : 'bg-danger-soft text-danger') }} rounded-pill">{{ str($member->status)->title() }}</span>
                                @if ($member->mute_until)
                                    <div class="text-muted extra-small mt-1">Muted until {{ $member->mute_until->format('M d, Y H:i') }}</div>
                                @endif
                            </td>
                            <td style="min-width: 240px;">
                                @if ($member->lastReadMessage)
                                    <div class="small">{{ str($member->lastReadMessage->body ?: '['.$member->lastReadMessage->message_type.']')->limit(50) }}</div>
                                    <div class="text-muted extra-small">{{ $member->last_read_at?->format('M d, Y H:i') ?? 'Timestamp missing' }}</div>
                                @else
                                    <span class="text-muted small">No read marker</span>
                                @endif
                            </td>
                            <td>
                                <div class="text-muted small">{{ $member->joined_at?->format('M d, Y H:i') }}</div>
                                <div class="text-muted extra-small">{{ $member->left_at?->format('M d, Y H:i') ? 'Left '.$member->left_at->format('M d, Y H:i') : 'Still in conversation' }}</div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center py-5 text-muted">No conversation members found for the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 bg-white-05 border-top border-white-05 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="text-muted small">Showing <span class="text-emphasis-dynamic fw-bold">{{ $members->firstItem() ?? 0 }}-{{ $members->lastItem() ?? 0 }}</span> of <span class="text-emphasis-dynamic fw-bold">{{ number_format($members->total()) }}</span> members</div>
            {{ $members->links() }}
        </div>
    </div>
</div>
@endsection
