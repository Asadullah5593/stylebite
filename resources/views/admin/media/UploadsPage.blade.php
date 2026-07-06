@extends('admin.layouts.app')

@section('content')
<div class="media-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold">Media & Tags</span>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">Media Uploads</span>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">Media Uploads</h1>
            <p class="text-muted small mb-0">Uploads library and content tags</p>
        </div>
    </div>

    @include('admin.media.partials.tabs')

    <form method="GET" action="{{ route('admin.media.uploads') }}" class="glass rounded-4 p-3 d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="position-relative flex-grow-1" style="min-width: 250px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control ps-5 border-0 bg-dark-soft rounded-3" placeholder="Search files, mime type, user...">
        </div>

        <select name="media_type" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Types</option>
            <option value="image" @selected(request('media_type') === 'image')>Image</option>
            <option value="video" @selected(request('media_type') === 'video')>Video</option>
        </select>

        <select name="upload_status" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Status</option>
            @foreach (['queued' => 'Queued', 'uploading' => 'Uploading', 'processing' => 'Processing', 'ready' => 'Ready', 'failed' => 'Failed'] as $value => $label)
                <option value="{{ $value }}" @selected(request('upload_status') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <button class="btn btn-outline-dynamic rounded-3 px-3" type="submit"><i class="bi bi-funnel me-2"></i>Filter</button>
        <a href="{{ route('admin.media.uploads') }}" class="btn btn-outline-dynamic rounded-3 px-3"><i class="bi bi-arrow-clockwise me-2"></i>Reset</a>
    </form>

    <div class="glass rounded-4 overflow-hidden border-white-05">
        <div class="table-responsive scrollbar-hidden">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-white-05">
                    <tr>
                        <th class="ps-4 text-muted small fw-bold text-uppercase py-3">Upload</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">User</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Type</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">File</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Size</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Status</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Uploaded</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($uploads as $upload)
                        <tr class="border-white-05">
                            <td class="ps-4">
                                <div class="fw-bold small">#{{ $upload->id }}</div>
                                <div class="text-muted extra-small">{{ $upload->upload_type }}</div>
                            </td>
                            <td><span class="small fw-semibold">{{ $upload->user?->full_name ?: '@'.$upload->user?->username }}</span></td>
                            <td><span class="badge bg-info-soft text-info rounded-pill text-uppercase">{{ $upload->media_type }}</span></td>
                            <td>
                                <div class="small fw-semibold">{{ $upload->original_file_name ?: 'Unnamed file' }}</div>
                                <div class="text-muted extra-small text-truncate" style="max-width: 320px;">{{ $upload->file_url }}</div>
                            </td>
                            <td><span class="text-muted small">{{ $upload->size_bytes ? number_format($upload->size_bytes / 1024, 1).' KB' : '—' }}</span></td>
                            <td><span class="badge {{ $upload->upload_status === 'ready' ? 'bg-success-soft text-success' : ($upload->upload_status === 'failed' ? 'bg-danger-soft text-danger' : 'bg-warning-soft text-warning') }} rounded-pill">{{ str($upload->upload_status)->title() }}</span></td>
                            <td><span class="text-muted small">{{ $upload->uploaded_at?->format('M d, Y H:i') ?? $upload->created_at?->format('M d, Y H:i') }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center py-5 text-muted">No media uploads found for the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 bg-white-05 border-top border-white-05 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="text-muted small">
                Showing <span class="text-emphasis-dynamic fw-bold">{{ $uploads->firstItem() ?? 0 }}-{{ $uploads->lastItem() ?? 0 }}</span>
                of <span class="text-emphasis-dynamic fw-bold">{{ number_format($uploads->total()) }}</span> uploads
            </div>
            {{ $uploads->links() }}
        </div>
    </div>
</div>
@endsection
