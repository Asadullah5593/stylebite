@extends('admin.layouts.app')

@section('content')
@php
    $statusClass = match ($ticket->status) {
        'open' => 'bg-danger-soft text-danger',
        'in_progress' => 'bg-info-soft text-info',
        'waiting_on_user' => 'bg-warning-soft text-warning',
        'resolved' => 'bg-success-soft text-success',
        default => 'bg-secondary-soft text-muted',
    };
@endphp
<div class="users-page">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <a href="{{ route('admin.support.index') }}" class="text-decoration-none text-reset fw-bold">Support Tickets</a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">{{ $ticket->reference }}</span>
    </nav>

    <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-4">
        <div>
            <h1 class="h3 fw-extrabold mb-1" style="letter-spacing: -0.03em;">{{ $ticket->subject }}</h1>
            <p class="text-muted small mb-0">
                {{ $ticket->reference }} · {{ $ticket->categoryLabel() }} ·
                opened {{ $ticket->created_at?->diffForHumans() }}
                @if ($ticket->user)
                    by <a href="{{ route('admin.users.show', $ticket->user) }}" class="text-decoration-none">{{ '@'.$ticket->user->username }}</a>
                @endif
            </p>
        </div>
        <span class="badge {{ $statusClass }} rounded-pill px-3 py-2 fw-bold text-uppercase">{{ $ticket->statusLabel() }}</span>
    </div>

    @if (session('status'))
        <div class="glass rounded-4 p-3 mb-4 border border-primary-soft bg-primary-soft-opaque">
            <i class="bi bi-check-circle me-2 text-success"></i>{{ session('status') }}
        </div>
    @endif

    <div class="row g-4">
        {{-- Conversation --}}
        <div class="col-12 col-lg-8">
            <div class="glass rounded-4 p-4 border border-white-05 mb-4">
                <h2 class="h6 fw-bold mb-3">Conversation</h2>

                @forelse ($ticket->messages as $message)
                    @php
                        $isStaff = $message->author_type === 'staff';
                        $isSystem = $message->author_type === 'system';
                    @endphp

                    @if ($isSystem)
                        <div class="text-center my-3">
                            <span class="badge bg-dark-soft text-muted rounded-pill px-3 py-2 extra-small">
                                <i class="bi bi-info-circle me-1"></i>{{ $message->body }}
                                <span class="opacity-50 ms-1">{{ $message->created_at?->format('M d, H:i') }}</span>
                            </span>
                        </div>
                    @else
                        <div class="d-flex mb-3 {{ $isStaff ? 'justify-content-end' : '' }}">
                            <div class="rounded-4 p-3 {{ $message->is_internal ? 'border border-warning bg-warning-soft' : ($isStaff ? 'bg-primary-soft' : 'bg-dark-soft') }}"
                                 style="max-width: 84%;">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="extra-small fw-bold text-uppercase {{ $isStaff ? 'text-primary' : 'text-muted' }}">
                                        {{ $message->is_internal ? 'Internal note' : ($isStaff ? 'Support' : 'User') }}
                                    </span>
                                    @if ($message->author)
                                        <span class="text-muted extra-small">{{ $message->author->full_name ?: $message->author->username }}</span>
                                    @endif
                                    <span class="text-muted extra-small">{{ $message->created_at?->format('M d, H:i') }}</span>
                                </div>

                                <div class="small" style="white-space: pre-wrap;">{{ $message->body }}</div>

                                @if ($message->attachments->isNotEmpty())
                                    <div class="d-flex flex-wrap gap-2 mt-2">
                                        @foreach ($message->attachments as $attachment)
                                            <a href="{{ stylebite_asset_url($attachment->file_path) }}" target="_blank" rel="noopener"
                                               class="badge bg-dark-soft text-info rounded-pill px-3 py-2 text-decoration-none">
                                                <i class="bi bi-paperclip me-1"></i>{{ Str::limit($attachment->original_file_name ?: 'attachment', 28) }}
                                            </a>
                                        @endforeach
                                    </div>
                                @endif

                                @if ($message->is_internal)
                                    <div class="text-warning extra-small mt-2"><i class="bi bi-eye-slash me-1"></i>Not visible to the user</div>
                                @endif
                            </div>
                        </div>
                    @endif
                @empty
                    <p class="text-muted small mb-0">No messages yet.</p>
                @endforelse
            </div>

            @can('tickets.reply')
                <div class="glass rounded-4 p-4 border border-white-05">
                    <h2 class="h6 fw-bold mb-3">Reply</h2>
                    <form method="POST" action="{{ route('admin.support.reply', $ticket) }}">
                        @csrf
                        <textarea name="body" rows="5" maxlength="5000"
                                  class="form-control bg-dark-soft border-0 rounded-3 mb-3 @error('body') is-invalid @enderror"
                                  placeholder="Write your reply to the user...">{{ old('body') }}</textarea>
                        @error('body')<div class="invalid-feedback d-block mb-2">{{ $message }}</div>@enderror

                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="isInternal" name="is_internal" value="1">
                                <label class="form-check-label small" for="isInternal">
                                    Internal note — visible to staff only, does not notify the user
                                </label>
                            </div>
                            <button class="btn bg-primary-gradient text-white fw-bold rounded-3 px-4 shadow-glow border-0">
                                <i class="bi bi-send me-2"></i>Send
                            </button>
                        </div>
                    </form>
                    <div class="form-text text-muted extra-small mt-2">
                        A reply moves the ticket to <span class="fw-bold">Waiting on user</span> and sends them a push notification.
                    </div>
                </div>
            @endcan
        </div>

        {{-- Side panel --}}
        <div class="col-12 col-lg-4">
            @can('tickets.manage')
                <div class="glass rounded-4 p-4 border border-white-05 mb-4">
                    <h2 class="h6 fw-bold mb-3">Manage</h2>
                    <form method="POST" action="{{ route('admin.support.update', $ticket) }}">
                        @csrf
                        @method('PATCH')

                        <label class="form-label small fw-bold text-muted">Status</label>
                        <select name="status" class="form-select bg-dark-soft border-0 rounded-3 mb-3">
                            @foreach (\App\Models\SupportTicket::STATUSES as $value => $label)
                                <option value="{{ $value }}" @selected($ticket->status === $value)>{{ $label }}</option>
                            @endforeach
                        </select>

                        <label class="form-label small fw-bold text-muted">Priority</label>
                        <select name="priority" class="form-select bg-dark-soft border-0 rounded-3 mb-3">
                            @foreach (\App\Models\SupportTicket::PRIORITIES as $value => $label)
                                <option value="{{ $value }}" @selected($ticket->priority === $value)>{{ $label }}</option>
                            @endforeach
                        </select>

                        <label class="form-label small fw-bold text-muted">Assignee</label>
                        <select name="assigned_to_user_id" class="form-select bg-dark-soft border-0 rounded-3 mb-3">
                            <option value="">Unassigned</option>
                            @foreach ($staffOptions as $staff)
                                <option value="{{ $staff->id }}" @selected($ticket->assigned_to_user_id === $staff->id)>
                                    {{ $staff->full_name ?: $staff->username }}
                                </option>
                            @endforeach
                        </select>

                        <button class="btn btn-outline-dynamic rounded-3 w-100"><i class="bi bi-check2 me-2"></i>Save</button>
                    </form>
                </div>
            @endcan

            <div class="glass rounded-4 p-4 border border-white-05">
                <h2 class="h6 fw-bold mb-3">Details</h2>
                <dl class="mb-0 small">
                    @foreach ([
                        'Reference' => $ticket->reference,
                        'Category' => $ticket->categoryLabel(),
                        'Replies' => $ticket->messages_count,
                        'Last reply' => ($ticket->last_reply_at?->diffForHumans() ?? '-').' ('.$ticket->last_reply_by.')',
                        'Resolved' => $ticket->resolved_at?->format('M d, Y H:i') ?? '—',
                        'Closed' => $ticket->closed_at?->format('M d, Y H:i') ?? '—',
                    ] as $label => $value)
                        <div class="d-flex justify-content-between gap-3 py-2 border-bottom border-white-05">
                            <dt class="text-muted fw-normal">{{ $label }}</dt>
                            <dd class="mb-0 fw-semibold text-end">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>

                @if ($ticket->app_version || $ticket->platform || $ticket->device_model || $ticket->os_version)
                    <h2 class="h6 fw-bold mt-4 mb-2">Device</h2>
                    <dl class="mb-0 small">
                        @foreach (array_filter([
                            'App version' => $ticket->app_version,
                            'Platform' => $ticket->platform,
                            'Device' => $ticket->device_model,
                            'OS' => $ticket->os_version,
                        ]) as $label => $value)
                            <div class="d-flex justify-content-between gap-3 py-2 border-bottom border-white-05">
                                <dt class="text-muted fw-normal">{{ $label }}</dt>
                                <dd class="mb-0 fw-semibold text-end">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                    <div class="form-text text-muted extra-small">Sent automatically by the app — useful for reproducing bugs.</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
