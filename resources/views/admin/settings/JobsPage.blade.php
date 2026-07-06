@extends('admin.layouts.app')

@section('content')
<div class="settings-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold">Settings</span>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">Queue Jobs</span>
    </nav>

    <div class="mb-4">
        <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">Queue Jobs</h1>
        <p class="text-muted small mb-0">Inspect pending jobs and payload details without leaving the admin panel.</p>
    </div>

    @include('admin.settings.partials.tabs')

    <form method="GET" action="{{ route('admin.settings.jobs') }}" class="glass rounded-4 p-3 d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="position-relative flex-grow-1" style="min-width: 260px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control ps-5 border-0 bg-dark-soft rounded-3" placeholder="Search queue name...">
        </div>
        <button class="btn btn-outline-dynamic rounded-3 px-3" type="submit"><i class="bi bi-funnel me-2"></i>Filter</button>
        <a href="{{ route('admin.settings.jobs') }}" class="btn btn-outline-dynamic rounded-3 px-3"><i class="bi bi-arrow-clockwise me-2"></i>Reset</a>
    </form>

    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="glass rounded-4 p-3 h-100"><div class="text-muted small">Visible Jobs</div><div class="fs-4 fw-bold">{{ number_format($jobs->total()) }}</div></div></div>
        <div class="col-md-4"><div class="glass rounded-4 p-3 h-100"><div class="text-muted small">Queues On Page</div><div class="fs-4 fw-bold">{{ number_format($jobs->pluck('queue')->filter()->unique()->count()) }}</div></div></div>
        <div class="col-md-4"><div class="glass rounded-4 p-3 h-100"><div class="text-muted small">Max Attempts On Page</div><div class="fs-4 fw-bold">{{ number_format((int) $jobs->pluck('attempts')->max()) }}</div></div></div>
    </div>

    <div class="glass rounded-4 overflow-hidden border-white-05">
        <div class="table-responsive scrollbar-hidden">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-white-05">
                    <tr>
                        <th class="ps-4 text-muted small fw-bold text-uppercase py-3">Job</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Queue</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Attempts</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Schedule</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Payload</th>
                        <th class="text-muted small fw-bold text-uppercase py-3 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($jobs as $job)
                        @php $payloadModalId = 'jobPayload'.$job->id; @endphp
                        <tr class="border-white-05">
                            <td class="ps-4">
                                <div class="small fw-semibold">#{{ $job->id }}</div>
                                <div class="text-muted extra-small">Created {{ \Carbon\Carbon::createFromTimestamp($job->created_at)->format('M d, Y H:i') }}</div>
                            </td>
                            <td><span class="badge bg-info-soft text-info rounded-pill">{{ $job->queue ?: 'default' }}</span></td>
                            <td><span class="fw-semibold">{{ number_format((int) $job->attempts) }}</span></td>
                            <td>
                                <div class="text-muted small">Available {{ \Carbon\Carbon::createFromTimestamp($job->available_at)->format('M d, Y H:i') }}</div>
                                <div class="text-muted extra-small">Reserved {{ $job->reserved_at ? \Carbon\Carbon::createFromTimestamp($job->reserved_at)->format('M d, Y H:i') : 'Not reserved' }}</div>
                            </td>
                            <td style="min-width: 240px;">
                                <button class="btn btn-sm btn-outline-dynamic rounded-3 px-3" type="button" data-bs-toggle="modal" data-bs-target="#{{ $payloadModalId }}">
                                    <i class="bi bi-braces me-1"></i>View Payload
                                </button>
                                <div class="modal fade" id="{{ $payloadModalId }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                        <div class="modal-content bg-dark border border-white-10">
                                            <div class="modal-header border-white-10">
                                                <h5 class="modal-title">Job #{{ $job->id }} payload</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <pre class="mb-0 small text-white bg-black rounded-3 p-3 border border-white-10" style="white-space: pre-wrap;">{{ $job->payload }}</pre>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-end pe-4">
                                <form method="POST" action="{{ route('admin.settings.jobs.delete', $job->id) }}" onsubmit="return confirm('Delete this queued job?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-3 px-3">
                                        <i class="bi bi-trash3 me-1"></i>Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center py-5 text-muted">No queued jobs found for the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 bg-white-05 border-top border-white-05 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="text-muted small">
                Showing <span class="text-emphasis-dynamic fw-bold">{{ $jobs->firstItem() ?? 0 }}-{{ $jobs->lastItem() ?? 0 }}</span>
                of <span class="text-emphasis-dynamic fw-bold">{{ number_format($jobs->total()) }}</span> queued jobs
            </div>
            {{ $jobs->links() }}
        </div>
    </div>
</div>
@endsection
