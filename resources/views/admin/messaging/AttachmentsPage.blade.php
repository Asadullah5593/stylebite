@extends('admin.layouts.app')

@section('content')
<div class="messaging-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold">Messaging</span>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">Attachments</span>
    </nav>

    <div class="mb-4">
        <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">Messaging</h1>
        <p class="text-muted small mb-0">Attachment audit with sender, message, and file preview context</p>
    </div>

    @include('admin.messaging.partials.tabs')

    @if (session('status'))
        <div class="alert alert-success rounded-3 border-0 mb-4">{{ session('status') }}</div>
    @endif

    <form method="GET" action="{{ route('admin.messaging.attachments') }}" class="glass rounded-4 p-3 d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="position-relative flex-grow-1" style="min-width: 250px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control ps-5 border-0 bg-dark-soft rounded-3" placeholder="Search file URL, mime, sender, message...">
        </div>

        <select name="media_type" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Types</option>
            @foreach (['image' => 'Image', 'video' => 'Video', 'file' => 'File'] as $value => $label)
                <option value="{{ $value }}" @selected(request('media_type') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <button class="btn btn-outline-dynamic rounded-3 px-3" type="submit"><i class="bi bi-funnel me-2"></i>Filter</button>
        <a href="{{ route('admin.messaging.attachments') }}" class="btn btn-outline-dynamic rounded-3 px-3"><i class="bi bi-arrow-clockwise me-2"></i>Reset</a>
    </form>

    <div class="glass rounded-4 overflow-hidden border-white-05">
        <div class="table-responsive scrollbar-hidden">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-white-05">
                    <tr>
                        <th class="ps-4 text-muted small fw-bold text-uppercase py-3">Attachment</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Message</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Sender</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Type</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Meta</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Created</th>
                        <th class="text-muted small fw-bold text-uppercase py-3 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($attachments as $attachment)
                        <tr class="border-white-05">
                            <td class="ps-4" style="min-width: 220px;">
                                <div class="d-flex align-items-center gap-3">
                                    @if ($attachment->media_type === 'image')
                                        <img src="{{ $attachment->thumbnail_url ?: $attachment->file_url }}" alt="attachment" class="rounded-3 border border-white-05" width="56" height="56" style="object-fit: cover;">
                                    @elseif ($attachment->media_type === 'video')
                                        <div class="rounded-3 border border-white-05 d-flex align-items-center justify-content-center text-muted" style="width:56px;height:56px;">
                                            <i class="bi bi-play-btn"></i>
                                        </div>
                                    @else
                                        <div class="rounded-3 border border-white-05 d-flex align-items-center justify-content-center text-muted" style="width:56px;height:56px;">
                                            <i class="bi bi-file-earmark"></i>
                                        </div>
                                    @endif
                                    <div>
                                        <div class="small fw-semibold">#{{ $attachment->id }}</div>
                                        <a href="{{ $attachment->file_url }}" target="_blank" class="text-muted extra-small text-decoration-none">{{ str($attachment->file_url)->limit(42) }}</a>
                                    </div>
                                </div>
                            </td>
                            <td style="min-width: 260px;">
                                <div class="small fw-semibold">{{ str($attachment->message?->body ?: 'Message #'.$attachment->message_id)->limit(60) }}</div>
                                <div class="text-muted extra-small">{{ $attachment->message?->conversation?->title ?: 'Conversation missing' }}</div>
                            </td>
                            <td>
                                @if ($attachment->message?->sender)
                                    <a href="{{ route('admin.users.show', $attachment->message->sender) }}" class="text-decoration-none">
                                        <div class="small fw-semibold">{{ $attachment->message->sender->full_name ?: '@'.$attachment->message->sender->username }}</div>
                                        <div class="text-muted extra-small">{{ '@'.$attachment->message->sender->username }}</div>
                                    </a>
                                @else
                                    <span class="text-muted small">Missing sender</span>
                                @endif
                            </td>
                            <td><span class="badge {{ $attachment->media_type === 'image' ? 'bg-info-soft text-info' : ($attachment->media_type === 'video' ? 'bg-warning-soft text-warning' : 'bg-secondary-soft text-muted') }} rounded-pill">{{ str($attachment->media_type)->title() }}</span></td>
                            <td>
                                <div class="small">{{ $attachment->mime_type ?: 'Unknown MIME' }}</div>
                                <div class="text-muted extra-small">
                                    {{ $attachment->size_bytes ? number_format($attachment->size_bytes / 1024, 1) . ' KB' : 'Size unknown' }}
                                    @if ($attachment->width && $attachment->height)
                                        · {{ $attachment->width }}x{{ $attachment->height }}
                                    @endif
                                    @if ($attachment->duration_seconds)
                                        · {{ $attachment->duration_seconds }}s
                                    @endif
                                </div>
                                @if ($attachment->upload)
                                    <div class="text-muted extra-small mt-1">Upload: {{ str($attachment->upload->upload_status)->replace('_', ' ')->title() }}</div>
                                @endif
                            </td>
                            <td><span class="text-muted small">{{ $attachment->created_at?->format('M d, Y H:i') }}</span></td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-2 flex-wrap justify-content-end">
                                    @if ($attachment->upload)
                                        <form method="POST" action="{{ route('admin.messaging.attachments.update', $attachment) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="action" value="{{ $attachment->upload->upload_status === 'failed' ? 'mark_ready' : 'mark_failed' }}">
                                            <button type="submit" class="btn btn-sm {{ $attachment->upload->upload_status === 'failed' ? 'btn-outline-success' : 'btn-outline-danger' }} rounded-3 px-3">
                                                <i class="bi {{ $attachment->upload->upload_status === 'failed' ? 'bi-check2-circle' : 'bi-shield-x' }} me-1"></i>{{ $attachment->upload->upload_status === 'failed' ? 'Mark Ready' : 'Mark Failed' }}
                                            </button>
                                        </form>
                                    @endif
                                    @if ($attachment->message)
                                        <a href="{{ route('admin.messaging.messages', ['q' => $attachment->message->id]) }}" class="btn btn-sm btn-outline-dynamic rounded-3 px-3">
                                            <i class="bi bi-chat-left-text me-1"></i>Message
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center py-5 text-muted">No attachments found for the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 bg-white-05 border-top border-white-05 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="text-muted small">Showing <span class="text-emphasis-dynamic fw-bold">{{ $attachments->firstItem() ?? 0 }}-{{ $attachments->lastItem() ?? 0 }}</span> of <span class="text-emphasis-dynamic fw-bold">{{ number_format($attachments->total()) }}</span> attachments</div>
            {{ $attachments->links() }}
        </div>
    </div>
</div>
@endsection
