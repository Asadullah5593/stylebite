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
        <p class="text-muted small mb-0">Leaderboard snapshots with contest context and deeper snapshot drilldowns</p>
    </div>

    @include('admin.contests.partials.tabs')

    @if (session('status'))
        <div class="alert alert-success rounded-3 border-0 mb-4">{{ session('status') }}</div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-md-3 col-sm-6"><div class="glass rounded-4 p-3 h-100"><div class="text-muted small">Snapshots</div><div class="fs-4 fw-bold">{{ number_format($leaderboardStats['total'] ?? 0) }}</div></div></div>
        <div class="col-md-3 col-sm-6"><div class="glass rounded-4 p-3 h-100"><div class="text-muted small">Visible Results</div><div class="fs-4 fw-bold">{{ number_format($leaderboardStats['visible'] ?? 0) }}</div></div></div>
        <div class="col-md-3 col-sm-6"><div class="glass rounded-4 p-3 h-100"><div class="text-muted small">Contests Covered</div><div class="fs-4 fw-bold">{{ number_format($leaderboardStats['contests'] ?? 0) }}</div></div></div>
        <div class="col-md-3 col-sm-6"><div class="glass rounded-4 p-3 h-100"><div class="text-muted small">Latest Snapshot</div><div class="fw-semibold">{{ $leaderboardStats['latest_generated_at'] ? \Carbon\Carbon::parse($leaderboardStats['latest_generated_at'])->format('M d, Y H:i') : 'N/A' }}</div></div></div>
    </div>

    <form method="GET" action="{{ route('admin.contests.leaderboards') }}" class="glass rounded-4 p-3 d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="position-relative flex-grow-1" style="min-width: 250px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control ps-5 border-0 bg-dark-soft rounded-3" placeholder="Search contest, period, category...">
        </div>

        <select name="contest_id" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto; min-width: 220px;">
            <option value="">All Contests</option>
            @foreach ($contestOptions as $contestOption)
                <option value="{{ $contestOption->id }}" @selected((string) request('contest_id') === (string) $contestOption->id)>{{ $contestOption->title }}</option>
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

    <div class="glass rounded-4 overflow-hidden border-white-05">
        <div class="table-responsive scrollbar-hidden">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-white-05">
                    <tr>
                        <th class="ps-4 text-muted small fw-bold text-uppercase py-3">Contest</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Period</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Category</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Snapshot</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Generated</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($leaderboards as $leaderboard)
                        @php
                            $payload = $leaderboard->payload_json ?? [];
                            $payloadPreview = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                            $topLevelKeys = is_array($payload) ? implode(', ', array_slice(array_keys($payload), 0, 3)) : '';
                            $summary = $payload['summary'] ?? [];
                            $participants = collect($payload['participants'] ?? [])->take(5);
                            $teams = collect($payload['teams'] ?? [])->take(5);
                            $submissions = collect($payload['submissions'] ?? [])->take(5);
                        @endphp
                        <tr class="border-white-05">
                            <td class="ps-4">
                                <div class="small fw-semibold">{{ $leaderboard->contest?->title ?: 'Missing contest' }}</div>
                                <div class="text-muted extra-small">{{ str($leaderboard->contest?->status ?: 'unknown')->title() }}</div>
                            </td>
                            <td>
                                <span class="badge bg-secondary-soft text-muted rounded-pill">
                                    {{ str($leaderboard->period_key)->replace('_', ' ')->title() }}
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-info-soft text-info rounded-pill">
                                    {{ str($leaderboard->category_key)->replace('_', ' ')->title() }}
                                </span>
                            </td>
                            <td style="min-width: 340px;">
                                <div class="small text-muted mb-2">
                                    {{ $topLevelKeys !== '' ? 'Keys: '.$topLevelKeys : 'No summary keys available' }}
                                </div>
                                <button class="btn btn-sm btn-outline-dynamic rounded-3 px-3" type="button" data-bs-toggle="modal" data-bs-target="#payloadModal{{ $leaderboard->id }}">
                                    <i class="bi bi-code-square me-2"></i>View snapshot
                                </button>

                                <div class="modal fade" id="payloadModal{{ $leaderboard->id }}" tabindex="-1" aria-labelledby="payloadModalLabel{{ $leaderboard->id }}" aria-hidden="true">
                                    <div class="modal-dialog modal-xl modal-dialog-scrollable">
                                        <div class="modal-content bg-dark border border-white-10">
                                            <div class="modal-header border-white-10">
                                                <h5 class="modal-title" id="payloadModalLabel{{ $leaderboard->id }}">Snapshot #{{ $leaderboard->id }} details</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="small text-muted mb-3">
                                                    {{ $leaderboard->contest?->title ?: 'Missing contest' }} · {{ $leaderboard->period_key }} · {{ $leaderboard->category_key }}
                                                </div>

                                                <div class="row g-3 mb-4">
                                                    <div class="col-md-3"><div class="glass rounded-4 p-3 h-100 border border-white-10"><div class="text-muted extra-small">Participants</div><div class="fw-bold">{{ number_format((int) ($summary['participant_count'] ?? 0)) }}</div></div></div>
                                                    <div class="col-md-3"><div class="glass rounded-4 p-3 h-100 border border-white-10"><div class="text-muted extra-small">Submissions</div><div class="fw-bold">{{ number_format((int) ($summary['submission_count'] ?? 0)) }}</div></div></div>
                                                    <div class="col-md-3"><div class="glass rounded-4 p-3 h-100 border border-white-10"><div class="text-muted extra-small">Approved</div><div class="fw-bold">{{ number_format((int) ($summary['approved_submission_count'] ?? 0)) }}</div></div></div>
                                                    <div class="col-md-3"><div class="glass rounded-4 p-3 h-100 border border-white-10"><div class="text-muted extra-small">Votes</div><div class="fw-bold">{{ number_format((int) ($summary['vote_count'] ?? 0)) }}</div></div></div>
                                                </div>

                                                <div class="row g-4 mb-4">
                                                    <div class="col-12 col-xl-4">
                                                        <h6 class="fw-bold mb-2">Top Participants</h6>
                                                        <div class="d-grid gap-2">
                                                            @forelse ($participants as $participant)
                                                                <div class="glass rounded-4 p-3 border border-white-10">
                                                                    <div class="d-flex justify-content-between gap-3">
                                                                        <div>
                                                                            <div class="fw-semibold small">{{ $participant['name'] ?? 'Unknown participant' }}</div>
                                                                            <div class="text-muted extra-small">User #{{ $participant['user_id'] ?? '-' }} · {{ str($participant['status'] ?? 'n/a')->title() }}</div>
                                                                        </div>
                                                                        <div class="text-end">
                                                                            <div class="fw-bold">{{ $participant['score'] !== null ? number_format((float) $participant['score'], 2) : '-' }}</div>
                                                                            <div class="text-muted extra-small">Rank {{ $participant['rank_position'] ?? 'N/A' }}</div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            @empty
                                                                <div class="text-muted small">No participant data.</div>
                                                            @endforelse
                                                        </div>
                                                    </div>

                                                    <div class="col-12 col-xl-4">
                                                        <h6 class="fw-bold mb-2">Top Teams</h6>
                                                        <div class="d-grid gap-2">
                                                            @forelse ($teams as $team)
                                                                <div class="glass rounded-4 p-3 border border-white-10">
                                                                    <div class="d-flex justify-content-between gap-3">
                                                                        <div>
                                                                            <div class="fw-semibold small">{{ $team['name'] ?? 'Unknown team' }}</div>
                                                                            <div class="text-muted extra-small">{{ count($team['members'] ?? []) }} members</div>
                                                                        </div>
                                                                        <div class="text-end">
                                                                            <div class="fw-bold">{{ $team['score'] !== null ? number_format((float) $team['score'], 2) : '-' }}</div>
                                                                            <div class="text-muted extra-small">Rank {{ $team['rank_position'] ?? 'N/A' }}</div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            @empty
                                                                <div class="text-muted small">No team data.</div>
                                                            @endforelse
                                                        </div>
                                                    </div>

                                                    <div class="col-12 col-xl-4">
                                                        <h6 class="fw-bold mb-2">Top Submissions</h6>
                                                        <div class="d-grid gap-2">
                                                            @forelse ($submissions as $submission)
                                                                <div class="glass rounded-4 p-3 border border-white-10">
                                                                    <div class="d-flex justify-content-between gap-3">
                                                                        <div>
                                                                            <div class="fw-semibold small">Submission #{{ $submission['id'] ?? '-' }}</div>
                                                                            <div class="text-muted extra-small">{{ str($submission['status'] ?? 'n/a')->title() }} · Votes {{ number_format((int) ($submission['vote_count'] ?? 0)) }}</div>
                                                                        </div>
                                                                        <div class="text-end">
                                                                            <div class="fw-bold">{{ $submission['final_score'] !== null ? number_format((float) $submission['final_score'], 2) : '-' }}</div>
                                                                            <div class="text-muted extra-small">Rank {{ $submission['rank_position'] ?? 'N/A' }}</div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            @empty
                                                                <div class="text-muted small">No submission data.</div>
                                                            @endforelse
                                                        </div>
                                                    </div>
                                                </div>

                                                <details class="glass rounded-4 p-3 border border-white-10">
                                                    <summary class="fw-semibold" style="cursor: pointer;">Raw snapshot payload</summary>
                                                    <pre class="mt-3 mb-0 small text-white bg-black rounded-3 p-3 border border-white-10" style="white-space: pre-wrap;">{{ $payloadPreview ?: '{}' }}</pre>
                                                </details>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="text-muted small">{{ $leaderboard->generated_at?->format('M d, Y H:i') }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center py-5 text-muted">No leaderboard snapshots found for the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 bg-white-05 border-top border-white-05 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="text-muted small">
                Showing <span class="text-emphasis-dynamic fw-bold">{{ $leaderboards->firstItem() ?? 0 }}-{{ $leaderboards->lastItem() ?? 0 }}</span>
                of <span class="text-emphasis-dynamic fw-bold">{{ number_format($leaderboards->total()) }}</span> leaderboard snapshots
            </div>
            {{ $leaderboards->links() }}
        </div>
    </div>
</div>
@endsection
