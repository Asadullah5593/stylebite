@extends('admin.layouts.app')

@section('content')
<div class="contests-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <a href="{{ route('admin.contests.contests') }}" class="text-decoration-none text-reset fw-bold">Contests</a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">Enrolled users</span>
    </nav>

    <div class="mb-4 d-flex align-items-start justify-content-between flex-wrap gap-3">
        <div>
            <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">{{ $contest->title }}</h1>
            <p class="text-muted small mb-0">Enrolled users for this contest</p>
        </div>
        <a href="{{ route('admin.contests.contests') }}" class="btn btn-outline-dynamic rounded-3 px-3">
            <i class="bi bi-arrow-left me-1"></i>Back to contests
        </a>
    </div>

    @if (session('status'))
        <div class="alert alert-success rounded-3 border-0 mb-4">{{ session('status') }}</div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-md-4 col-sm-6">
            <div class="glass rounded-4 p-3 h-100">
                <div class="text-muted small">Enrolled users</div>
                <div class="fs-4 fw-bold">{{ number_format($participants->count()) }}</div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6">
            <div class="glass rounded-4 p-3 h-100">
                <div class="text-muted small">Submissions</div>
                <div class="fs-4 fw-bold">{{ number_format($contest->submissions->count()) }}</div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6">
            <div class="glass rounded-4 p-3 h-100">
                <div class="text-muted small">Ratings</div>
                <div class="fs-4 fw-bold">{{ number_format($ratingsByUserId->sum('count')) }}</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
    <div class="glass rounded-4 overflow-hidden border-white-05">
        <div class="table-responsive scrollbar-hidden">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-white-05">
                    <tr>
                        <th class="ps-4 text-muted small fw-bold text-uppercase py-3">User</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Status</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Place</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Score</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Rated by</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Joined</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($participants as $participant)
                        @php
                            $place = $placeByUserId->get((int) $participant->user_id);
                            $submission = $submissionByUserId->get($participant->user_id);
                            $score = $participant->total_score ?? $submission?->final_score ?? $submission?->community_score;
                            $rating = $ratingsByUserId->get($participant->user_id) ?? $ratingsByUserId->get((string) $participant->user_id);
                            $ratingCount = is_array($rating) ? (int) $rating['count'] : 0;
                            $ratingAverage = is_array($rating) ? $rating['average'] : null;
                            $ratingVoters = is_array($rating) ? $rating['voters'] : collect();
                        @endphp
                        <tr class="border-white-05">
                            <td class="ps-4">
                                @if ($participant->user)
                                    <a href="{{ route('admin.users.show', $participant->user) }}" class="text-decoration-none">
                                        <div class="small fw-semibold">{{ $participant->user->full_name ?: '@'.$participant->user->username }}</div>
                                        <div class="text-muted extra-small">{{ '@'.$participant->user->username }}</div>
                                    </a>
                                @else
                                    <span class="text-muted small">Removed account</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $participant->status === 'approved' ? 'bg-success-soft text-success' : ($participant->status === 'banned' ? 'bg-danger-soft text-danger' : 'bg-warning-soft text-warning') }} rounded-pill">
                                    {{ str($participant->status)->title() }}
                                </span>
                            </td>
                            <td>
                                @if ($place)
                                    <span class="small fw-semibold">{{ $place->displayTitle() }}</span>
                                @elseif ($participant->rank_position)
                                    <span class="text-muted small">{{ \App\Models\ContestPlace::ordinal((int) $participant->rank_position) }} place</span>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td><span class="text-muted small">{{ $score !== null ? number_format((float) $score, 2) : '—' }}</span></td>
                            <td>
                                <div class="small fw-semibold">{{ number_format($ratingCount) }} {{ $ratingCount === 1 ? 'user' : 'users' }}</div>
                                @if ($ratingAverage !== null)
                                    <div class="text-muted extra-small">Average {{ number_format((float) $ratingAverage, 2) }}</div>
                                @endif
                                @if ($ratingVoters->isNotEmpty())
                                    <div class="text-muted extra-small">{{ $ratingVoters->implode(', ') }}</div>
                                @endif
                            </td>
                            <td><span class="text-muted small">{{ $participant->joined_at?->format('M d, Y H:i') ?? '—' }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">No users have enrolled in this contest yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
        </div>
        <div class="col-xl-4">
            <div class="glass rounded-4 p-3">
                <div class="small fw-bold text-uppercase text-muted mb-3">Contest actions</div>
                @include('admin.contests.partials.workflow', ['showEnrolledLink' => false])
            </div>
        </div>
    </div>
</div>
@endsection
