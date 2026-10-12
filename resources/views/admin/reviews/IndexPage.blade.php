@extends('admin.layouts.app')

@section('content')
<div class="reviews-page">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">Restaurant reviews</span>
    </nav>

    <div class="mb-4">
        <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">Restaurant reviews</h1>
        <p class="text-muted small mb-0">Food ratings people left on restaurants. Removing a review takes it off the app.</p>
    </div>

    @if (session('status'))
        <div class="alert alert-success rounded-3 border-0 mb-4">{{ session('status') }}</div>
    @endif

    <form method="GET" action="{{ route('admin.reviews.index') }}" class="glass rounded-4 p-3 d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="position-relative flex-grow-1" style="min-width: 260px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control ps-5 border-0 bg-dark-soft rounded-3" placeholder="Search restaurant or username">
        </div>
        <button class="btn btn-outline-dynamic rounded-3 px-3" type="submit"><i class="bi bi-funnel me-2"></i>Filter</button>
        <a href="{{ route('admin.reviews.index') }}" class="btn btn-outline-dynamic rounded-3 px-3"><i class="bi bi-arrow-clockwise me-2"></i>Reset</a>
    </form>

    <div class="glass rounded-4 overflow-hidden border-white-05">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-white-05">
                    <tr>
                        <th class="ps-4 text-muted small fw-bold text-uppercase py-3">Restaurant</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Author</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Ratings</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Post</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Created</th>
                        <th class="text-end pe-4 text-muted small fw-bold text-uppercase py-3">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reviews as $review)
                        <tr class="border-white-05">
                            <td class="ps-4">
                                <div class="fw-bold small">{{ $review->restaurant }}</div>
                                <div class="text-muted extra-small">Overall {{ number_format($review->overall(), 1) }}</div>
                            </td>
                            <td>
                                <div class="small fw-semibold">{{ $review->user?->full_name ?: 'Unknown' }}</div>
                                <div class="text-muted extra-small">{{ $review->user?->username ? '@'.$review->user->username : '' }}</div>
                            </td>
                            <td class="text-muted small">
                                Food {{ $review->food_rating }} · Service {{ $review->service_rating }}<br>
                                Staff {{ $review->staff_rating }} · Ambience {{ $review->ambience_rating }}
                            </td>
                            <td>
                                @if ($review->post)
                                    <a href="{{ route('admin.posts.show', $review->post) }}" class="small text-decoration-none">Post #{{ $review->post->id }}</a>
                                @else
                                    <span class="text-muted small">No post</span>
                                @endif
                            </td>
                            <td><span class="text-muted small">{{ $review->created_at?->format('M d, Y') }}</span></td>
                            <td class="text-end pe-4">
                                <form method="POST" action="{{ route('admin.reviews.destroy', $review) }}" onsubmit="return confirm('Remove this restaurant review?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger rounded-3" type="submit">Remove</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center py-5 text-muted">No restaurant reviews found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-top border-white-05">
            {{ $reviews->links() }}
        </div>
    </div>
</div>
@endsection
