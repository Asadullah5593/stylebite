@php
    $tabs = [
        ['route' => 'admin.posts.all_posts', 'key' => 'all_posts', 'label' => 'All Posts'],
        ['route' => 'admin.posts.post_media', 'key' => 'post_media', 'label' => 'Media'],
        ['route' => 'admin.posts.post_tags', 'key' => 'post_tags', 'label' => 'Tags'],
        ['route' => 'admin.posts.post_ratings', 'key' => 'post_ratings', 'label' => 'Ratings'],
    ];
@endphp

<div class="mb-4">
    <div class="d-flex flex-wrap gap-1 p-1 rounded-4 glass border border-white-05">
        @foreach ($tabs as $tab)
            <a href="{{ route($tab['route']) }}" class="btn btn-sm rounded-3 px-2 py-2 fw-bold {{ request()->routeIs($tab['route']) ? 'bg-primary-gradient text-white shadow-glow' : 'text-muted hover-bg-white-10' }}">
                {{ $tab['label'] }}
                <span class="ms-1 opacity-50 small">{{ number_format($postTabCounts[$tab['key']] ?? 0) }}</span>
            </a>
        @endforeach
    </div>
</div>
