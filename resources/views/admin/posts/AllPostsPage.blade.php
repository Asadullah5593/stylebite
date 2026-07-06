@extends('admin.layouts.app')

@section('content')
<div class="posts-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold">Posts</span>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">All Posts</span>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">Posts</h1>
            <p class="text-muted small mb-0">Posts, media, tags and ratings</p>
        </div>
    </div>

    @if (session('status'))
        <div class="glass rounded-4 p-3 mb-4 border border-primary-soft bg-primary-soft-opaque">
            <i class="bi bi-check-circle me-2 text-success"></i>{{ session('status') }}
        </div>
    @endif

    @include('admin.posts.partials.tabs')

    <form method="GET" action="{{ route('admin.posts.all_posts') }}" class="glass rounded-4 p-3 d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="position-relative flex-grow-1" style="min-width: 250px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control ps-5 border-0 bg-dark-soft rounded-3" placeholder="Search posts, author, city, location...">
        </div>

        <select name="post_type" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Types</option>
            @foreach (['outfit' => 'Outfit', 'food' => 'Food', 'reel' => 'Reel', 'memory' => 'Memory', 'contest_submission' => 'Contest Submission'] as $value => $label)
                <option value="{{ $value }}" @selected(request('post_type') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <select name="status" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Status</option>
            @foreach (['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived', 'under_review' => 'Under Review', 'removed' => 'Removed'] as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <button class="btn btn-outline-dynamic rounded-3 px-3" type="submit"><i class="bi bi-funnel me-2"></i>Filter</button>
        <a href="{{ route('admin.posts.all_posts') }}" class="btn btn-outline-dynamic rounded-3 px-3"><i class="bi bi-arrow-clockwise me-2"></i>Reset</a>
    </form>

    <div class="glass rounded-4 overflow-hidden border-white-05">
        <div class="table-responsive scrollbar-hidden">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-white-05">
                    <tr>
                        <th class="ps-4 text-muted small fw-bold text-uppercase py-3">Post</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Author</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Type</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Location</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Stats</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Status</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Published</th>
                        <th class="text-muted small fw-bold text-uppercase py-3 pe-4 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($posts as $post)
                        <tr class="border-white-05">
                            <td class="ps-4">
                                <div class="fw-bold small">#{{ $post->id }}</div>
                                <div class="text-muted extra-small text-truncate" style="max-width: 320px;">{{ $post->caption ?: 'No caption' }}</div>
                            </td>
                            <td>
                                <div class="small fw-semibold">{{ $post->user?->full_name ?: '@'.$post->user?->username }}</div>
                                <div class="text-muted extra-small">{{ $post->user?->username ? '@'.$post->user->username : 'Unknown author' }}</div>
                            </td>
                            <td><span class="badge bg-info-soft text-info rounded-pill">{{ str($post->post_type)->replace('_', ' ')->title() }}</span></td>
                            <td><span class="text-muted small">{{ collect([$post->location_name, $post->city, $post->country])->filter()->implode(', ') ?: '-' }}</span></td>
                            <td><span class="text-muted small">{{ $post->media_count }} media · {{ $post->comment_count }} comments · {{ $post->like_count }} likes</span></td>
                            <td>
                                <span class="badge {{ $post->status === 'published' ? 'bg-success-soft text-success' : ($post->status === 'under_review' ? 'bg-warning-soft text-warning' : 'bg-secondary-soft text-muted') }} rounded-pill">
                                    {{ str($post->status)->replace('_', ' ')->title() }}
                                </span>
                                <div class="text-muted extra-small mt-1">{{ str($post->moderation_status)->replace('_', ' ')->title() }}</div>
                            </td>
                            <td><span class="text-muted small">{{ $post->published_at?->format('M d, Y') ?? '-' }}</span></td>
                            <td class="pe-4">
                                <div class="d-grid gap-2" style="min-width: 230px;">
                                    <div class="d-flex gap-2">
                                        <a href="{{ route('admin.posts.show', $post) }}" class="btn btn-sm btn-outline-dynamic rounded-3 flex-fill">
                                            <i class="bi bi-eye me-1"></i>View
                                        </a>
                                        <a href="{{ route('admin.posts.edit', $post) }}" class="btn btn-sm btn-outline-dynamic rounded-3 flex-fill">
                                            <i class="bi bi-pencil me-1"></i>Edit
                                        </a>
                                    </div>
                                    <form method="POST" action="{{ route('admin.posts.moderate', $post) }}" class="d-grid gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <select name="status" class="form-select form-select-sm border-0 bg-dark-soft rounded-3 text-muted">
                                            @foreach (['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived', 'under_review' => 'Under Review', 'removed' => 'Removed'] as $value => $label)
                                                <option value="{{ $value }}" @selected($post->status === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <select name="moderation_status" class="form-select form-select-sm border-0 bg-dark-soft rounded-3 text-muted">
                                            @foreach (['clean' => 'Clean', 'flagged' => 'Flagged', 'restricted' => 'Restricted', 'blocked' => 'Blocked'] as $value => $label)
                                                <option value="{{ $value }}" @selected($post->moderation_status === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <button class="btn btn-sm btn-outline-warning rounded-3" type="submit">
                                            <i class="bi bi-shield-check me-1"></i>Moderate
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center py-5 text-muted">No posts found for the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 bg-white-05 border-top border-white-05 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="text-muted small">
                Showing <span class="text-emphasis-dynamic fw-bold">{{ $posts->firstItem() ?? 0 }}-{{ $posts->lastItem() ?? 0 }}</span>
                of <span class="text-emphasis-dynamic fw-bold">{{ number_format($posts->total()) }}</span> posts
            </div>
            {{ $posts->links() }}
        </div>
    </div>
</div>
@endsection
