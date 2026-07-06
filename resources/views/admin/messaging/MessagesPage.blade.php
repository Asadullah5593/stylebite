@extends('admin.layouts.app')

@section('content')
<div class="messaging-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold">Messaging</span>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">Messages</span>
    </nav>

    <div class="mb-4">
        <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">Messaging</h1>
        <p class="text-muted small mb-0">Message audit with replies, media counts, and delivery context</p>
    </div>

    @include('admin.messaging.partials.tabs')

    @if (session('status'))
        <div class="alert alert-success rounded-3 border-0 mb-4">{{ session('status') }}</div>
    @endif

    <form method="GET" action="{{ route('admin.messaging.messages') }}" class="glass rounded-4 p-3 d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="position-relative flex-grow-1" style="min-width: 250px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control ps-5 border-0 bg-dark-soft rounded-3" placeholder="Search message, sender, conversation...">
        </div>

        <select name="message_type" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Types</option>
            @foreach (['text' => 'Text', 'image' => 'Image', 'video' => 'Video', 'system' => 'System'] as $value => $label)
                <option value="{{ $value }}" @selected(request('message_type') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <button class="btn btn-outline-dynamic rounded-3 px-3" type="submit"><i class="bi bi-funnel me-2"></i>Filter</button>
        <a href="{{ route('admin.messaging.messages') }}" class="btn btn-outline-dynamic rounded-3 px-3"><i class="bi bi-arrow-clockwise me-2"></i>Reset</a>
    </form>

    <div class="glass rounded-4 overflow-hidden border-white-05">
        <div class="table-responsive scrollbar-hidden">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-white-05">
                    <tr>
                        <th class="ps-4 text-muted small fw-bold text-uppercase py-3">Message</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Conversation</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Sender</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Media / Reads</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">State</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Sent</th>
                        <th class="text-muted small fw-bold text-uppercase py-3 text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($messages as $message)
                        <tr class="border-white-05">
                            <td class="ps-4" style="min-width: 280px;">
                                <div class="small fw-semibold">#{{ $message->id }} · {{ str($message->message_type)->title() }}</div>
                                <div class="text-muted extra-small">{{ str($message->body ?: 'No text body')->limit(70) }}</div>
                                @if ($message->replyToMessage)
                                    <button class="btn btn-link btn-sm p-0 text-decoration-none mt-1" type="button" data-bs-toggle="modal" data-bs-target="#replyModal{{ $message->id }}">
                                        View replied message
                                    </button>
                                    <div class="modal fade" id="replyModal{{ $message->id }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content bg-dark border border-white-10">
                                                <div class="modal-header border-white-10">
                                                    <h5 class="modal-title">Reply context</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p class="mb-0 text-white">{{ $message->replyToMessage->body ?: 'No body' }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </td>
                            <td>
                                <div class="small fw-semibold">{{ $message->conversation?->title ?: 'Conversation #'.$message->conversation_id }}</div>
                                <div class="text-muted extra-small">{{ str($message->conversation?->type ?: 'unknown')->title() }}</div>
                            </td>
                            <td>
                                @if ($message->sender)
                                    <a href="{{ route('admin.users.show', $message->sender) }}" class="text-decoration-none">
                                        <div class="small fw-semibold">{{ $message->sender->full_name ?: '@'.$message->sender->username }}</div>
                                        <div class="text-muted extra-small">{{ '@'.$message->sender->username }}</div>
                                    </a>
                                @else
                                    <span class="text-muted small">Missing sender</span>
                                @endif
                            </td>
                            <td>
                                <div class="small">{{ number_format($message->attachments_count) }} attachments</div>
                                <div class="text-muted extra-small">{{ number_format($message->reads_count) }} reads</div>
                            </td>
                            <td>
                                @if ($message->is_deleted)
                                    <span class="badge bg-danger-soft text-danger rounded-pill">Deleted</span>
                                @elseif ($message->is_edited)
                                    <span class="badge bg-warning-soft text-warning rounded-pill">Edited</span>
                                @else
                                    <span class="badge bg-success-soft text-success rounded-pill">Live</span>
                                @endif
                            </td>
                            <td><span class="text-muted small">{{ $message->sent_at?->format('M d, Y H:i') }}</span></td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('admin.messaging.messages.update', $message) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="action" value="{{ $message->is_deleted ? 'restore' : 'delete' }}">
                                    <button type="submit" class="btn btn-sm {{ $message->is_deleted ? 'btn-outline-success' : 'btn-outline-danger' }} rounded-3 px-3">
                                        <i class="bi {{ $message->is_deleted ? 'bi-arrow-counterclockwise' : 'bi-trash3' }} me-1"></i>{{ $message->is_deleted ? 'Restore' : 'Delete' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center py-5 text-muted">No messages found for the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 bg-white-05 border-top border-white-05 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="text-muted small">Showing <span class="text-emphasis-dynamic fw-bold">{{ $messages->firstItem() ?? 0 }}-{{ $messages->lastItem() ?? 0 }}</span> of <span class="text-emphasis-dynamic fw-bold">{{ number_format($messages->total()) }}</span> messages</div>
            {{ $messages->links() }}
        </div>
    </div>
</div>
@endsection
