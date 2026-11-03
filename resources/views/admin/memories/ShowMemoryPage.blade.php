@extends('admin.layouts.app')

@section('content')
<div class="memories-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <a href="{{ route('admin.memories.users') }}" class="text-decoration-none text-reset fw-bold">Memories</a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">#{{ $memory->id }}</span>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">{{ $memory->title }}</h1>
            <p class="text-muted small mb-0">Admin view — includes private / followers-only content</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @if ($memory->user_id)
                <a href="{{ route('admin.memories.memories', ['user_id' => $memory->user_id]) }}" class="btn btn-outline-dynamic rounded-3">
                    <i class="bi bi-person me-2"></i>All from this user
                </a>
            @endif
            <a href="{{ route('admin.memories.memories') }}" class="btn btn-outline-dynamic rounded-3">
                <i class="bi bi-arrow-left me-2"></i>All memories
            </a>
        </div>
    </div>

    @include('admin.memories.partials.tabs')

    <div class="row g-4 mb-4">
        <div class="col-12 col-xl-8">
            <div class="glass rounded-4 p-4 h-100 border border-white-05">
                <h3 class="h6 fw-bold mb-3">Details</h3>
                <div class="fw-bold mb-2">#{{ $memory->id }} · {{ $memory->title }}</div>
                @if ($memory->short_title)
                    <div class="text-muted small mb-2">{{ $memory->short_title }}</div>
                @endif
                <div class="text-muted">{{ $memory->description ?: ($memory->short_description ?: 'No description.') }}</div>
                @if ($memory->note)
                    <div class="mt-3 small"><span class="text-muted">Note:</span> {{ $memory->note }}</div>
                @endif
                <div class="row g-3 mt-3 small">
                    <div class="col-md-6">
                        <span class="text-muted d-block">Owner</span>
                        @if ($memory->user)
                            <a href="{{ route('admin.memories.memories', ['user_id' => $memory->user_id]) }}" class="text-decoration-none">
                                {{ $memory->user->full_name ?: '@'.$memory->user->username }}
                            </a>
                            <div class="text-muted extra-small">{{ '@'.$memory->user->username }}</div>
                        @else
                            Unknown
                        @endif
                    </div>
                    <div class="col-md-6"><span class="text-muted d-block">Visibility</span>{{ str($memory->visibility)->replace('_', ' ')->title() }}</div>
                    <div class="col-md-6"><span class="text-muted d-block">Status</span>{{ str($memory->status)->title() }}</div>
                    <div class="col-md-6"><span class="text-muted d-block">Memory date</span>{{ $memory->memory_date?->format('M d, Y') ?? '—' }}</div>
                    <div class="col-md-6"><span class="text-muted d-block">Location</span>{{ collect([$memory->location_name, $memory->city, $memory->country])->filter()->implode(', ') ?: '—' }}</div>
                    <div class="col-md-6"><span class="text-muted d-block">Stats</span>{{ $memory->like_count }} likes · {{ $memory->comment_count }} comments · {{ $memory->save_count }} saves</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-4">
            <div class="glass rounded-4 p-4 h-100 border border-white-05">
                <h3 class="h6 fw-bold mb-3">Quick links</h3>
                <div class="d-grid gap-2">
                    @if ($memory->user)
                        <a href="{{ route('admin.users.show', $memory->user) }}" class="btn btn-sm btn-outline-dynamic rounded-3">
                            <i class="bi bi-person-badge me-1"></i>User profile
                        </a>
                    @endif
                    <a href="{{ route('admin.memories.memory-media') }}?q={{ urlencode($memory->title) }}" class="btn btn-sm btn-outline-dynamic rounded-3">
                        <i class="bi bi-images me-1"></i>Media tab search
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="glass rounded-4 p-4 border border-white-05 mb-4">
        <h3 class="h6 fw-bold mb-3">Media ({{ $memory->media->count() }})</h3>
        @if ($memory->media->isNotEmpty())
            <div class="row g-3">
                @foreach ($memory->media as $item)
                    <div class="col-12 col-md-6 col-xl-4">
                        @include('admin.memories.partials.media-preview', ['media' => $item, 'large' => true])
                        <div class="d-flex justify-content-between mt-2 small text-muted">
                            <span>{{ str($item->media_type)->title() }} · sort {{ $item->sort_order }}</span>
                            @if ($item->file_url)
                                <a href="{{ stylebite_asset_url($item->file_url) }}" target="_blank" rel="noopener" class="text-decoration-none">Open</a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-muted mb-0">No media attached to this memory.</p>
        @endif
    </div>

    <div class="glass rounded-4 p-4 border border-white-05">
        <h3 class="h6 fw-bold mb-3">Recent comments</h3>
        @forelse ($memory->comments as $comment)
            <div class="border-bottom border-white-05 py-3">
                <div class="small fw-semibold">{{ $comment->user?->full_name ?: '@'.$comment->user?->username }}</div>
                <div class="text-muted small">{{ $comment->body }}</div>
                <div class="text-muted extra-small mt-1">{{ $comment->created_at?->format('M d, Y H:i') }} · {{ str($comment->status)->title() }}</div>
            </div>
        @empty
            <p class="text-muted mb-0">No comments yet.</p>
        @endforelse
    </div>
</div>
@endsection
