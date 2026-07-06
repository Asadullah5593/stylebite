@extends('admin.layouts.app')

@section('content')
<div class="settings-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold">Settings</span>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">Failed Jobs</span>
    </nav>

    <div class="mb-4">
        <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">Failed Jobs</h1>
        <p class="text-muted small mb-0">Review queue failures with searchable exception previews.</p>
    </div>

    @include('admin.settings.partials.tabs')

    @if (session('status'))
        <div class="alert alert-success rounded-3 border-0 mb-4">{{ session('status') }}</div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="glass rounded-4 p-3 h-100"><div class="text-muted small">Total Failed Jobs</div><div class="fs-4 fw-bold">{{ number_format($failedJobStats['total'] ?? 0) }}</div></div></div>
        <div class="col-md-4"><div class="glass rounded-4 p-3 h-100"><div class="text-muted small">Filtered Results</div><div class="fs-4 fw-bold">{{ number_format($failedJobStats['visible'] ?? 0) }}</div></div></div>
        <div class="col-md-4"><div class="glass rounded-4 p-3 h-100"><div class="text-muted small">Queues With Failures</div><div class="fs-4 fw-bold">{{ number_format($failedJobStats['queues'] ?? 0) }}</div></div></div>
    </div>

    <form method="GET" action="{{ route('admin.settings.failed_jobs') }}" class="glass rounded-4 p-3 d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="position-relative flex-grow-1" style="min-width: 260px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control ps-5 border-0 bg-dark-soft rounded-3" placeholder="Search UUID, queue, or exception...">
        </div>
        <button class="btn btn-outline-dynamic rounded-3 px-3" type="submit"><i class="bi bi-funnel me-2"></i>Filter</button>
        <a href="{{ route('admin.settings.failed_jobs') }}" class="btn btn-outline-dynamic rounded-3 px-3"><i class="bi bi-arrow-clockwise me-2"></i>Reset</a>
    </form>

    <div class="glass rounded-4 overflow-hidden border-white-05">
        <div class="table-responsive scrollbar-hidden">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-white-05">
                    <tr>
                        <th class="ps-4 text-muted small fw-bold text-uppercase py-3">Failure</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Queue</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Connection</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Exception</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Failed At</th>
                        <th class="text-muted small fw-bold text-uppercase py-3 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($failedJobs as $failedJob)
                        @php $exceptionModalId = 'failedJobException'.$failedJob->id; @endphp
                        <tr class="border-white-05">
                            <td class="ps-4">
                                <div class="small fw-semibold">{{ $failedJob->uuid }}</div>
                                <div class="text-muted extra-small">#{{ $failedJob->id }}</div>
                            </td>
                            <td><span class="badge bg-danger-soft text-danger rounded-pill">{{ $failedJob->queue ?: 'default' }}</span></td>
                            <td><span class="text-muted small">{{ $failedJob->connection }}</span></td>
                            <td style="min-width: 320px;">
                                <div class="small text-muted mb-2">{{ \Illuminate\Support\Str::limit($failedJob->exception, 110) }}</div>
                                <button class="btn btn-sm btn-outline-dynamic rounded-3 px-3" type="button" data-bs-toggle="modal" data-bs-target="#{{ $exceptionModalId }}">
                                    <i class="bi bi-bug me-1"></i>View Exception
                                </button>
                                <div class="modal fade" id="{{ $exceptionModalId }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-xl modal-dialog-scrollable">
                                        <div class="modal-content bg-dark border border-white-10">
                                            <div class="modal-header border-white-10">
                                                <h5 class="modal-title">Failed Job #{{ $failedJob->id }}</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="small text-muted mb-3">{{ $failedJob->queue }} on {{ $failedJob->connection }}</div>
                                                <pre class="mb-0 small text-white bg-black rounded-3 p-3 border border-white-10" style="white-space: pre-wrap;">{{ $failedJob->exception }}</pre>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="text-muted small">{{ \Carbon\Carbon::parse($failedJob->failed_at)->format('M d, Y H:i') }}</span></td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-2 flex-wrap justify-content-end">
                                    <form method="POST" action="{{ route('admin.settings.failed_jobs.retry', $failedJob->id) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-dynamic rounded-3 px-3">
                                            <i class="bi bi-arrow-repeat me-1"></i>Retry
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.settings.failed_jobs.delete', $failedJob->id) }}" onsubmit="return confirm('Delete this failed job record?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger rounded-3 px-3">
                                            <i class="bi bi-trash3 me-1"></i>Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center py-5 text-muted">No failed jobs found for the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 bg-white-05 border-top border-white-05 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="text-muted small">
                Showing <span class="text-emphasis-dynamic fw-bold">{{ $failedJobs->firstItem() ?? 0 }}-{{ $failedJobs->lastItem() ?? 0 }}</span>
                of <span class="text-emphasis-dynamic fw-bold">{{ number_format($failedJobs->total()) }}</span> failed jobs
            </div>
            {{ $failedJobs->links() }}
        </div>
    </div>
</div>
@endsection
