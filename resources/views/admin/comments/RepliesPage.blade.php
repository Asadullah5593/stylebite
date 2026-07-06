@extends('admin.layouts.app')

@section('content')
<div class="comments-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold">Comments</span>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">Replies</span>
    </nav>

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <div>
            <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">Comments</h1>
            <p class="text-muted small mb-0">Comments and threaded replies</p>
        </div>
    </div>

    @if (session('status'))
        <div class="glass rounded-4 p-3 mb-4 border border-primary-soft bg-primary-soft-opaque">
            <i class="bi bi-check-circle me-2 text-success"></i>{{ session('status') }}
        </div>
    @endif

    @include('admin.comments.partials.tabs')

    <form method="GET" action="{{ route('admin.comments.replies') }}" class="glass rounded-4 p-3 d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="position-relative flex-grow-1" style="min-width: 250px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control ps-5 border-0 bg-dark-soft rounded-3" placeholder="Search replies, parent comment, user...">
        </div>

        <select name="status" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Status</option>
            @foreach (['active' => 'Active', 'hidden' => 'Hidden', 'deleted' => 'Deleted', 'blocked' => 'Blocked'] as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <select name="moderation_status" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Moderation</option>
            @foreach (['clean' => 'Clean', 'flagged' => 'Flagged', 'restricted' => 'Restricted'] as $value => $label)
                <option value="{{ $value }}" @selected(request('moderation_status') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <button class="btn btn-outline-dynamic rounded-3 px-3" type="submit"><i class="bi bi-funnel me-2"></i>Filter</button>
        <a href="{{ route('admin.comments.replies') }}" class="btn btn-outline-dynamic rounded-3 px-3"><i class="bi bi-arrow-clockwise me-2"></i>Reset</a>
    </form>

    <div class="glass rounded-4 overflow-hidden border-white-05">
        <div class="table-responsive scrollbar-hidden">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-white-05">
                    <tr>
                        <th class="ps-4 text-muted small fw-bold text-uppercase py-3">Reply</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Parent Comment</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">User</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Likes</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Status</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Moderation</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Created</th>
                        <th class="text-muted small fw-bold text-uppercase py-3 pe-4 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($replies as $reply)
                        <tr class="border-white-05">
                            <td class="ps-4">
                                <div class="fw-bold small">#{{ $reply->id }}</div>
                                <div class="text-muted extra-small text-truncate" style="max-width: 320px;">{{ $reply->body }}</div>
                            </td>
                            <td>
                                <div class="text-muted small">{{ str($reply->comment?->body ?: 'No parent comment')->limit(40) }}</div>
                                <div class="text-muted extra-small">Comment #{{ $reply->comment_id }}</div>
                            </td>
                            <td>
                                <div class="small fw-semibold">{{ $reply->user?->username ? '@'.$reply->user->username : 'Removed account' }}</div>
                                <div class="text-muted extra-small">{{ $reply->user?->full_name ?: 'User record unavailable' }}</div>
                            </td>
                            <td><span class="text-muted small">{{ $reply->like_count }}</span></td>
                            <td><span class="badge {{ $reply->status === 'active' ? 'bg-success-soft text-success' : 'bg-warning-soft text-warning' }} rounded-pill">{{ str($reply->status)->title() }}</span></td>
                            <td>
                                <span class="badge {{ $reply->moderation_status === 'clean' ? 'bg-success-soft text-success' : 'bg-warning-soft text-warning' }} rounded-pill">{{ str($reply->moderation_status)->title() }}</span>
                                <div class="text-muted extra-small mt-1">{{ $reply->is_reported ? 'Reported' : 'Not reported' }} · {{ $reply->is_blocked ? 'Blocked' : 'Visible' }}</div>
                            </td>
                            <td><span class="text-muted small">{{ $reply->created_at?->format('M d, Y H:i') }}</span></td>
                            <td class="pe-4">
                                <form method="POST" action="{{ route('admin.comments.replies.update', $reply) }}" class="d-grid gap-2" style="min-width: 220px;">
                                    @csrf
                                    @method('PATCH')
                                    <select name="status" class="form-select form-select-sm border-0 bg-dark-soft rounded-3 text-muted">
                                        @foreach (['active' => 'Active', 'hidden' => 'Hidden', 'deleted' => 'Deleted', 'blocked' => 'Blocked'] as $value => $label)
                                            <option value="{{ $value }}" @selected($reply->status === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <select name="moderation_status" class="form-select form-select-sm border-0 bg-dark-soft rounded-3 text-muted">
                                        @foreach (['clean' => 'Clean', 'flagged' => 'Flagged', 'restricted' => 'Restricted'] as $value => $label)
                                            <option value="{{ $value }}" @selected($reply->moderation_status === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <button class="btn btn-sm btn-outline-dynamic rounded-3" type="submit">
                                        <i class="bi bi-save me-2"></i>Update
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center py-5 text-muted">No replies found for the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 bg-white-05 border-top border-white-05 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="text-muted small">
                Showing <span class="text-emphasis-dynamic fw-bold">{{ $replies->firstItem() ?? 0 }}-{{ $replies->lastItem() ?? 0 }}</span>
                of <span class="text-emphasis-dynamic fw-bold">{{ number_format($replies->total()) }}</span> replies
            </div>
            {{ $replies->links() }}
        </div>
    </div>
</div>
@endsection
