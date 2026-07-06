@extends('admin.layouts.app')

@section('content')
<div class="moderation-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold">Moderation</span>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">Moderation Actions</span>
    </nav>

    <div class="mb-4">
        <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">Moderation</h1>
        <p class="text-muted small mb-0">Reports queue and moderator actions</p>
    </div>

    @if (session('status'))
        <div class="glass rounded-4 p-3 mb-4 border border-primary-soft bg-primary-soft-opaque">
            <i class="bi bi-check-circle me-2 text-success"></i>{{ session('status') }}
        </div>
    @endif

    @include('admin.moderation.partials.tabs')

    <form method="GET" action="{{ route('admin.moderation.actions') }}" class="glass rounded-4 p-3 d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="position-relative flex-grow-1" style="min-width: 250px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control ps-5 border-0 bg-dark-soft rounded-3" placeholder="Search actions, moderator, reason...">
        </div>

        <select name="action" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Actions</option>
            @foreach (['warn','hide','remove','ban','unban','restrict','restore'] as $action)
                <option value="{{ $action }}" @selected(request('action') === $action)>{{ str($action)->title() }}</option>
            @endforeach
        </select>

        <button class="btn btn-outline-dynamic rounded-3 px-3" type="submit"><i class="bi bi-funnel me-2"></i>Filter</button>
        <a href="{{ route('admin.moderation.actions') }}" class="btn btn-outline-dynamic rounded-3 px-3"><i class="bi bi-arrow-clockwise me-2"></i>Reset</a>
    </form>

    <div class="glass rounded-4 overflow-hidden border-white-05">
        <div class="table-responsive scrollbar-hidden">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-white-05">
                    <tr>
                        <th class="ps-4 text-muted small fw-bold text-uppercase py-3">Action</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Moderator</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Target</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Reason</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Expires</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Created</th>
                        <th class="text-muted small fw-bold text-uppercase py-3 pe-4 text-end">Controls</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($actions as $item)
                        @php
                            $target = $item->targetModel;
                            $targetTitle = match ($item->target_type) {
                                'user' => $target?->full_name ?: ($target?->username ? '@'.$target->username : 'Missing user'),
                                'post' => str($target?->caption ?: 'Missing post')->limit(42),
                                'comment' => str($target?->body ?: 'Missing comment')->limit(42),
                                'reply' => str($target?->body ?: 'Missing reply')->limit(42),
                                'contest' => str($target?->title ?: 'Missing contest')->limit(42),
                                'message' => str($target?->body ?: 'Missing message')->limit(42),
                                default => 'Target #'.$item->target_id,
                            };
                            $previewFrameId = 'moderation-action-preview-'.$item->id;
                            $canPreview = $item->target_type === 'message' && $target;
                        @endphp
                        <tr class="border-white-05">
                            <td class="ps-4">
                                <div class="fw-bold small">#{{ $item->id }}</div>
                                <div class="mt-1">
                                    <span class="badge bg-warning-soft text-warning rounded-pill">{{ str($item->action)->title() }}</span>
                                    <span class="badge bg-info-soft text-info rounded-pill">{{ str($item->target_type)->title() }}</span>
                                </div>
                            </td>
                            <td>
                                @if ($item->moderator)
                                    <a href="{{ route('admin.users.show', $item->moderator) }}" class="text-decoration-none">
                                        <div class="small fw-semibold">{{ $item->moderator->full_name ?: '@'.$item->moderator->username }}</div>
                                        <div class="text-muted extra-small">{{ '@'.$item->moderator->username }}</div>
                                    </a>
                                @else
                                    <span class="text-muted small">Deleted moderator</span>
                                @endif
                            </td>
                            <td>
                                <div class="fw-semibold small">{{ $targetTitle }}</div>
                                <div class="text-muted extra-small">#{{ $item->target_id }}</div>
                                <div class="d-flex flex-wrap gap-2 mt-2">
                                    @if ($item->target_type === 'user' && $target)
                                        <a href="{{ route('admin.users.show', $target) }}" class="btn btn-sm btn-outline-dynamic rounded-3">
                                            <i class="bi bi-person me-1"></i>User
                                        </a>
                                    @elseif ($item->target_type === 'post' && $target)
                                        <a href="{{ route('admin.posts.show', $target) }}" class="btn btn-sm btn-outline-dynamic rounded-3">
                                            <i class="bi bi-file-earmark-text me-1"></i>Post
                                        </a>
                                    @elseif (in_array($item->target_type, ['comment', 'reply'], true) && $target?->user)
                                        <a href="{{ route('admin.users.show', $target->user) }}" class="btn btn-sm btn-outline-dynamic rounded-3">
                                            <i class="bi bi-person me-1"></i>Author
                                        </a>
                                    @elseif ($item->target_type === 'contest' && $target?->creator)
                                        <a href="{{ route('admin.users.show', $target->creator) }}" class="btn btn-sm btn-outline-dynamic rounded-3">
                                            <i class="bi bi-person me-1"></i>Creator
                                        </a>
                                    @elseif ($item->target_type === 'message' && $target?->sender)
                                        <a href="{{ route('admin.users.show', $target->sender) }}" class="btn btn-sm btn-outline-dynamic rounded-3">
                                            <i class="bi bi-person me-1"></i>Sender
                                        </a>
                                        <button class="btn btn-sm btn-outline-dynamic rounded-3" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $previewFrameId }}" aria-expanded="false">
                                            <i class="bi bi-eye me-1"></i>Preview
                                        </button>
                                    @endif
                                </div>
                            </td>
                            <td><span class="text-muted small text-truncate d-inline-block" style="max-width: 260px;">{{ $item->reason ?: '-' }}</span></td>
                            <td>
                                <span class="text-muted small">{{ $item->expires_at?->format('M d, Y H:i') ?? 'No expiry' }}</span>
                                @if ($item->expires_at && $item->expires_at->isPast())
                                    <div class="text-warning extra-small mt-1">Expired</div>
                                @endif
                            </td>
                            <td>
                                <div class="text-muted small">{{ $item->created_at?->format('M d, Y') ?? '-' }}</div>
                                <div class="text-muted extra-small">{{ $item->created_at?->format('H:i') ?? '-' }}</div>
                            </td>
                            <td class="pe-4">
                                @if (in_array($item->action, \App\Http\Controllers\Admin\ModerationController::actionTypesWithExpiry(), true))
                                    <form method="POST" action="{{ route('admin.moderation.actions.expiry', $item) }}" class="d-grid gap-2" style="min-width: 190px;">
                                        @csrf
                                        @method('PATCH')
                                        <input type="datetime-local" name="expires_at" value="{{ $item->expires_at?->format('Y-m-d\TH:i') }}" class="form-control form-control-sm border-0 bg-dark-soft rounded-3 text-muted">
                                        <button class="btn btn-sm btn-outline-dynamic rounded-3" type="submit">
                                            <i class="bi bi-clock-history me-1"></i>Save Expiry
                                        </button>
                                    </form>
                                @else
                                    <span class="text-muted extra-small">No expiry control for this action.</span>
                                @endif
                            </td>
                        </tr>
                        @if ($canPreview)
                            <tr class="border-white-05">
                                <td colspan="7" class="px-4 py-0">
                                    <div class="collapse" id="{{ $previewFrameId }}">
                                        <div class="glass rounded-4 p-4 my-3 border border-white-05">
                                            <div class="small text-muted mb-2">Conversation: {{ $target?->conversation?->title ?: str($target?->conversation?->type)->title().' chat' }}</div>
                                            <div class="glass rounded-4 p-3 border border-white-05 mb-3">
                                                <div class="fw-semibold mb-2">{{ $target?->sender?->full_name ?: '@'.$target?->sender?->username }}</div>
                                                <div class="text-muted">{{ $target?->body ?: 'No message text available.' }}</div>
                                            </div>
                                            @if ($target?->attachments?->isNotEmpty())
                                                <div class="row g-3">
                                                    @foreach ($target->attachments as $attachment)
                                                        <div class="col-md-4">
                                                            <div class="glass rounded-4 p-3 border border-white-05 h-100">
                                                                <div class="fw-semibold small mb-2">{{ str($attachment->media_type)->title() }}</div>
                                                                @if ($attachment->thumbnail_url || $attachment->file_url)
                                                                    <img src="{{ $attachment->thumbnail_url ?: $attachment->file_url }}" alt="Attachment preview" class="img-fluid rounded-3 mb-2 w-100" style="max-height: 180px; object-fit: cover;">
                                                                @endif
                                                                <div class="text-muted extra-small">{{ $attachment->mime_type ?: 'Unknown type' }}</div>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="7" class="text-center py-5 text-muted">No moderation actions found for the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 bg-white-05 border-top border-white-05 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="text-muted small">
                Showing <span class="text-emphasis-dynamic fw-bold">{{ $actions->firstItem() ?? 0 }}-{{ $actions->lastItem() ?? 0 }}</span>
                of <span class="text-emphasis-dynamic fw-bold">{{ number_format($actions->total()) }}</span> actions
            </div>
            {{ $actions->links() }}
        </div>
    </div>
</div>
@endsection
