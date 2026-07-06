@php
    $tabs = [
        ['route' => 'admin.engagement.post_likes', 'key' => 'post_likes', 'label' => 'Post Likes'],
        ['route' => 'admin.engagement.comment_likes', 'key' => 'comment_likes', 'label' => 'Comment Likes'],
        ['route' => 'admin.engagement.reply_likes', 'key' => 'reply_likes', 'label' => 'Reply Likes'],
        ['route' => 'admin.engagement.shares', 'key' => 'shares', 'label' => 'Shares'],
        ['route' => 'admin.engagement.saved', 'key' => 'saved', 'label' => 'Saved Posts'],
        ['route' => 'admin.engagement.views', 'key' => 'views', 'label' => 'Views'],
    ];
@endphp

<div class="mb-4">
    <div class="d-flex flex-wrap gap-1 p-1 rounded-4 glass border border-white-05">
        @foreach ($tabs as $tab)
            <a href="{{ route($tab['route']) }}" class="btn btn-sm rounded-3 px-2 py-2 fw-bold {{ request()->routeIs($tab['route']) ? 'bg-primary-gradient text-white shadow-glow' : 'text-muted hover-bg-white-10' }}">
                {{ $tab['label'] }}
                <span class="ms-1 opacity-50 small">{{ number_format($engagementTabCounts[$tab['key']] ?? 0) }}</span>
            </a>
        @endforeach
    </div>
</div>
