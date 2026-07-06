@extends('admin.layouts.app')

@section('content')
<div class="engagement-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold">Engagement</span>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">Views</span>
    </nav>
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">Post Views</h1>
            <p class="text-muted small mb-0">View events, sources, and watch times.</p>
        </div>
    </div>
    @include('admin.engagement.partials.tabs')
    <form method="GET" action="{{ route('admin.engagement.views') }}" class="glass rounded-4 p-3 d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="position-relative flex-grow-1" style="min-width: 250px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control ps-5 border-0 bg-dark-soft rounded-3" placeholder="Search by user, caption, source, device, or view ID...">
        </div>
        <select name="view_source" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Sources</option>
            @foreach (['feed' => 'Feed', 'reel' => 'Reel', 'detail' => 'Detail', 'explore' => 'Explore', 'profile' => 'Profile'] as $value => $label)
                <option value="{{ $value }}" @selected(request('view_source') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="btn btn-outline-dynamic rounded-3 px-3" type="submit"><i class="bi bi-funnel me-2"></i>Filter</button>
        <a href="{{ route('admin.engagement.views') }}" class="btn btn-outline-dynamic rounded-3 px-3"><i class="bi bi-arrow-clockwise me-2"></i>Reset</a>
    </form>
    <div class="glass rounded-4 overflow-hidden border-white-05">
        <div class="table-responsive scrollbar-hidden">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-white-05">
                    <tr>
                        <th class="ps-4 text-muted small fw-bold text-uppercase py-3">View</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Viewer</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Post</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Source</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Watch</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Created</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($views as $view)
                        <tr class="border-white-05">
                            <td class="ps-4">
                                <div class="fw-bold small">#{{ $view->id }}</div>
                                <div class="text-muted extra-small">{{ $view->device_id ?: 'No device id' }}</div>
                            </td>
                            <td>
                                @if ($view->viewer)
                                    <a href="{{ route('admin.users.show', $view->viewer->id) }}" class="text-decoration-none text-reset small fw-semibold">{{ $view->viewer->full_name ?: '@'.$view->viewer->username }}</a>
                                @else
                                    <span class="text-muted small">Anonymous / unavailable</span>
                                @endif
                            </td>
                            <td style="min-width: 280px;">
                                @if ($view->post)
                                    <a href="{{ route('admin.posts.show', $view->post->id) }}" class="text-decoration-none text-reset">
                                        <div class="small fw-semibold">{{ \Illuminate\Support\Str::limit($view->post->caption ?: 'Untitled post', 80) }}</div>
                                        <div class="text-muted extra-small">Post #{{ $view->post->id }}</div>
                                    </a>
                                @else
                                    <span class="text-muted small">Post unavailable</span>
                                @endif
                            </td>
                            <td><span class="badge bg-info-soft text-info rounded-pill">{{ str($view->view_source)->title() }}</span></td>
                            <td><span class="text-muted small">{{ $view->watch_seconds !== null ? $view->watch_seconds.' sec' : '-' }}</span></td>
                            <td><span class="text-muted small">{{ $view->created_at?->format('M d, Y H:i') ?? '-' }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center py-5 text-muted">No post views found for the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 bg-white-05 border-top border-white-05 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="text-muted small">Showing <span class="text-emphasis-dynamic fw-bold">{{ $views->firstItem() ?? 0 }}-{{ $views->lastItem() ?? 0 }}</span> of <span class="text-emphasis-dynamic fw-bold">{{ number_format($views->total()) }}</span> post views</div>
            {{ $views->links() }}
        </div>
    </div>
</div>
@endsection
