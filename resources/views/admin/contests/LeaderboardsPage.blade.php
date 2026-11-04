@extends('admin.layouts.app')

@section('content')
<div class="contests-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold">Contests</span>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">Leaderboards</span>
    </nav>

    <div class="mb-4">
        <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">Contests</h1>
        <p class="text-muted small mb-0">Live rankings for every contest — filter by contest or category if you want one board only</p>
    </div>

    @include('admin.contests.partials.tabs')

    @if (session('status'))
        <div class="alert alert-success rounded-3 border-0 mb-4">{{ session('status') }}</div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-md-3 col-sm-6"><div class="glass rounded-4 p-3 h-100"><div class="text-muted small">Contests</div><div class="fs-4 fw-bold">{{ number_format($leaderboardStats['contests'] ?? 0) }}</div></div></div>
        <div class="col-md-3 col-sm-6"><div class="glass rounded-4 p-3 h-100"><div class="text-muted small">Approved Participants</div><div class="fs-4 fw-bold">{{ number_format($leaderboardStats['participants'] ?? 0) }}</div></div></div>
        <div class="col-md-3 col-sm-6"><div class="glass rounded-4 p-3 h-100"><div class="text-muted small">Submissions</div><div class="fs-4 fw-bold">{{ number_format($leaderboardStats['submissions'] ?? 0) }}</div></div></div>
        <div class="col-md-3 col-sm-6"><div class="glass rounded-4 p-3 h-100"><div class="text-muted small">Boards on this page</div><div class="fs-4 fw-bold">{{ number_format($leaderboardStats['boards_on_page'] ?? 0) }}</div></div></div>
    </div>

    <form method="GET" action="{{ route('admin.contests.leaderboards') }}" class="glass rounded-4 p-3 d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="position-relative flex-grow-1" style="min-width: 250px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control ps-5 border-0 bg-dark-soft rounded-3" placeholder="Search contest title...">
        </div>

        <select name="contest_id" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto; min-width: 220px;" onchange="this.form.submit()">
            <option value="">All Contests</option>
            @foreach ($contestOptions as $contestOption)
                <option value="{{ $contestOption->id }}" @selected((string) request('contest_id') === (string) $contestOption->id)>
                    #{{ $contestOption->id }} — {{ $contestOption->title !== '' ? $contestOption->title : 'Untitled' }}
                    ({{ str($contestOption->status)->title() }})
                </option>
            @endforeach
        </select>

        <select name="category_key" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto; min-width: 180px;">
            <option value="">All Categories</option>
            @foreach ($categoryOptions as $categoryOption)
                <option value="{{ $categoryOption }}" @selected(request('category_key') === $categoryOption)>{{ str($categoryOption)->replace('_', ' ')->title() }}</option>
            @endforeach
        </select>

        <button class="btn btn-outline-dynamic rounded-3 px-3" type="submit"><i class="bi bi-funnel me-2"></i>Filter</button>
        <a href="{{ route('admin.contests.leaderboards') }}" class="btn btn-outline-dynamic rounded-3 px-3"><i class="bi bi-arrow-clockwise me-2"></i>Reset</a>
    </form>

    @forelse ($boards as $board)
        @php
            $contest = $board['contest'];
            $places = $board['places'];
            $rankings = $board['rankings'];
            $title = $contest->title !== '' ? $contest->title : ('Contest #'.$contest->id);
        @endphp
        <div class="glass rounded-4 p-4 mb-4 border-white-05">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
                <div>
                    <h2 class="h5 fw-bold mb-1">{{ $title }}</h2>
                    <div class="text-muted small">
                        #{{ $contest->id }}
                        · {{ str($contest->status)->title() }}
                        · {{ str($contest->category ?: 'n/a')->replace('_', ' ')->title() }}
                        · {{ number_format((int) $contest->approved_participants_count) }} participants
                        · {{ number_format((int) $contest->submissions_count) }} submissions
                        @if ($contest->winnerUser)
                            · Winner {{ '@'.$contest->winnerUser->username }}
                        @endif
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('admin.contests.recalculate', $contest) }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-dynamic rounded-3 px-3">
                            <i class="bi bi-arrow-repeat me-1"></i>Recalculate
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.contests.leaderboards.regenerate', $contest) }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-dynamic rounded-3 px-3">
                            <i class="bi bi-camera me-1"></i>Save snapshot
                        </button>
                    </form>
                    <a href="{{ route('admin.contests.enrolled', $contest) }}" class="btn btn-sm btn-outline-dynamic rounded-3 px-3">
                        <i class="bi bi-people me-1"></i>Enrolled
                    </a>
                </div>
            </div>

            @if ($places->isNotEmpty())
                <div class="row g-3 mb-3">
                    @foreach ($places as $place)
                        <div class="col-md-4 col-lg-3">
                            <div class="bg-white-05 rounded-3 p-3 h-100">
                                <div class="text-muted extra-small text-uppercase fw-bold">{{ \App\Models\ContestPlace::ordinal((int) $place->place) }} place</div>
                                <div class="fw-semibold">{{ $place->winnerUser?->username ? '@'.$place->winnerUser->username : 'No winner yet' }}</div>
                                <div class="text-muted small">{{ number_format((float) $place->prize_amount, 2) }} prize</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="table-responsive scrollbar-hidden">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-white-05">
                        <tr>
                            <th class="ps-3 text-muted small fw-bold text-uppercase py-3">Rank</th>
                            <th class="text-muted small fw-bold text-uppercase py-3">Participant</th>
                            <th class="text-muted small fw-bold text-uppercase py-3">Score</th>
                            <th class="text-muted small fw-bold text-uppercase py-3">Ratings</th>
                            <th class="text-muted small fw-bold text-uppercase py-3">Likes</th>
                            <th class="text-muted small fw-bold text-uppercase py-3">Status</th>
                            <th class="text-muted small fw-bold text-uppercase py-3">Submission</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rankings as $index => $row)
                            <tr class="border-white-05">
                                <td class="ps-3"><span class="fw-bold">{{ $row->rank_position ?: ($index + 1) }}</span></td>
                                <td>
                                    <div class="fw-semibold small">{{ $row->user?->full_name ?: '—' }}</div>
                                    <div class="text-muted extra-small">{{ '@'.($row->user?->username ?: 'unknown') }} · user #{{ $row->user_id }}</div>
                                </td>
                                <td><span class="fw-semibold">{{ number_format((float) ($row->final_score ?? $row->total_score ?? 0), 2) }}</span></td>
                                <td><span class="text-muted small">{{ number_format((int) ($row->vote_count ?? 0)) }}</span></td>
                                <td><span class="text-muted small">{{ number_format((int) ($row->like_count ?? 0)) }}</span></td>
                                <td><span class="badge bg-secondary-soft text-muted rounded-pill">{{ str($row->status ?: 'n/a')->title() }}</span></td>
                                <td>
                                    @if ($row->submission_id)
                                        <span class="text-muted small">#{{ $row->submission_id }}</span>
                                    @else
                                        <span class="text-muted extra-small">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No ranked participants or submissions for this contest yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <div class="glass rounded-4 p-5 text-center text-muted mb-4">
            No contests found for the selected filters.
            @if (! empty($selectedContestId))
                <div class="extra-small mt-2">Requested contest id: {{ $selectedContestId }}</div>
            @endif
            <div class="mt-3">
                <a href="{{ route('admin.contests.leaderboards') }}" class="btn btn-outline-dynamic rounded-3 px-3">Show all contests</a>
            </div>
        </div>
    @endforelse

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <div class="text-muted small">
            Showing <span class="text-emphasis-dynamic fw-bold">{{ $contests->firstItem() ?? 0 }}-{{ $contests->lastItem() ?? 0 }}</span>
            of <span class="text-emphasis-dynamic fw-bold">{{ number_format($contests->total()) }}</span> contests
        </div>
        {{ $contests->links() }}
    </div>

    @if ($snapshots->isNotEmpty())
        <div class="glass rounded-4 overflow-hidden border-white-05">
            <div class="p-4 border-bottom border-white-05">
                <h2 class="h6 fw-bold mb-0">Saved snapshots</h2>
            </div>
            <div class="table-responsive scrollbar-hidden">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-white-05">
                        <tr>
                            <th class="ps-4 text-muted small fw-bold text-uppercase py-3">Contest</th>
                            <th class="text-muted small fw-bold text-uppercase py-3">Period</th>
                            <th class="text-muted small fw-bold text-uppercase py-3">Category</th>
                            <th class="text-muted small fw-bold text-uppercase py-3">Generated</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($snapshots as $snapshot)
                            <tr class="border-white-05">
                                <td class="ps-4"><div class="small fw-semibold">{{ $snapshot->contest?->title ?: 'Missing contest' }}</div></td>
                                <td><span class="badge bg-secondary-soft text-muted rounded-pill">{{ $snapshot->period_key }}</span></td>
                                <td><span class="badge bg-info-soft text-info rounded-pill">{{ str($snapshot->category_key)->replace('_', ' ')->title() }}</span></td>
                                <td><span class="text-muted small">{{ $snapshot->generated_at?->format('M d, Y H:i') }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
