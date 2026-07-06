@extends('admin.layouts.app')

@section('content')
<div class="memories-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold">Memories</span>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">Memory Media</span>
    </nav>

    <div class="mb-4">
        <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">Memories</h1>
        <p class="text-muted small mb-0">User memories, media and saves</p>
    </div>

    @include('admin.memories.partials.tabs')

    <form method="GET" action="{{ route('admin.memories.memory-media') }}" class="glass rounded-4 p-3 d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="position-relative flex-grow-1" style="min-width: 250px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control ps-5 border-0 bg-dark-soft rounded-3" placeholder="Search memory media...">
        </div>

        <select name="media_type" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Types</option>
            <option value="image" @selected(request('media_type') === 'image')>Image</option>
            <option value="video" @selected(request('media_type') === 'video')>Video</option>
        </select>

        <button class="btn btn-outline-dynamic rounded-3 px-3" type="submit"><i class="bi bi-funnel me-2"></i>Filter</button>
        <a href="{{ route('admin.memories.memory-media') }}" class="btn btn-outline-dynamic rounded-3 px-3"><i class="bi bi-arrow-clockwise me-2"></i>Reset</a>
    </form>

    <div class="glass rounded-4 overflow-hidden border-white-05">
        <div class="table-responsive scrollbar-hidden">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-white-05">
                    <tr>
                        <th class="ps-4 text-muted small fw-bold text-uppercase py-3">Media</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Memory</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Type</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Preview</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Sort</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Created</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($memoryMedia as $item)
                        <tr class="border-white-05">
                            <td class="ps-4"><span class="fw-bold small">#{{ $item->id }}</span></td>
                            <td><span class="text-muted small">{{ $item->memory?->title ?: 'Unknown memory' }}</span></td>
                            <td><span class="badge bg-info-soft text-info rounded-pill text-uppercase">{{ $item->media_type }}</span></td>
                            <td><span class="text-muted extra-small text-truncate d-inline-block" style="max-width: 280px;">{{ $item->file_url }}</span></td>
                            <td><span class="text-muted small">{{ $item->sort_order }}</span></td>
                            <td><span class="text-muted small">{{ $item->created_at?->format('M d, Y H:i') ?? '—' }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center py-5 text-muted">No memory media found for the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 bg-white-05 border-top border-white-05 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="text-muted small">
                Showing <span class="text-emphasis-dynamic fw-bold">{{ $memoryMedia->firstItem() ?? 0 }}-{{ $memoryMedia->lastItem() ?? 0 }}</span>
                of <span class="text-emphasis-dynamic fw-bold">{{ number_format($memoryMedia->total()) }}</span> memory media records
            </div>
            {{ $memoryMedia->links() }}
        </div>
    </div>
</div>
@endsection
