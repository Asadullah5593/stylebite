@php
    $tabs = [
        ['route' => 'admin.messaging.conversations', 'key' => 'conversations', 'label' => 'Conversations'],
        ['route' => 'admin.messaging.members', 'key' => 'members', 'label' => 'Members'],
        ['route' => 'admin.messaging.messages', 'key' => 'messages', 'label' => 'Messages'],
        ['route' => 'admin.messaging.attachments', 'key' => 'attachments', 'label' => 'Attachments'],
        ['route' => 'admin.messaging.reads', 'key' => 'reads', 'label' => 'Read Receipts'],
    ];
@endphp

<div class="mb-4">
    <div class="d-flex flex-wrap gap-1 p-1 rounded-4 glass border border-white-05">
        @foreach ($tabs as $tab)
            <a href="{{ route($tab['route']) }}" class="btn btn-sm rounded-3 px-2 py-2 fw-bold {{ request()->routeIs($tab['route']) ? 'bg-primary-gradient text-white shadow-glow' : 'text-muted hover-bg-white-10' }}">
                {{ $tab['label'] }}
                <span class="ms-1 opacity-50 small">{{ number_format($messagingTabCounts[$tab['key']] ?? 0) }}</span>
            </a>
        @endforeach
    </div>
</div>
