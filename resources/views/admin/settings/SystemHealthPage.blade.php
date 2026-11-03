@extends('admin.layouts.app')

@section('content')
<div class="settings-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold">Settings</span>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">System Health</span>
    </nav>

    <div class="mb-4">
        <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">System Health</h1>
        <p class="text-muted small mb-0">Queue, cache, and migration signals for the live admin environment.</p>
    </div>

    @include('admin.settings.partials.tabs')

    <div class="row g-3 mb-4">
        <div class="col-md-4 col-xl-2">
            <div class="glass rounded-4 p-3 h-100">
                <div class="text-muted small">Queued Jobs</div>
                <div class="fs-4 fw-bold">{{ number_format($healthStats['queued_jobs'] ?? 0) }}</div>
            </div>
        </div>
        <div class="col-md-4 col-xl-2">
            <div class="glass rounded-4 p-3 h-100">
                <div class="text-muted small">Failed Jobs</div>
                <div class="fs-4 fw-bold">{{ number_format($healthStats['failed_jobs'] ?? 0) }}</div>
            </div>
        </div>
        <div class="col-md-4 col-xl-2">
            <div class="glass rounded-4 p-3 h-100">
                <div class="text-muted small">Cache Entries</div>
                <div class="fs-4 fw-bold">{{ number_format($healthStats['cache_entries'] ?? 0) }}</div>
            </div>
        </div>
        <div class="col-md-4 col-xl-2">
            <div class="glass rounded-4 p-3 h-100">
                <div class="text-muted small">Cache Locks</div>
                <div class="fs-4 fw-bold">{{ number_format($healthStats['cache_locks'] ?? 0) }}</div>
            </div>
        </div>
        <div class="col-md-4 col-xl-2">
            <div class="glass rounded-4 p-3 h-100">
                <div class="text-muted small">Migrations</div>
                <div class="fs-4 fw-bold">{{ number_format($healthStats['migrations'] ?? 0) }}</div>
            </div>
        </div>
        <div class="col-md-4 col-xl-2">
            <div class="glass rounded-4 p-3 h-100">
                <div class="text-muted small">Expiring Cache Today</div>
                <div class="fs-4 fw-bold">{{ number_format($cacheStats['expiring_today'] ?? 0) }}</div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="glass rounded-4 p-4 h-100 border border-white-05">
                <h2 class="h6 fw-bold mb-3">Queue snapshot</h2>
                <div class="d-flex justify-content-between py-2 border-bottom border-white-05">
                    <span class="text-muted small">Pending jobs</span>
                    <span class="fw-semibold">{{ number_format($queueStats['jobs'] ?? 0) }}</span>
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom border-white-05">
                    <span class="text-muted small">Failed jobs</span>
                    <span class="fw-semibold">{{ number_format($queueStats['failed_jobs'] ?? 0) }}</span>
                </div>
                <div class="d-flex justify-content-between py-2 mb-3">
                    <span class="text-muted small">Job batches</span>
                    <span class="fw-semibold">{{ number_format($queueStats['job_batches'] ?? 0) }}</span>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('admin.settings.jobs') }}" class="btn btn-sm btn-outline-dynamic rounded-3">Open jobs</a>
                    <a href="{{ route('admin.settings.failed_jobs') }}" class="btn btn-sm btn-outline-dynamic rounded-3">Failed jobs</a>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="glass rounded-4 p-4 h-100 border border-white-05">
                <h2 class="h6 fw-bold mb-3">Cache & migrations</h2>
                <div class="d-flex justify-content-between py-2 border-bottom border-white-05">
                    <span class="text-muted small">Cache entries</span>
                    <span class="fw-semibold">{{ number_format($cacheStats['entries'] ?? 0) }}</span>
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom border-white-05">
                    <span class="text-muted small">Cache locks</span>
                    <span class="fw-semibold">{{ number_format($cacheStats['locks'] ?? 0) }}</span>
                </div>
                <div class="d-flex justify-content-between py-2 mb-3">
                    <span class="text-muted small">Latest migration</span>
                    <span class="fw-semibold text-truncate ms-3" style="max-width: 60%;">{{ $healthStats['latest_migration'] ?? '—' }}</span>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('admin.settings.cache') }}" class="btn btn-sm btn-outline-dynamic rounded-3">Open cache</a>
                    <a href="{{ route('admin.settings.migrations') }}" class="btn btn-sm btn-outline-dynamic rounded-3">Migrations</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
