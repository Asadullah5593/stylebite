@extends('admin.layouts.app')

@section('content')
<div class="moderation-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold">Moderation</span>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">Reports</span>
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

    <form method="GET" action="{{ route('admin.moderation.reports') }}" class="glass rounded-4 p-3 d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="position-relative flex-grow-1" style="min-width: 250px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control ps-5 border-0 bg-dark-soft rounded-3" placeholder="Search reports, notes, reporter...">
        </div>

        <select name="status" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Status</option>
            @foreach (['open' => 'Open', 'under_review' => 'Under Review', 'resolved' => 'Resolved', 'rejected' => 'Rejected'] as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <select name="reason" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Reasons</option>
            @foreach (['spam','harassment','hate','nudity','violence','copyright','fake','other'] as $reason)
                <option value="{{ $reason }}" @selected(request('reason') === $reason)>{{ str($reason)->title() }}</option>
            @endforeach
        </select>

        <select name="target_type" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Targets</option>
            @foreach (['user','post','comment','reply','message','contest'] as $type)
                <option value="{{ $type }}" @selected(request('target_type') === $type)>{{ str($type)->title() }}</option>
            @endforeach
        </select>

        <button class="btn btn-outline-dynamic rounded-3 px-3" type="submit"><i class="bi bi-funnel me-2"></i>Filter</button>
        <a href="{{ route('admin.moderation.reports') }}" class="btn btn-outline-dynamic rounded-3 px-3"><i class="bi bi-arrow-clockwise me-2"></i>Reset</a>
    </form>

    <div class="glass rounded-4 overflow-hidden border-white-05">
        <div class="table-responsive scrollbar-hidden">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-white-05">
                    <tr>
                        <th class="ps-4 text-muted small fw-bold text-uppercase py-3">Report</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Reporter</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Target</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Status</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Reviewer</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Created</th>
                        <th class="text-muted small fw-bold text-uppercase py-3 pe-4 text-end">Workflow</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reports as $report)
                        @php
                            $target = $report->targetModel;
                            $previewFrameId = 'report-preview-'.$report->id;
                            $reporterName = $report->reporter?->full_name ?: '@'.$report->reporter?->username;
                            $reviewerName = $report->reviewer?->full_name ?: ($report->reviewer?->username ? '@'.$report->reviewer->username : 'Unassigned');
                            $statusClass = match ($report->status) {
                                'resolved' => 'bg-success-soft text-success',
                                'rejected' => 'bg-secondary-soft text-muted',
                                'under_review' => 'bg-info-soft text-info',
                                default => 'bg-warning-soft text-warning',
                            };
                            $targetTitle = match ($report->target_type) {
                                'user' => $target?->full_name ?: ($target?->username ? '@'.$target->username : 'Missing user'),
                                'post' => str($target?->caption ?: 'Untitled post')->limit(52),
                                'comment' => str($target?->body ?: 'Missing comment')->limit(52),
                                'reply' => str($target?->body ?: 'Missing reply')->limit(52),
                                'contest' => str($target?->title ?: 'Missing contest')->limit(52),
                                'message' => str($target?->body ?: 'Missing message')->limit(52),
                                default => 'Unavailable',
                            };
                            $targetMeta = match ($report->target_type) {
                                'user' => $target?->email ?: ($target?->status ? 'Status: '.str($target->status)->title() : 'Missing record'),
                                'post' => $target?->user ? '@'.$target->user->username.' | '.str($target->status)->replace('_', ' ')->title() : 'Missing record',
                                'comment' => $target?->user ? '@'.$target->user->username.' | '.str($target->status)->title() : 'Missing record',
                                'reply' => $target?->user ? '@'.$target->user->username.' | '.str($target->status)->title() : 'Missing record',
                                'contest' => $target?->creator ? '@'.$target->creator->username.' | '.str($target->status)->title() : 'Missing record',
                                'message' => $target?->sender ? '@'.$target->sender->username.' | '.($target->conversation?->title ?: str($target->conversation?->type)->title().' chat') : 'Missing record',
                                default => 'Missing record',
                            };
                            $supportsAction = in_array($report->target_type, ['user', 'post', 'comment', 'reply', 'contest', 'message'], true) && $target;
                            $canPreview = in_array($report->target_type, ['post', 'comment', 'reply', 'contest', 'message'], true) && $target;
                        @endphp
                        <tr class="border-white-05">
                            <td class="ps-4">
                                <div class="d-flex align-items-start gap-2">
                                    <div>
                                        <div class="fw-bold small">#{{ $report->id }}</div>
                                        <div class="d-flex flex-wrap gap-2 mt-1">
                                            <span class="badge bg-warning-soft text-warning rounded-pill">{{ str($report->reason)->title() }}</span>
                                            <span class="badge bg-info-soft text-info rounded-pill">{{ str($report->target_type)->title() }}</span>
                                        </div>
                                        <div class="text-muted extra-small text-truncate mt-2" style="max-width: 280px;">{{ $report->description ?: 'No description provided by reporter.' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if ($report->reporter)
                                    <a href="{{ route('admin.users.show', $report->reporter) }}" class="text-decoration-none">
                                        <div class="small fw-semibold">{{ $reporterName }}</div>
                                        <div class="text-muted extra-small">{{ '@'.$report->reporter->username }}</div>
                                    </a>
                                @else
                                    <span class="text-muted small">Deleted user</span>
                                @endif
                            </td>
                            <td>
                                <div class="fw-semibold small">{{ $targetTitle }}</div>
                                <div class="text-muted extra-small text-truncate" style="max-width: 250px;">{{ $targetMeta }}</div>
                                <div class="d-flex flex-wrap gap-2 mt-2">
                                    @if ($canPreview)
                                        <button class="btn btn-sm btn-outline-dynamic rounded-3" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $previewFrameId }}" aria-expanded="false">
                                            <i class="bi bi-eye me-1"></i>Preview
                                        </button>
                                    @endif
                                    @if ($report->target_type === 'user' && $target)
                                        <a href="{{ route('admin.users.show', $target) }}" class="btn btn-sm btn-outline-dynamic rounded-3">
                                            <i class="bi bi-person me-1"></i>User
                                        </a>
                                    @elseif ($report->target_type === 'post' && $target)
                                        <a href="{{ route('admin.posts.show', $target) }}" class="btn btn-sm btn-outline-dynamic rounded-3">
                                            <i class="bi bi-file-earmark-text me-1"></i>Post
                                        </a>
                                    @elseif (in_array($report->target_type, ['comment', 'reply'], true) && $target?->user)
                                        <a href="{{ route('admin.users.show', $target->user) }}" class="btn btn-sm btn-outline-dynamic rounded-3">
                                            <i class="bi bi-person me-1"></i>Author
                                        </a>
                                    @elseif ($report->target_type === 'contest' && $target?->creator)
                                        <a href="{{ route('admin.users.show', $target->creator) }}" class="btn btn-sm btn-outline-dynamic rounded-3">
                                            <i class="bi bi-person me-1"></i>Creator
                                        </a>
                                    @elseif ($report->target_type === 'message' && $target?->sender)
                                        <a href="{{ route('admin.users.show', $target->sender) }}" class="btn btn-sm btn-outline-dynamic rounded-3">
                                            <i class="bi bi-person me-1"></i>Sender
                                        </a>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <span class="badge {{ $statusClass }} rounded-pill">{{ str($report->status)->replace('_', ' ')->title() }}</span>
                                @if ($report->status === 'open')
                                    <form method="POST" action="{{ route('admin.moderation.reports.assign', $report) }}" class="mt-2">
                                        @csrf
                                        @method('PATCH')
                                        <button class="btn btn-sm btn-outline-info rounded-3" type="submit">
                                            <i class="bi bi-person-check me-1"></i>Assign To Me
                                        </button>
                                    </form>
                                @endif
                            </td>
                            <td>
                                @if ($report->reviewer)
                                    <a href="{{ route('admin.users.show', $report->reviewer) }}" class="text-decoration-none">
                                        <div class="small fw-semibold">{{ $reviewerName }}</div>
                                        <div class="text-muted extra-small">{{ '@'.$report->reviewer->username }}</div>
                                    </a>
                                @else
                                    <span class="text-muted small">Unassigned</span>
                                @endif
                            </td>
                            <td>
                                <div class="text-muted small">{{ $report->created_at?->format('M d, Y') ?? '-' }}</div>
                                <div class="text-muted extra-small">{{ $report->created_at?->format('H:i') ?? '-' }}</div>
                            </td>
                            <td class="pe-4">
                                <div class="d-grid gap-2" style="min-width: 250px;">
                                    <form method="POST" action="{{ route('admin.moderation.reports.update', $report) }}" class="d-grid gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="report_id" value="{{ $report->id }}">
                                        <select name="status" class="form-select form-select-sm border-0 bg-dark-soft rounded-3 text-muted">
                                            @foreach (['open' => 'Open', 'under_review' => 'Under Review', 'resolved' => 'Resolved', 'rejected' => 'Rejected'] as $value => $label)
                                                <option value="{{ $value }}" @selected((int) old('report_id') === $report->id ? old('status', $report->status) === $value : $report->status === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <textarea name="resolution_notes" rows="2" class="form-control form-control-sm border-0 bg-dark-soft rounded-3" placeholder="Add reviewer notes...">{{ (int) old('report_id') === $report->id ? old('resolution_notes', $report->resolution_notes) : $report->resolution_notes }}</textarea>
                                        <button class="btn btn-sm btn-outline-dynamic rounded-3" type="submit">
                                            <i class="bi bi-save me-2"></i>Update Report
                                        </button>
                                    </form>

                                    @if ($supportsAction)
                                        <form method="POST" action="{{ route('admin.moderation.reports.target.update', $report) }}" class="d-grid gap-2">
                                            @csrf
                                            @method('PATCH')
                                            <select name="action" class="form-select form-select-sm border-0 bg-dark-soft rounded-3 text-muted">
                                                @foreach ([
                                                    'user' => ['ban' => 'Ban user', 'restore' => 'Restore user'],
                                                    'post' => ['hide' => 'Hide post', 'restrict' => 'Restrict post', 'ban' => 'Remove post', 'restore' => 'Restore post'],
                                                    'comment' => ['hide' => 'Hide comment', 'restrict' => 'Restrict comment', 'restore' => 'Restore comment'],
                                                    'reply' => ['hide' => 'Hide reply', 'restrict' => 'Restrict reply', 'restore' => 'Restore reply'],
                                                    'contest' => ['hide' => 'Archive contest', 'restrict' => 'Restrict contest', 'ban' => 'Cancel contest', 'restore' => 'Restore contest'],
                                                    'message' => ['hide' => 'Hide message', 'restrict' => 'Restrict message', 'ban' => 'Delete message', 'restore' => 'Restore message'],
                                                ][$report->target_type] as $value => $label)
                                                    <option value="{{ $value }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            <button class="btn btn-sm btn-outline-warning rounded-3" type="submit">
                                                <i class="bi bi-shield-check me-2"></i>Apply Target Action
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @if ($canPreview)
                            <tr class="border-white-05">
                                <td colspan="7" class="px-4 py-0">
                                    <div class="collapse" id="{{ $previewFrameId }}">
                                        <div class="glass rounded-4 p-4 my-3 border border-white-05">
                                            <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                                                <h5 class="mb-0">{{ str($report->target_type)->title() }} Preview</h5>
                                                <button class="btn btn-sm btn-outline-dynamic rounded-3" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $previewFrameId }}">
                                                    <i class="bi bi-x-lg me-1"></i>Close
                                                </button>
                                            </div>
                                            @if ($report->target_type === 'contest')
                                                <div class="row g-4 align-items-start">
                                                    <div class="col-lg-5">
                                                        @if ($target?->cover_image_url)
                                                            <img src="{{ $target->cover_image_url }}" alt="{{ $target->title }}" class="img-fluid rounded-3 w-100" style="max-height: 280px; object-fit: cover;">
                                                        @else
                                                            <div class="rounded-3 bg-white-05 d-flex align-items-center justify-content-center text-muted" style="height: 220px;">No contest image</div>
                                                        @endif
                                                    </div>
                                                    <div class="col-lg-7">
                                                        <div class="fw-bold mb-1">{{ $target?->title }}</div>
                                                        <div class="text-muted small mb-2">{{ $target?->subtitle ?: 'No subtitle' }}</div>
                                                        <div class="text-muted mb-3">{{ $target?->description ?: 'No contest description available.' }}</div>
                                                        <div class="row g-3 small">
                                                            <div class="col-md-6"><span class="text-muted d-block">Creator</span>{{ $target?->creator?->full_name ?: '@'.$target?->creator?->username }}</div>
                                                            <div class="col-md-6"><span class="text-muted d-block">Status</span>{{ str($target?->status)->title() }}</div>
                                                            <div class="col-md-6"><span class="text-muted d-block">Participants</span>{{ number_format($target?->participant_count ?? 0) }}</div>
                                                            <div class="col-md-6"><span class="text-muted d-block">Submissions</span>{{ number_format($target?->submission_count ?? 0) }}</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @elseif ($report->target_type === 'message')
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
                                            @else
                                                <div class="fw-bold mb-2">{{ $targetTitle }}</div>
                                                <div class="text-muted">{{ match ($report->target_type) {
                                                    'post' => $target?->caption ?: 'No post caption available.',
                                                    'comment' => $target?->body ?: 'No comment text available.',
                                                    'reply' => $target?->body ?: 'No reply text available.',
                                                    default => 'Preview unavailable.',
                                                } }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="7" class="text-center py-5 text-muted">No reports found for the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 bg-white-05 border-top border-white-05 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="text-muted small">
                Showing <span class="text-emphasis-dynamic fw-bold">{{ $reports->firstItem() ?? 0 }}-{{ $reports->lastItem() ?? 0 }}</span>
                of <span class="text-emphasis-dynamic fw-bold">{{ number_format($reports->total()) }}</span> reports
            </div>
            {{ $reports->links() }}
        </div>
    </div>
</div>
@endsection
