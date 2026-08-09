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

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <div>
            <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">Activity Logs</h1>
            <p class="text-muted small mb-0">Every action taken in this panel — who, what, when, from where, and whether it went through</p>
        </div>
        <a href="{{ route('admin.activity.export', request()->query()) }}" class="btn btn-outline-dynamic rounded-3">
            <i class="bi bi-download me-2"></i>Export CSV
        </a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md col-6"><div class="glass rounded-4 p-3 h-100"><div class="text-muted small">Total Events</div><div class="fs-4 fw-bold">{{ number_format($activityStats['total'] ?? 0) }}</div></div></div>
        <div class="col-md col-6"><div class="glass rounded-4 p-3 h-100"><div class="text-muted small">Today</div><div class="fs-4 fw-bold">{{ number_format($activityStats['today'] ?? 0) }}</div></div></div>
        <div class="col-md col-6"><div class="glass rounded-4 p-3 h-100"><div class="text-muted small">Staff Actions</div><div class="fs-4 fw-bold">{{ number_format($activityStats['admins'] ?? 0) }}</div></div></div>
        <div class="col-md col-6">
            <a href="{{ route('admin.activity.activity_logs', ['outcome' => 'blocked']) }}" class="text-decoration-none text-reset">
                <div class="glass rounded-4 p-3 h-100"><div class="text-muted small">Blocked Attempts</div><div class="fs-4 fw-bold text-warning">{{ number_format($activityStats['blocked'] ?? 0) }}</div></div>
            </a>
        </div>
        <div class="col-md col-6">
            <a href="{{ route('admin.activity.activity_logs', ['event_name' => 'admin_login_failed']) }}" class="text-decoration-none text-reset">
                <div class="glass rounded-4 p-3 h-100"><div class="text-muted small">Failed Sign-ins</div><div class="fs-4 fw-bold {{ ($activityStats['failed_logins'] ?? 0) > 0 ? 'text-danger' : '' }}">{{ number_format($activityStats['failed_logins'] ?? 0) }}</div></div>
            </a>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.activity.activity_logs') }}" class="glass rounded-4 p-3 mb-4">
        <div class="row g-3 align-items-end">
            <div class="col-12 col-lg-4">
                <label class="form-label small fw-bold text-muted">Search</label>
                <div class="position-relative">
                    <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                    <input type="text" name="q" value="{{ request('q') }}" class="form-control ps-5 border-0 bg-dark-soft rounded-3" placeholder="Event, description, route, IP, actor...">
                </div>
            </div>

            <div class="col-6 col-lg-2">
                <label class="form-label small fw-bold text-muted">Staff Member</label>
                <select name="user_id" class="form-select border-0 bg-dark-soft rounded-3 text-muted">
                    <option value="">Anyone</option>
                    @foreach ($actorOptions as $actor)
                        <option value="{{ $actor->id }}" @selected((string) request('user_id') === (string) $actor->id)>{{ $actor->full_name ?: '@'.$actor->username }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-6 col-lg-2">
                <label class="form-label small fw-bold text-muted">Outcome</label>
                <select name="outcome" class="form-select border-0 bg-dark-soft rounded-3 text-muted">
                    <option value="">Any outcome</option>
                    @foreach ([\App\Models\ActivityLog::OUTCOME_APPLIED => 'Applied', \App\Models\ActivityLog::OUTCOME_BLOCKED => 'Blocked (no permission)', \App\Models\ActivityLog::OUTCOME_REJECTED => 'Rejected (invalid)', \App\Models\ActivityLog::OUTCOME_FAILED => 'Failed (error)'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('outcome') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-6 col-lg-2">
                <label class="form-label small fw-bold text-muted">Actor Type</label>
                <select name="actor_type" class="form-select border-0 bg-dark-soft rounded-3 text-muted">
                    <option value="">All</option>
                    @foreach (['admin' => 'Staff', 'user' => 'User', 'system' => 'System'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('actor_type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-6 col-lg-2">
                <label class="form-label small fw-bold text-muted">Method</label>
                <select name="method" class="form-select border-0 bg-dark-soft rounded-3 text-muted">
                    <option value="">Any</option>
                    @foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $value)
                        <option value="{{ $value }}" @selected(request('method') === $value)>{{ $value }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-12 col-lg-3">
                <label class="form-label small fw-bold text-muted">Event</label>
                <select name="event_name" class="form-select border-0 bg-dark-soft rounded-3 text-muted">
                    <option value="">All events</option>
                    @foreach ($eventNameOptions as $eventNameOption)
                        <option value="{{ $eventNameOption }}" @selected(request('event_name') === $eventNameOption)>{{ str($eventNameOption)->replace(['_', '.'], ' ')->title() }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-6 col-lg-3">
                <label class="form-label small fw-bold text-muted">Entity</label>
                <select name="entity_type" class="form-select border-0 bg-dark-soft rounded-3 text-muted">
                    <option value="">All entities</option>
                    @foreach ($entityTypeOptions as $entityTypeOption)
                        <option value="{{ $entityTypeOption }}" @selected(request('entity_type') === $entityTypeOption)>{{ str($entityTypeOption)->replace('_', ' ')->title() }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-6 col-lg-2">
                <label class="form-label small fw-bold text-muted">From</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control border-0 bg-dark-soft rounded-3">
            </div>

            <div class="col-6 col-lg-2">
                <label class="form-label small fw-bold text-muted">To</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control border-0 bg-dark-soft rounded-3">
            </div>

            <div class="col-6 col-lg-2 d-flex gap-2">
                <button class="btn btn-outline-dynamic rounded-3 px-3 w-100" type="submit"><i class="bi bi-funnel me-2"></i>Filter</button>
                <a href="{{ route('admin.activity.activity_logs') }}" class="btn btn-outline-dynamic rounded-3 px-3" title="Reset filters"><i class="bi bi-arrow-clockwise"></i></a>
            </div>
        </div>
    </form>

    <div class="glass rounded-4 overflow-hidden border-white-05">
        <div class="table-responsive scrollbar-hidden">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-white-05">
                    <tr>
                        <th class="ps-4 text-muted small fw-bold text-uppercase py-3">Action</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Who</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Target</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Outcome</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Request</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">When</th>
                        <th class="text-end pe-4 text-muted small fw-bold text-uppercase py-3">Detail</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        @php
                            $detailId = 'log-detail-'.$log->id;
                            $actorClass = match ($log->actor_type) {
                                'admin' => 'bg-danger-soft text-danger',
                                'system' => 'bg-secondary-soft text-muted',
                                default => 'bg-info-soft text-info',
                            };
                            $outcomeLabel = $log->outcomeLabel();
                            $outcomeClass = match ($log->outcome) {
                                \App\Models\ActivityLog::OUTCOME_BLOCKED,
                                \App\Models\ActivityLog::OUTCOME_REJECTED => 'bg-warning-soft text-warning',
                                \App\Models\ActivityLog::OUTCOME_FAILED => 'bg-danger-soft text-danger',
                                default => 'bg-success-soft text-success',
                            };
                        @endphp
                        <tr class="border-white-05">
                            <td class="ps-4">
                                <div class="fw-semibold small">{{ $log->description ?: str($log->event_name)->replace(['_', '.'], ' ')->title() }}</div>
                                <div class="text-muted extra-small font-monospace">{{ $log->event_name }}</div>
                            </td>
                            <td>
                                @if ($log->user)
                                    <a href="{{ route('admin.users.show', $log->user) }}" class="text-decoration-none small fw-semibold d-block">
                                        {{ $log->user->full_name ?: '@'.$log->user->username }}
                                    </a>
                                @else
                                    <div class="text-muted small">Not signed in</div>
                                @endif
                                <div class="d-flex align-items-center gap-1 mt-1 flex-wrap">
                                    <span class="badge {{ $actorClass }} rounded-pill extra-small">{{ $log->actor_type === 'admin' ? 'Staff' : str($log->actor_type)->title() }}</span>
                                    @if ($log->actor_role)
                                        <span class="badge bg-secondary-soft text-muted rounded-pill extra-small">{{ str($log->actor_role)->replace('_', ' ')->title() }}</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <div class="small fw-semibold">{{ str($log->entity_type ?: '—')->replace('_', ' ')->title() }}</div>
                                <div class="text-muted extra-small">{{ $log->entity_id ? '#'.$log->entity_id : '' }}</div>
                            </td>
                            <td>
                                <span class="badge {{ $outcomeClass }} rounded-pill">{{ $outcomeLabel }}</span>
                                @if ($log->response_status)
                                    <div class="text-muted extra-small mt-1">HTTP {{ $log->response_status }}</div>
                                @endif
                            </td>
                            <td>
                                @if ($log->http_method)
                                    <span class="badge bg-dark-soft text-muted rounded-pill font-monospace extra-small">{{ $log->http_method }}</span>
                                @endif
                                <div class="text-muted extra-small text-truncate" style="max-width: 210px;">{{ $log->route_name ?: '—' }}</div>
                                <div class="text-muted extra-small">{{ $log->ip_address ?: 'no IP' }}</div>
                            </td>
                            <td>
                                <div class="text-muted small">{{ $log->created_at?->format('M d, Y') ?? '-' }}</div>
                                <div class="text-muted extra-small">{{ $log->created_at?->format('H:i:s') ?? '-' }}</div>
                            </td>
                            <td class="text-end pe-4">
                                <button class="btn btn-sm btn-outline-dynamic rounded-3" type="button" data-bs-toggle="modal" data-bs-target="#{{ $detailId }}">
                                    <i class="bi bi-braces"></i>
                                </button>

                                <div class="modal fade text-start" id="{{ $detailId }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                        <div class="modal-content bg-dark border border-white-10">
                                            <div class="modal-header border-white-10">
                                                <h5 class="modal-title">{{ $log->description ?: str($log->event_name)->replace(['_', '.'], ' ')->title() }}</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <dl class="row small mb-3">
                                                    <dt class="col-4 text-muted">Log ID</dt><dd class="col-8">#{{ $log->id }}</dd>
                                                    <dt class="col-4 text-muted">When</dt><dd class="col-8">{{ $log->created_at?->format('M d, Y H:i:s') ?? '—' }}</dd>
                                                    <dt class="col-4 text-muted">Actor</dt>
                                                    <dd class="col-8">
                                                        {{ $log->user ? ($log->user->full_name ?: '@'.$log->user->username) : 'Not signed in' }}
                                                        <span class="text-muted">({{ $log->actor_type }}{{ $log->actor_role ? ' · '.$log->actor_role : '' }})</span>
                                                    </dd>
                                                    <dt class="col-4 text-muted">Event</dt><dd class="col-8 font-monospace">{{ $log->event_name }}</dd>
                                                    <dt class="col-4 text-muted">Target</dt><dd class="col-8">{{ $log->entity_type ?: '—' }} {{ $log->entity_id ? '#'.$log->entity_id : '' }}</dd>
                                                    <dt class="col-4 text-muted">Outcome</dt><dd class="col-8">{{ $outcomeLabel }} @if ($log->response_status)<span class="text-muted">(HTTP {{ $log->response_status }})</span>@endif</dd>
                                                    <dt class="col-4 text-muted">Request</dt><dd class="col-8 font-monospace">{{ $log->http_method }} {{ $log->route_name }}</dd>
                                                    <dt class="col-4 text-muted">URL</dt><dd class="col-8 text-break font-monospace extra-small">{{ $log->url ?: '—' }}</dd>
                                                    <dt class="col-4 text-muted">IP</dt><dd class="col-8">{{ $log->ip_address ?: '—' }}</dd>
                                                    <dt class="col-4 text-muted">User agent</dt><dd class="col-8 text-break extra-small">{{ $log->user_agent ?: '—' }}</dd>
                                                    @if ($log->request_id)
                                                        <dt class="col-4 text-muted">Request ID</dt><dd class="col-8 font-monospace extra-small">{{ $log->request_id }}</dd>
                                                    @endif
                                                </dl>

                                                <div class="text-muted small mb-2 fw-bold text-uppercase">What was submitted / changed</div>
                                                @if ($log->metadata_json)
                                                    <pre class="mb-0 text-white small bg-black rounded-3 p-3 border border-white-10" style="white-space: pre-wrap;">{{ json_encode($log->metadata_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                                @else
                                                    <div class="text-muted small">No additional detail recorded.</div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center py-5 text-muted">No activity found for the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 bg-white-05 border-top border-white-05 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="text-muted small">
                Showing <span class="text-emphasis-dynamic fw-bold">{{ $logs->firstItem() ?? 0 }}-{{ $logs->lastItem() ?? 0 }}</span>
                of <span class="text-emphasis-dynamic fw-bold">{{ number_format($logs->total()) }}</span> events
            </div>
            {{ $logs->links() }}
        </div>
    </div>

    <div class="glass rounded-4 p-3 mt-4 border border-white-05 text-muted small">
        <i class="bi bi-info-circle me-2"></i>
        Every state-changing action in this panel is recorded automatically — including attempts that were blocked for lack of permission or rejected as invalid. Page views are not logged, except private conversations, member account pages and data exports.
    </div>
</div>
@endsection
