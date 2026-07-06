@extends('admin.layouts.app')

@section('content')
<div class="comments-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold">Comments</span>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">Comments</span>
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

    <form method="GET" action="{{ route('admin.comments.comments') }}" class="glass rounded-4 p-3 d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="position-relative flex-grow-1" style="min-width: 250px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control ps-5 border-0 bg-dark-soft rounded-3" placeholder="Search comments, post caption, user...">
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
        <a href="{{ route('admin.comments.comments') }}" class="btn btn-outline-dynamic rounded-3 px-3"><i class="bi bi-arrow-clockwise me-2"></i>Reset</a>
    </form>

    <div class="glass rounded-4 overflow-hidden border-white-05">
        <div class="table-responsive scrollbar-hidden">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-white-05">
                    <tr>
                        <th class="ps-4 text-muted small fw-bold text-uppercase py-3">Comment</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Post</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">User</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Stats</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Status</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Moderation</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Created</th>
                        <th class="text-muted small fw-bold text-uppercase py-3 pe-4 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($comments as $comment)
                        <tr class="border-white-05">
                            <td class="ps-4">
                                <div class="fw-bold small">#{{ $comment->id }}</div>
                                <div class="text-muted extra-small text-truncate" style="max-width: 320px;">{{ $comment->body }}</div>
                            </td>
                            <td>
                                <div class="text-muted small">{{ str($comment->post?->caption ?: 'No caption')->limit(32) }}</div>
                                <div class="text-muted extra-small">Post #{{ $comment->post_id }}</div>
                            </td>
                            <td>
                                <div class="small fw-semibold">{{ $comment->user?->username ? '@'.$comment->user->username : 'Removed account' }}</div>
                                <div class="text-muted extra-small">{{ $comment->user?->full_name ?: 'User record unavailable' }}</div>
                            </td>
                            <td><span class="text-muted small">{{ $comment->like_count }} likes · {{ $comment->reply_count }} replies</span></td>
                            <td><span class="badge {{ $comment->status === 'active' ? 'bg-success-soft text-success' : 'bg-warning-soft text-warning' }} rounded-pill">{{ str($comment->status)->title() }}</span></td>
                            <td>
                                <span class="badge {{ $comment->moderation_status === 'clean' ? 'bg-success-soft text-success' : 'bg-warning-soft text-warning' }} rounded-pill">{{ str($comment->moderation_status)->title() }}</span>
                                <div class="text-muted extra-small mt-1">{{ $comment->is_reported ? 'Reported' : 'Not reported' }} · {{ $comment->is_blocked ? 'Blocked' : 'Visible' }}</div>
                            </td>
                            <td><span class="text-muted small">{{ $comment->created_at?->format('M d, Y H:i') }}</span></td>
                            <td class="pe-4">
                                <form method="POST" action="{{ route('admin.comments.update', $comment) }}" class="d-grid gap-2" style="min-width: 220px;">
                                    @csrf
                                    @method('PATCH')
                                    <select name="status" class="form-select form-select-sm border-0 bg-dark-soft rounded-3 text-muted">
                                        @foreach (['active' => 'Active', 'hidden' => 'Hidden', 'deleted' => 'Deleted', 'blocked' => 'Blocked'] as $value => $label)
                                            <option value="{{ $value }}" @selected($comment->status === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <select name="moderation_status" class="form-select form-select-sm border-0 bg-dark-soft rounded-3 text-muted">
                                        @foreach (['clean' => 'Clean', 'flagged' => 'Flagged', 'restricted' => 'Restricted'] as $value => $label)
                                            <option value="{{ $value }}" @selected($comment->moderation_status === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <button class="btn btn-sm btn-outline-dynamic rounded-3" type="submit">
                                        <i class="bi bi-save me-2"></i>Update
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center py-5 text-muted">No comments found for the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 bg-white-05 border-top border-white-05 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="text-muted small">
                Showing <span class="text-emphasis-dynamic fw-bold">{{ $comments->firstItem() ?? 0 }}-{{ $comments->lastItem() ?? 0 }}</span>
                of <span class="text-emphasis-dynamic fw-bold">{{ number_format($comments->total()) }}</span> comments
            </div>
            {{ $comments->links() }}
        </div>
    </div>
</div>
@endsection
