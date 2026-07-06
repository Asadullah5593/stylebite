@php
    $tabs = [
        ['route' => 'admin.memories.memories', 'key' => 'memories', 'label' => 'Memories'],
        ['route' => 'admin.memories.memory-media', 'key' => 'memory_media', 'label' => 'Media'],
        ['route' => 'admin.memories.memory-comments', 'key' => 'memory_comments', 'label' => 'Comments'],
        ['route' => 'admin.memories.saved_memories', 'key' => 'saved_memories', 'label' => 'Saved'],
    ];
@endphp

<div class="mb-4">
    <div class="d-flex flex-wrap gap-1 p-1 rounded-4 glass border border-white-05">
        @foreach ($tabs as $tab)
            <a href="{{ route($tab['route']) }}" class="btn btn-sm rounded-3 px-2 py-2 fw-bold {{ request()->routeIs($tab['route']) ? 'bg-primary-gradient text-white shadow-glow' : 'text-muted hover-bg-white-10' }}">
                {{ $tab['label'] }}
                <span class="ms-1 opacity-50 small">{{ number_format($memoryTabCounts[$tab['key']] ?? 0) }}</span>
            </a>
        @endforeach
    </div>
</div>
