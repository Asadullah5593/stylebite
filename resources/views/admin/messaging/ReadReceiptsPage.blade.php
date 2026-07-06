@extends('admin.layouts.app')

@section('content')
<div class="messaging-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold">Messaging</span>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">Read Receipts</span>
    </nav>

    <div class="mb-4">
        <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">Messaging</h1>
        <p class="text-muted small mb-0">Per-message read tracking with reader and conversation context</p>
    </div>

    @include('admin.messaging.partials.tabs')

    <form method="GET" action="{{ route('admin.messaging.reads') }}" class="glass rounded-4 p-3 d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="position-relative flex-grow-1" style="min-width: 250px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control ps-5 border-0 bg-dark-soft rounded-3" placeholder="Search message, conversation, reader...">
        </div>

        <button class="btn btn-outline-dynamic rounded-3 px-3" type="submit"><i class="bi bi-funnel me-2"></i>Filter</button>
        <a href="{{ route('admin.messaging.reads') }}" class="btn btn-outline-dynamic rounded-3 px-3"><i class="bi bi-arrow-clockwise me-2"></i>Reset</a>
    </form>

    <div class="glass rounded-4 overflow-hidden border-white-05">
        <div class="table-responsive scrollbar-hidden">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-white-05">
                    <tr>
                        <th class="ps-4 text-muted small fw-bold text-uppercase py-3">Reader</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Conversation</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Message</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Sender</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Read At</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reads as $read)
                        <tr class="border-white-05">
                            <td class="ps-4">
                                @if ($read->user)
                                    <a href="{{ route('admin.users.show', $read->user) }}" class="text-decoration-none d-flex align-items-center gap-3">
                                        @if ($read->user->avatar_url)
                                            <img src="{{ $read->user->avatar_url }}" alt="{{ $read->user->username }}" class="rounded-circle border border-white-05" width="42" height="42" style="object-fit: cover;">
                                        @else
                                            <div class="rounded-circle border border-white-05 d-flex align-items-center justify-content-center text-muted" style="width:42px;height:42px;">
                                                <i class="bi bi-person"></i>
                                            </div>
                                        @endif
                                        <div>
                                            <div class="small fw-semibold">{{ $read->user->full_name ?: '@'.$read->user->username }}</div>
                                            <div class="text-muted extra-small">{{ '@'.$read->user->username }}</div>
                                        </div>
                                    </a>
                                @else
                                    <span class="text-muted small">Missing reader</span>
                                @endif
                            </td>
                            <td>
                                <div class="small fw-semibold">{{ $read->message?->conversation?->title ?: 'Conversation #'.($read->message?->conversation_id ?? '') }}</div>
                                <div class="text-muted extra-small">{{ str($read->message?->conversation?->type ?: 'unknown')->title() }}</div>
                            </td>
                            <td style="min-width: 280px;">
                                <div class="small fw-semibold">#{{ $read->message_id }}</div>
                                <div class="text-muted extra-small">{{ str($read->message?->body ?: 'No message body')->limit(70) }}</div>
                            </td>
                            <td>
                                @if ($read->message?->sender)
                                    <a href="{{ route('admin.users.show', $read->message->sender) }}" class="text-decoration-none">
                                        <div class="small fw-semibold">{{ $read->message->sender->full_name ?: '@'.$read->message->sender->username }}</div>
                                        <div class="text-muted extra-small">{{ '@'.$read->message->sender->username }}</div>
                                    </a>
                                @else
                                    <span class="text-muted small">Missing sender</span>
                                @endif
                            </td>
                            <td><span class="text-muted small">{{ $read->read_at?->format('M d, Y H:i') }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center py-5 text-muted">No read receipts found for the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 bg-white-05 border-top border-white-05 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="text-muted small">Showing <span class="text-emphasis-dynamic fw-bold">{{ $reads->firstItem() ?? 0 }}-{{ $reads->lastItem() ?? 0 }}</span> of <span class="text-emphasis-dynamic fw-bold">{{ number_format($reads->total()) }}</span> read receipts</div>
            {{ $reads->links() }}
        </div>
    </div>
</div>
@endsection
