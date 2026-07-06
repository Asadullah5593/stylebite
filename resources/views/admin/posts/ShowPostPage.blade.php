@extends('admin.layouts.app')

@section('content')
<div class="posts-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <a href="{{ route('admin.posts.all_posts') }}" class="text-decoration-none text-reset fw-bold">Posts</a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">Post #{{ $post->id }}</span>
    </nav>

    @if (session('status'))
        <div class="glass rounded-4 p-3 mb-4 border border-primary-soft bg-primary-soft-opaque">
            <i class="bi bi-check-circle me-2 text-success"></i>{{ session('status') }}
        </div>
    @endif

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">Post Detail</h1>
            <p class="text-muted small mb-0">Review content, metadata, media, tags, and recent activity</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.posts.edit', $post) }}" class="btn btn-outline-dynamic rounded-3">
                <i class="bi bi-pencil me-2"></i>Edit
            </a>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12 col-xl-8">
            <div class="glass rounded-4 p-4 h-100 border border-white-05">
                <h3 class="h6 fw-bold mb-3">Content</h3>
                <div class="fw-bold mb-2">#{{ $post->id }}</div>
                <div class="text-muted">{{ $post->caption ?: 'No caption for this post.' }}</div>
                <div class="row g-3 mt-3 small">
                    <div class="col-md-6"><span class="text-muted d-block">Author</span>{{ $post->user?->full_name ?: '@'.$post->user?->username }}</div>
                    <div class="col-md-6"><span class="text-muted d-block">Type</span>{{ str($post->post_type)->replace('_', ' ')->title() }}</div>
                    <div class="col-md-6"><span class="text-muted d-block">Status</span>{{ str($post->status)->replace('_', ' ')->title() }}</div>
                    <div class="col-md-6"><span class="text-muted d-block">Moderation</span>{{ str($post->moderation_status)->replace('_', ' ')->title() }}</div>
                    <div class="col-md-6"><span class="text-muted d-block">Visibility</span>{{ str($post->visibility)->replace('_', ' ')->title() }}</div>
                    <div class="col-md-6"><span class="text-muted d-block">Feed</span>{{ $post->feed_type ? str($post->feed_type)->title() : 'Not set' }}</div>
                    <div class="col-md-6"><span class="text-muted d-block">Location</span>{{ collect([$post->location_name, $post->city, $post->country])->filter()->implode(', ') ?: 'Not set' }}</div>
                    <div class="col-md-6"><span class="text-muted d-block">Restaurant / Dish</span>{{ collect([$post->restaurant, $post->dish_name])->filter()->implode(' / ') ?: 'Not set' }}</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="glass rounded-4 p-4 h-100 border border-white-05">
                <h3 class="h6 fw-bold mb-3">Moderation</h3>
                <form method="POST" action="{{ route('admin.posts.moderate', $post) }}" class="d-grid gap-3">
                    @csrf
                    @method('PATCH')
                    <div>
                        <label class="form-label small text-muted">Status</label>
                        <select name="status" class="form-select bg-dark-soft border-0 rounded-3">
                            @foreach (['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived', 'under_review' => 'Under Review', 'removed' => 'Removed'] as $value => $label)
                                <option value="{{ $value }}" @selected($post->status === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label small text-muted">Moderation Status</label>
                        <select name="moderation_status" class="form-select bg-dark-soft border-0 rounded-3">
                            @foreach (['clean' => 'Clean', 'flagged' => 'Flagged', 'restricted' => 'Restricted', 'blocked' => 'Blocked'] as $value => $label)
                                <option value="{{ $value }}" @selected($post->moderation_status === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button class="btn btn-outline-warning rounded-3" type="submit">
                        <i class="bi bi-shield-check me-2"></i>Update Moderation
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-xl-4">
            <div class="glass rounded-4 p-4 h-100 border border-white-05">
                <h3 class="h6 fw-bold mb-3">Media</h3>
                @forelse ($post->media->take(6) as $item)
                    <div class="d-flex justify-content-between py-2 border-bottom border-white-05 small">
                        <span>{{ str($item->media_type)->title() }} · {{ str($item->media_role)->replace('_', ' ')->title() }}</span>
                        <span class="text-muted">{{ str($item->processing_status)->title() }}</span>
                    </div>
                @empty
                    <p class="text-muted mb-0">No media attached to this post.</p>
                @endforelse
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="glass rounded-4 p-4 h-100 border border-white-05">
                <h3 class="h6 fw-bold mb-3">Tags</h3>
                @if ($post->tags->isNotEmpty())
                    <div class="d-flex flex-wrap gap-2">
                        @foreach ($post->tags as $tag)
                            <span class="badge bg-info-soft text-info rounded-pill">{{ $tag->name }}</span>
                        @endforeach
                    </div>
                @else
                    <p class="text-muted mb-0">No tags linked to this post.</p>
                @endif
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="glass rounded-4 p-4 h-100 border border-white-05">
                <h3 class="h6 fw-bold mb-3">Engagement</h3>
                <div class="d-grid gap-2 small">
                    <div><span class="text-muted d-block">Likes</span>{{ number_format($post->like_count) }}</div>
                    <div><span class="text-muted d-block">Comments</span>{{ number_format($post->comment_count) }}</div>
                    <div><span class="text-muted d-block">Shares</span>{{ number_format($post->share_count) }}</div>
                    <div><span class="text-muted d-block">Saves</span>{{ number_format($post->save_count) }}</div>
                    <div><span class="text-muted d-block">Views</span>{{ number_format($post->view_count) }}</div>
                    <div><span class="text-muted d-block">Rating</span>{{ $post->rating_avg ? number_format($post->rating_avg, 2) : 'No rating yet' }}</div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="glass rounded-4 p-4 border border-white-05">
                <h3 class="h6 fw-bold mb-3">Recent Comments</h3>
                @forelse ($post->comments->take(8) as $comment)
                    <div class="py-2 border-bottom border-white-05 small">
                        <div class="fw-semibold">{{ $comment->user?->full_name ?: '@'.$comment->user?->username }}</div>
                        <div class="text-muted">{{ $comment->body }}</div>
                    </div>
                @empty
                    <p class="text-muted mb-0">No comments found for this post.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
