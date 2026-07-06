@extends('admin.layouts.app')

@section('content')
<div class="messaging-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold">Messaging</span>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">Conversations</span>
    </nav>

    <div class="mb-4">
        <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">Messaging</h1>
        <p class="text-muted small mb-0">Conversation health, participants, and latest chat context</p>
    </div>

    @include('admin.messaging.partials.tabs')

    @if (session('status'))
        <div class="alert alert-success rounded-3 border-0 mb-4">{{ session('status') }}</div>
    @endif

    <form method="GET" action="{{ route('admin.messaging.conversations') }}" class="glass rounded-4 p-3 d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="position-relative flex-grow-1" style="min-width: 250px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control ps-5 border-0 bg-dark-soft rounded-3" placeholder="Search title, creator, members...">
        </div>

        <select name="type" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Types</option>
            @foreach (['direct' => 'Direct', 'group' => 'Group', 'support' => 'Support'] as $value => $label)
                <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <button class="btn btn-outline-dynamic rounded-3 px-3" type="submit"><i class="bi bi-funnel me-2"></i>Filter</button>
        <a href="{{ route('admin.messaging.conversations') }}" class="btn btn-outline-dynamic rounded-3 px-3"><i class="bi bi-arrow-clockwise me-2"></i>Reset</a>
    </form>

    <div class="glass rounded-4 overflow-hidden border-white-05">
        <div class="table-responsive scrollbar-hidden">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-white-05">
                    <tr>
                        <th class="ps-4 text-muted small fw-bold text-uppercase py-3">Conversation</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Creator</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Members</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Latest Message</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">State</th>
                        <th class="text-muted small fw-bold text-uppercase py-3 text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($conversations as $conversation)
                        <tr class="border-white-05">
                            <td class="ps-4">
                                <div class="small fw-semibold">{{ $conversation->title ?: 'Conversation #'.$conversation->id }}</div>
                                <div class="text-muted extra-small">{{ str($conversation->type)->title() }} · {{ number_format($conversation->messages_count) }} messages</div>
                            </td>
                            <td>
                                @if ($conversation->creator)
                                    <a href="{{ route('admin.users.show', $conversation->creator) }}" class="text-decoration-none">
                                        <div class="small fw-semibold">{{ $conversation->creator->full_name ?: '@'.$conversation->creator->username }}</div>
                                        <div class="text-muted extra-small">{{ '@'.$conversation->creator->username }}</div>
                                    </a>
                                @else
                                    <span class="text-muted small">No creator</span>
                                @endif
                            </td>
                            <td>
                                <div class="small">{{ number_format($conversation->members_count) }} participants</div>
                                <div class="d-flex flex-wrap gap-2 mt-2">
                                    @foreach ($conversation->members->take(3) as $member)
                                        @if ($member->user)
                                            <a href="{{ route('admin.users.show', $member->user) }}" class="text-decoration-none badge bg-white-05 text-muted rounded-pill px-2 py-1">
                                                {{ $member->user->full_name ?: '@'.$member->user->username }}
                                            </a>
                                        @endif
                                    @endforeach
                                </div>
                            </td>
                            <td style="min-width: 260px;">
                                @if ($conversation->lastMessage)
                                    <div class="small fw-semibold">{{ $conversation->lastMessage->sender?->full_name ?: ($conversation->lastMessage->sender?->username ? '@'.$conversation->lastMessage->sender->username : 'Removed account') }}</div>
                                    <div class="text-muted extra-small">{{ str($conversation->lastMessage->body ?: '['.$conversation->lastMessage->message_type.']')->limit(60) }}</div>
                                @else
                                    <span class="text-muted small">No messages yet</span>
                                @endif
                            </td>
                            <td>
                                @if ($conversation->messaging_stopped_at)
                                    <span class="badge bg-danger-soft text-danger rounded-pill">Stopped</span>
                                    <div class="text-muted extra-small mt-1">{{ $conversation->messaging_stopped_at?->format('M d, Y H:i') }}</div>
                                @else
                                    <span class="badge bg-success-soft text-success rounded-pill">Active</span>
                                    <div class="text-muted extra-small mt-1">{{ $conversation->last_message_at?->format('M d, Y H:i') ?? 'No activity' }}</div>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-2 flex-wrap justify-content-end">
                                    <a href="{{ route('admin.messaging.conversations.export', $conversation) }}" class="btn btn-sm btn-outline-dynamic rounded-3 px-3">
                                        <i class="bi bi-download me-1"></i>Export
                                    </a>
                                    <form method="POST" action="{{ route('admin.messaging.conversations.update', $conversation) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="action" value="{{ $conversation->messaging_stopped_at ? 'resume' : 'stop' }}">
                                        <button type="submit" class="btn btn-sm {{ $conversation->messaging_stopped_at ? 'btn-outline-success' : 'btn-outline-danger' }} rounded-3 px-3">
                                            <i class="bi {{ $conversation->messaging_stopped_at ? 'bi-play-circle' : 'bi-pause-circle' }} me-1"></i>{{ $conversation->messaging_stopped_at ? 'Resume' : 'Stop' }}
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center py-5 text-muted">No conversations found for the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 bg-white-05 border-top border-white-05 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="text-muted small">Showing <span class="text-emphasis-dynamic fw-bold">{{ $conversations->firstItem() ?? 0 }}-{{ $conversations->lastItem() ?? 0 }}</span> of <span class="text-emphasis-dynamic fw-bold">{{ number_format($conversations->total()) }}</span> conversations</div>
            {{ $conversations->links() }}
        </div>
    </div>
</div>
@endsection
