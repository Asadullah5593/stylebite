@extends('admin.layouts.app')

@section('content')
<div class="activity-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold">Activity Logs</span>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">Audit Trail</span>
    </nav>

    <div class="mb-4">
        <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">Activity Logs</h1>
        <p class="text-muted small mb-0">Audit trail of admin, user, and system events</p>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-2 col-sm-6"><div class="glass rounded-4 p-3 h-100"><div class="text-muted small">Total Logs</div><div class="fs-4 fw-bold">{{ number_format($activityStats['total'] ?? 0) }}</div></div></div>
        <div class="col-md-2 col-sm-6"><div class="glass rounded-4 p-3 h-100"><div class="text-muted small">Admin Events</div><div class="fs-4 fw-bold">{{ number_format($activityStats['admins'] ?? 0) }}</div></div></div>
        <div class="col-md-3 col-sm-6"><div class="glass rounded-4 p-3 h-100"><div class="text-muted small">User Lifecycle</div><div class="fs-4 fw-bold">{{ number_format($activityStats['user_lifecycle'] ?? 0) }}</div></div></div>
        <div class="col-md-3 col-sm-6"><div class="glass rounded-4 p-3 h-100"><div class="text-muted small">Badge Events</div><div class="fs-4 fw-bold">{{ number_format($activityStats['badge_events'] ?? 0) }}</div></div></div>
        <div class="col-md-2 col-sm-6"><div class="glass rounded-4 p-3 h-100"><div class="text-muted small">Today</div><div class="fs-4 fw-bold">{{ number_format($activityStats['today'] ?? 0) }}</div></div></div>
    </div>

    <form method="GET" action="{{ route('admin.activity.activity_logs') }}" class="glass rounded-4 p-3 d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="position-relative flex-grow-1" style="min-width: 250px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control ps-5 border-0 bg-dark-soft rounded-3" placeholder="Search event, entity, IP, actor...">
        </div>

        <select name="actor_type" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Actors</option>
            @foreach (['admin' => 'Admin', 'user' => 'User', 'system' => 'System'] as $value => $label)
                <option value="{{ $value }}" @selected(request('actor_type') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <select name="entity_type" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto; min-width: 180px;">
            <option value="">All Entities</option>
            @foreach ($entityTypeOptions as $entityTypeOption)
                <option value="{{ $entityTypeOption }}" @selected(request('entity_type') === $entityTypeOption)>{{ str($entityTypeOption)->replace('_', ' ')->title() }}</option>
            @endforeach
        </select>

        <button class="btn btn-outline-dynamic rounded-3 px-3" type="submit"><i class="bi bi-funnel me-2"></i>Filter</button>
        <a href="{{ route('admin.activity.activity_logs') }}" class="btn btn-outline-dynamic rounded-3 px-3"><i class="bi bi-arrow-clockwise me-2"></i>Reset</a>
    </form>

    <div class="glass rounded-4 overflow-hidden border-white-05">
        <div class="table-responsive scrollbar-hidden">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-white-05">
                    <tr>
                        <th class="ps-4 text-muted small fw-bold text-uppercase py-3">Event</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Actor</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Entity</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Metadata</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">IP</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Created</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        @php
                            $metadataId = 'log-meta-'.$log->id;
                            $actorClass = match ($log->actor_type) {
                                'admin' => 'bg-danger-soft text-danger',
                                'system' => 'bg-secondary-soft text-muted',
                                default => 'bg-info-soft text-info',
                            };
                        @endphp
                        <tr class="border-white-05">
                            <td class="ps-4">
                                <div class="fw-semibold small">{{ str($log->event_name)->replace('_', ' ')->title() }}</div>
                                <div class="text-muted extra-small">#{{ $log->id }}</div>
                            </td>
                            <td>
                                <span class="badge {{ $actorClass }} rounded-pill mb-2">{{ str($log->actor_type)->title() }}</span>
                                @if ($log->user)
                                    <a href="{{ route('admin.users.show', $log->user) }}" class="d-block text-decoration-none small fw-semibold">
                                        {{ $log->user->full_name ?: '@'.$log->user->username }}
                                    </a>
                                    <div class="text-muted extra-small">{{ '@'.$log->user->username }}</div>
                                @else
                                    <div class="text-muted small">No linked user</div>
                                @endif
                            </td>
                            <td>
                                <div class="small fw-semibold">{{ str($log->entity_type ?: 'n/a')->replace('_', ' ')->title() }}</div>
                                <div class="text-muted extra-small">{{ $log->entity_id ? '#'.$log->entity_id : 'No entity id' }}</div>
                            </td>
                            <td>
                                @if ($log->metadata_json)
                                    <button class="btn btn-sm btn-outline-dynamic rounded-3" type="button" data-bs-toggle="modal" data-bs-target="#{{ $metadataId }}">
                                        <i class="bi bi-braces me-1"></i>View Metadata
                                    </button>

                                    <div class="modal fade" id="{{ $metadataId }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                            <div class="modal-content bg-dark border border-white-10">
                                                <div class="modal-header border-white-10">
                                                    <h5 class="modal-title">{{ str($log->event_name)->replace('_', ' ')->title() }} metadata</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="small text-muted mb-3">
                                                        {{ str($log->entity_type ?: 'n/a')->replace('_', ' ')->title() }} {{ $log->entity_id ? '#'.$log->entity_id : '' }} · {{ $log->created_at?->format('M d, Y H:i') ?? '-' }}
                                                    </div>
                                                    <pre class="mb-0 text-white small bg-black rounded-3 p-3 border border-white-10" style="white-space: pre-wrap;">{{ json_encode($log->metadata_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <span class="text-muted small">No metadata</span>
                                @endif
                            </td>
                            <td><span class="text-muted small">{{ $log->ip_address ?: '-' }}</span></td>
                            <td>
                                <div class="text-muted small">{{ $log->created_at?->format('M d, Y') ?? '-' }}</div>
                                <div class="text-muted extra-small">{{ $log->created_at?->format('H:i') ?? '-' }}</div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center py-5 text-muted">No activity logs found for the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 bg-white-05 border-top border-white-05 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="text-muted small">
                Showing <span class="text-emphasis-dynamic fw-bold">{{ $logs->firstItem() ?? 0 }}-{{ $logs->lastItem() ?? 0 }}</span>
                of <span class="text-emphasis-dynamic fw-bold">{{ number_format($logs->total()) }}</span> logs
            </div>
            {{ $logs->links() }}
        </div>
    </div>
</div>
@endsection
