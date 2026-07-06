@extends('admin.layouts.app')

@section('content')
<div class="posts-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold">Posts</span>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">Post Ratings</span>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">Posts</h1>
            <p class="text-muted small mb-0">Posts, media, tags and ratings</p>
        </div>
    </div>

    @include('admin.posts.partials.tabs')

    <form method="GET" action="{{ route('admin.posts.post_ratings') }}" class="glass rounded-4 p-3 d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="position-relative flex-grow-1" style="min-width: 250px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control ps-5 border-0 bg-dark-soft rounded-3" placeholder="Search by post caption or user...">
        </div>

        <select name="rating_value" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Ratings</option>
            @foreach ([5,4,3,2,1] as $rating)
                <option value="{{ $rating }}" @selected((string) request('rating_value') === (string) $rating)>{{ $rating }} Star{{ $rating > 1 ? 's' : '' }}</option>
            @endforeach
        </select>

        <button class="btn btn-outline-dynamic rounded-3 px-3" type="submit"><i class="bi bi-funnel me-2"></i>Filter</button>
        <a href="{{ route('admin.posts.post_ratings') }}" class="btn btn-outline-dynamic rounded-3 px-3"><i class="bi bi-arrow-clockwise me-2"></i>Reset</a>
    </form>

    <div class="glass rounded-4 overflow-hidden border-white-05">
        <div class="table-responsive scrollbar-hidden">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-white-05">
                    <tr>
                        <th class="ps-4 text-muted small fw-bold text-uppercase py-3">Rating</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Post</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">User</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Value</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Created</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Updated</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($ratings as $rating)
                        <tr class="border-white-05">
                            <td class="ps-4"><span class="fw-bold small">#{{ $rating->id }}</span></td>
                            <td><span class="text-muted small">{{ str($rating->post?->caption ?: 'No post caption')->limit(40) }}</span></td>
                            <td><span class="small fw-semibold">{{ $rating->user?->full_name ?: '@'.$rating->user?->username }}</span></td>
                            <td><span class="badge bg-warning-soft text-warning rounded-pill">{{ $rating->rating_value }}/5</span></td>
                            <td><span class="text-muted small">{{ $rating->created_at?->format('M d, Y H:i') ?? '—' }}</span></td>
                            <td><span class="text-muted small">{{ $rating->updated_at?->format('M d, Y H:i') ?? '—' }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center py-5 text-muted">No post ratings found for the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 bg-white-05 border-top border-white-05 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="text-muted small">
                Showing <span class="text-emphasis-dynamic fw-bold">{{ $ratings->firstItem() ?? 0 }}-{{ $ratings->lastItem() ?? 0 }}</span>
                of <span class="text-emphasis-dynamic fw-bold">{{ number_format($ratings->total()) }}</span> ratings
            </div>
            {{ $ratings->links() }}
        </div>
    </div>
</div>
@endsection
