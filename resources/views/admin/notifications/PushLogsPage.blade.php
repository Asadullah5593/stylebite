@extends('admin.layouts.app')

@section('content')
<div class="notifications-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold">Notifications</span>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">Push Logs</span>
    </nav>

    <div class="mb-4">
        <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">Notifications</h1>
        <p class="text-muted small mb-0">Push delivery health across providers, devices, and users</p>
    </div>

    @include('admin.notifications.partials.tabs')

    @if (session('status'))
        <div class="alert alert-success rounded-3 border-0 mb-4">{{ session('status') }}</div>
    @endif

    <form method="GET" action="{{ route('admin.notifications.push_logs') }}" class="glass rounded-4 p-3 d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="position-relative flex-grow-1" style="min-width: 250px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control ps-5 border-0 bg-dark-soft rounded-3" placeholder="Search response, user, notification...">
        </div>

        <select name="status" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Status</option>
            @foreach (['queued' => 'Queued', 'sent' => 'Sent', 'failed' => 'Failed'] as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <select name="provider" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Providers</option>
            @foreach (['fcm' => 'FCM', 'apns' => 'APNS'] as $value => $label)
                <option value="{{ $value }}" @selected(request('provider') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <button class="btn btn-outline-dynamic rounded-3 px-3" type="submit"><i class="bi bi-funnel me-2"></i>Filter</button>
        <a href="{{ route('admin.notifications.push_logs') }}" class="btn btn-outline-dynamic rounded-3 px-3"><i class="bi bi-arrow-clockwise me-2"></i>Reset</a>
    </form>

    <div class="glass rounded-4 overflow-hidden border-white-05">
        <div class="table-responsive scrollbar-hidden">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-white-05">
                    <tr>
                        <th class="ps-4 text-muted small fw-bold text-uppercase py-3">Notification</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">User</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Device</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Provider</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Status</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Response</th>
                        <th class="text-muted small fw-bold text-uppercase py-3 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pushLogs as $pushLog)
                        <tr class="border-white-05">
                            <td class="ps-4">
                                <div class="small fw-semibold">{{ $pushLog->notification?->title ?: 'Notification #'.$pushLog->notification_id }}</div>
                                <div class="text-muted extra-small">{{ str($pushLog->notification?->type ?: 'unknown')->replace('_', ' ')->title() }}</div>
                            </td>
                            <td>
                                @if ($pushLog->user)
                                    <a href="{{ route('admin.users.show', $pushLog->user) }}" class="text-decoration-none">
                                        <div class="small fw-semibold">{{ $pushLog->user->full_name ?: '@'.$pushLog->user->username }}</div>
                                        <div class="text-muted extra-small">{{ '@'.$pushLog->user->username }}</div>
                                    </a>
                                @else
                                    <span class="text-muted small">Missing user</span>
                                @endif
                            </td>
                            <td>
                                <div class="small">{{ $pushLog->deviceToken?->device_id ?: 'No device token' }}</div>
                                <div class="text-muted extra-small">{{ strtoupper($pushLog->deviceToken?->platform ?: 'unknown') }}</div>
                            </td>
                            <td><span class="badge bg-secondary-soft text-muted rounded-pill">{{ strtoupper($pushLog->provider) }}</span></td>
                            <td><span class="badge {{ $pushLog->status === 'sent' ? 'bg-success-soft text-success' : ($pushLog->status === 'queued' ? 'bg-warning-soft text-warning' : 'bg-danger-soft text-danger') }} rounded-pill">{{ str($pushLog->status)->title() }}</span></td>
                            <td style="min-width: 260px;">
                                @if ($pushLog->provider_response)
                                    <button class="btn btn-sm btn-outline-dynamic rounded-3 px-3" type="button" data-bs-toggle="modal" data-bs-target="#pushResponse{{ $pushLog->id }}">
                                        <i class="bi bi-card-text me-1"></i>View response
                                    </button>
                                    <div class="modal fade" id="pushResponse{{ $pushLog->id }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered modal-lg">
                                            <div class="modal-content bg-dark border border-white-10">
                                                <div class="modal-header border-white-10">
                                                    <h5 class="modal-title">Push log #{{ $pushLog->id }} response</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <pre class="mb-0 small text-white bg-black rounded-3 p-3 border border-white-10" style="white-space: pre-wrap;">{{ $pushLog->provider_response }}</pre>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <span class="text-muted small">No response body</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if ($pushLog->status === 'failed')
                                    <form method="POST" action="{{ route('admin.notifications.push_logs.retry', $pushLog) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-dynamic rounded-3 px-3">
                                            <i class="bi bi-arrow-repeat me-1"></i>Retry
                                        </button>
                                    </form>
                                @else
                                    <span class="text-muted extra-small">No action</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center py-5 text-muted">No push logs found for the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 bg-white-05 border-top border-white-05 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="text-muted small">Showing <span class="text-emphasis-dynamic fw-bold">{{ $pushLogs->firstItem() ?? 0 }}-{{ $pushLogs->lastItem() ?? 0 }}</span> of <span class="text-emphasis-dynamic fw-bold">{{ number_format($pushLogs->total()) }}</span> push logs</div>
            {{ $pushLogs->links() }}
        </div>
    </div>
</div>
@endsection
