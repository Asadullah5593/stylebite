@extends('admin.layouts.app')

@section('content')
<div class="settings-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold">Settings</span>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">Job Batches</span>
    </nav>

    <div class="mb-4">
        <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">Job Batches</h1>
        <p class="text-muted small mb-0">Monitor bulk queue runs, pending counts, and options payloads.</p>
    </div>

    @include('admin.settings.partials.tabs')

    <form method="GET" action="{{ route('admin.settings.job_batches') }}" class="glass rounded-4 p-3 d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="position-relative flex-grow-1" style="min-width: 260px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control ps-5 border-0 bg-dark-soft rounded-3" placeholder="Search batch id or name...">
        </div>
        <button class="btn btn-outline-dynamic rounded-3 px-3" type="submit"><i class="bi bi-funnel me-2"></i>Filter</button>
        <a href="{{ route('admin.settings.job_batches') }}" class="btn btn-outline-dynamic rounded-3 px-3"><i class="bi bi-arrow-clockwise me-2"></i>Reset</a>
    </form>

    <div class="glass rounded-4 overflow-hidden border-white-05">
        <div class="table-responsive scrollbar-hidden">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-white-05">
                    <tr>
                        <th class="ps-4 text-muted small fw-bold text-uppercase py-3">Batch</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Progress</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Failures</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Options</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Timeline</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($jobBatches as $jobBatch)
                        @php
                            $optionsModalId = 'batchOptions'.md5($jobBatch->id);
                            $completion = $jobBatch->total_jobs > 0
                                ? round((($jobBatch->total_jobs - $jobBatch->pending_jobs) / $jobBatch->total_jobs) * 100)
                                : 0;
                        @endphp
                        <tr class="border-white-05">
                            <td class="ps-4">
                                <div class="small fw-semibold">{{ $jobBatch->name ?: 'Unnamed batch' }}</div>
                                <div class="text-muted extra-small">{{ $jobBatch->id }}</div>
                            </td>
                            <td style="min-width: 240px;">
                                <div class="d-flex justify-content-between small mb-2">
                                    <span>{{ number_format((int) $jobBatch->pending_jobs) }} pending of {{ number_format((int) $jobBatch->total_jobs) }}</span>
                                    <span class="text-muted">{{ $completion }}%</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar" role="progressbar" style="width: {{ $completion }}%"></div>
                                </div>
                            </td>
                            <td>
                                <div class="small fw-semibold text-danger">{{ number_format((int) $jobBatch->failed_jobs) }}</div>
                                <div class="text-muted extra-small">{{ $jobBatch->failed_job_ids ?: 'No failed job ids' }}</div>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-dynamic rounded-3 px-3" type="button" data-bs-toggle="modal" data-bs-target="#{{ $optionsModalId }}">
                                    <i class="bi bi-sliders me-1"></i>View Options
                                </button>
                                <div class="modal fade" id="{{ $optionsModalId }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                        <div class="modal-content bg-dark border border-white-10">
                                            <div class="modal-header border-white-10">
                                                <h5 class="modal-title">{{ $jobBatch->name ?: 'Unnamed batch' }} options</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <pre class="mb-0 small text-white bg-black rounded-3 p-3 border border-white-10" style="white-space: pre-wrap;">{{ $jobBatch->options ?: '{}' }}</pre>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="text-muted small">Created {{ \Carbon\Carbon::createFromTimestamp($jobBatch->created_at)->format('M d, Y H:i') }}</div>
                                <div class="text-muted extra-small">Finished {{ $jobBatch->finished_at ? \Carbon\Carbon::createFromTimestamp($jobBatch->finished_at)->format('M d, Y H:i') : 'In progress' }}</div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center py-5 text-muted">No job batches found for the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 bg-white-05 border-top border-white-05 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="text-muted small">
                Showing <span class="text-emphasis-dynamic fw-bold">{{ $jobBatches->firstItem() ?? 0 }}-{{ $jobBatches->lastItem() ?? 0 }}</span>
                of <span class="text-emphasis-dynamic fw-bold">{{ number_format($jobBatches->total()) }}</span> job batches
            </div>
            {{ $jobBatches->links() }}
        </div>
    </div>
</div>
@endsection
