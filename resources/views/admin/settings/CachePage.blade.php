@extends('admin.layouts.app')

@section('content')
<div class="settings-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold">Settings</span>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">Cache</span>
    </nav>

    <div class="mb-4">
        <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">Cache Entries</h1>
        <p class="text-muted small mb-0">Inspect cached keys and values safely with modal-based previews.</p>
    </div>

    @include('admin.settings.partials.tabs')

    @if (session('status'))
        <div class="alert alert-success rounded-3 border-0 mb-4">{{ session('status') }}</div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="glass rounded-4 p-3 h-100"><div class="text-muted small">Total Cache Keys</div><div class="fs-4 fw-bold">{{ number_format($cacheStats['total'] ?? 0) }}</div></div></div>
        <div class="col-md-4"><div class="glass rounded-4 p-3 h-100"><div class="text-muted small">Filtered Results</div><div class="fs-4 fw-bold">{{ number_format($cacheStats['visible'] ?? 0) }}</div></div></div>
        <div class="col-md-4"><div class="glass rounded-4 p-3 h-100"><div class="text-muted small">Expiring Today</div><div class="fs-4 fw-bold">{{ number_format($cacheStats['expiring_today'] ?? 0) }}</div></div></div>
    </div>

    <form method="GET" action="{{ route('admin.settings.cache') }}" class="glass rounded-4 p-3 d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="position-relative flex-grow-1" style="min-width: 260px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control ps-5 border-0 bg-dark-soft rounded-3" placeholder="Search cache key...">
        </div>
        <button class="btn btn-outline-dynamic rounded-3 px-3" type="submit"><i class="bi bi-funnel me-2"></i>Filter</button>
        <a href="{{ route('admin.settings.cache') }}" class="btn btn-outline-dynamic rounded-3 px-3"><i class="bi bi-arrow-clockwise me-2"></i>Reset</a>
    </form>

    <form method="POST" action="{{ route('admin.settings.cache.clear_prefix') }}" class="glass rounded-4 p-3 d-flex flex-wrap align-items-center gap-3 mb-4" onsubmit="return confirm('Clear cache rows matching this prefix?');">
        @csrf
        @method('DELETE')
        <div class="position-relative flex-grow-1" style="min-width: 260px;">
            <i class="bi bi-eraser position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" name="prefix" class="form-control ps-5 border-0 bg-dark-soft rounded-3" placeholder="Enter cache key prefix, for example user:profile:">
        </div>
        <button class="btn btn-outline-danger rounded-3 px-3" type="submit"><i class="bi bi-trash3 me-2"></i>Clear Prefix</button>
    </form>

    <form method="POST" action="{{ route('admin.settings.cache.clear_expired') }}" class="glass rounded-4 p-3 d-flex flex-wrap align-items-center gap-3 mb-4" onsubmit="return confirm('Remove expired cache entries and locks now?');">
        @csrf
        @method('DELETE')
        <div>
            <div class="fw-semibold mb-1">Expired cache cleanup</div>
            <div class="text-muted small">Removes expired cache rows and lock rows in one maintenance action.</div>
        </div>
        <button class="btn btn-outline-danger rounded-3 px-3 ms-auto" type="submit"><i class="bi bi-broom me-2"></i>Clean Expired</button>
    </form>

    <div class="glass rounded-4 overflow-hidden border-white-05">
        <div class="table-responsive scrollbar-hidden">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-white-05">
                    <tr>
                        <th class="ps-4 text-muted small fw-bold text-uppercase py-3">Key</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Expiration</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Value</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($cacheEntries as $cacheEntry)
                        @php $valueModalId = 'cacheValue'.md5($cacheEntry->key); @endphp
                        <tr class="border-white-05">
                            <td class="ps-4">
                                <div class="small fw-semibold font-monospace">{{ $cacheEntry->key }}</div>
                            </td>
                            <td><span class="text-muted small">{{ \Carbon\Carbon::createFromTimestamp($cacheEntry->expiration)->format('M d, Y H:i') }}</span></td>
                            <td style="min-width: 260px;">
                                <div class="small text-muted mb-2">{{ \Illuminate\Support\Str::limit($cacheEntry->value, 100) }}</div>
                                <button class="btn btn-sm btn-outline-dynamic rounded-3 px-3" type="button" data-bs-toggle="modal" data-bs-target="#{{ $valueModalId }}">
                                    <i class="bi bi-eye me-1"></i>View Value
                                </button>
                                <div class="modal fade" id="{{ $valueModalId }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                        <div class="modal-content bg-dark border border-white-10">
                                            <div class="modal-header border-white-10">
                                                <h5 class="modal-title">{{ $cacheEntry->key }}</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <pre class="mb-0 small text-white bg-black rounded-3 p-3 border border-white-10" style="white-space: pre-wrap;">{{ $cacheEntry->value }}</pre>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center py-5 text-muted">No cache entries found for the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 bg-white-05 border-top border-white-05 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="text-muted small">
                Showing <span class="text-emphasis-dynamic fw-bold">{{ $cacheEntries->firstItem() ?? 0 }}-{{ $cacheEntries->lastItem() ?? 0 }}</span>
                of <span class="text-emphasis-dynamic fw-bold">{{ number_format($cacheEntries->total()) }}</span> cache entries
            </div>
            {{ $cacheEntries->links() }}
        </div>
    </div>
</div>
@endsection
