@extends('admin.layouts.app')

@section('content')
<div class="contests-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold">Contests</span>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">Contests</span>
    </nav>

    <div class="mb-4">
        <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
            <div>
                <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">Contests</h1>
                <p class="text-muted small mb-0">Contests, teams, submissions and votes</p>
            </div>
            <a href="{{ route('admin.contests.create') }}" class="btn btn-primary rounded-3 px-4 align-self-start">
                <i class="bi bi-plus-circle me-1"></i>Create Contest
            </a>
        </div>
    </div>

    @include('admin.contests.partials.tabs')

    @if (session('status'))
        <div class="alert alert-success rounded-3 border-0 mb-4">{{ session('status') }}</div>
    @endif

    <form method="GET" action="{{ route('admin.contests.contests') }}" class="glass rounded-4 p-3 d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="position-relative flex-grow-1" style="min-width: 250px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control ps-5 border-0 bg-dark-soft rounded-3" placeholder="Search contests, creator, city...">
        </div>

        <select name="status" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Status</option>
            @foreach (['draft' => 'Draft', 'active' => 'Active', 'upcoming' => 'Upcoming', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'archived' => 'Archived'] as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <select name="category" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Category</option>
            @foreach (['admin' => 'Admin', 'community' => 'Community'] as $value => $label)
                <option value="{{ $value }}" @selected(request('category') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <input type="text" name="contest_type" value="{{ request('contest_type') }}" class="form-control border-0 bg-dark-soft rounded-3" style="width: 180px;" placeholder="Contest type">

        <button class="btn btn-outline-dynamic rounded-3 px-3" type="submit"><i class="bi bi-funnel me-2"></i>Filter</button>
        <a href="{{ route('admin.contests.contests') }}" class="btn btn-outline-dynamic rounded-3 px-3"><i class="bi bi-arrow-clockwise me-2"></i>Reset</a>
    </form>

    <div class="glass rounded-4 overflow-hidden border-white-05">
        <div class="table-responsive scrollbar-hidden">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-white-05">
                    <tr>
                        <th class="ps-4 text-muted small fw-bold text-uppercase py-3">Contest</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Creator</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Category</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Type</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Location</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Stats</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Status</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Created</th>
                        <th class="text-muted small fw-bold text-uppercase py-3 text-end">Workflow</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($contests as $contest)
                        <tr class="border-white-05">
                            <td class="ps-4">
                                <div class="d-flex align-items-center gap-3">
                                    @php
                                        $contestImage = stylebite_asset_url($contest->cover_image_url ?: $contest->banner_image_url);
                                    @endphp
                                    @if ($contestImage)
                                        <img src="{{ $contestImage }}" alt="{{ $contest->title }}" class="rounded-3 border border-white-05" width="48" height="48" style="object-fit: cover;">
                                    @else
                                        <div class="rounded-3 border border-white-05 d-flex align-items-center justify-content-center text-muted" style="width:48px;height:48px;">
                                            <i class="bi bi-trophy"></i>
                                        </div>
                                    @endif
                                    <div>
                                        <div class="fw-semibold small">{{ $contest->title }}</div>
                                        <div class="text-muted extra-small">{{ $contest->slug }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if ($contest->creator)
                                    <a href="{{ route('admin.users.show', $contest->creator) }}" class="text-decoration-none">
                                        <div class="small fw-semibold">{{ $contest->creator->full_name ?: '@'.$contest->creator->username }}</div>
                                        <div class="text-muted extra-small">{{ '@'.$contest->creator->username }}</div>
                                    </a>
                                @else
                                    <span class="text-muted small">No creator</span>
                                @endif
                            </td>
                            <td><span class="badge bg-info-soft text-info rounded-pill">{{ str($contest->category)->title() }}</span></td>
                            <td><span class="badge bg-secondary-soft text-muted rounded-pill">{{ $contest->contest_type }}</span></td>
                            <td><span class="text-muted small">{{ collect([$contest->city, $contest->country])->filter()->implode(', ') ?: 'No location' }}</span></td>
                            <td><span class="text-muted small">{{ number_format($contest->participant_count) }} participants · {{ number_format($contest->submission_count) }} submissions</span></td>
                            <td><span class="badge {{ in_array($contest->status, ['active', 'completed'], true) ? 'bg-success-soft text-success' : ($contest->status === 'upcoming' ? 'bg-info-soft text-info' : ($contest->status === 'cancelled' ? 'bg-danger-soft text-danger' : 'bg-warning-soft text-warning')) }} rounded-pill">{{ str($contest->status)->title() }}</span></td>
                            <td><span class="text-muted small">{{ $contest->created_at instanceof \DateTimeInterface ? $contest->created_at->format('M d, Y H:i') : '' }}</span></td>
                            <td class="pe-4" style="min-width: 280px;">
                                <div class="contest-workflow d-grid gap-2">
                                    <div class="d-flex gap-2">
                                        <a href="{{ route('admin.contests.edit', $contest) }}" class="btn btn-sm btn-outline-dynamic rounded-3 flex-fill">
                                            <i class="bi bi-pencil-square me-1"></i>Edit
                                        </a>
                                        @if (Route::has('admin.contests.destroy'))
                                            <form method="POST" action="{{ route('admin.contests.destroy', $contest) }}" class="flex-fill" onsubmit="return confirm('Delete this contest? It will be removed from the app.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-3 w-100">
                                                    <i class="bi bi-trash3 me-1"></i>Delete
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                    <form method="POST" action="{{ route('admin.contests.workflow.update', $contest) }}" class="d-grid gap-2" data-workflow-form>
                                        @csrf
                                        @method('PATCH')
                                        <select name="status" class="form-select form-select-sm border-0 rounded-3" aria-label="Contest status">
                                            @foreach (['draft' => 'Draft', 'active' => 'Active', 'upcoming' => 'Upcoming', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'archived' => 'Archived'] as $value => $label)
                                                <option value="{{ $value }}" @selected($contest->status === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        @php
                                            $winnerOptions = $contest->participants
                                                ->map(fn ($participant) => $participant->user)
                                                ->filter()
                                                ->merge($contest->places->map(fn ($place) => $place->winnerUser)->filter())
                                                ->unique('id')
                                                ->values();
                                            if ($winnerOptions->isEmpty()) {
                                                $winnerOptions = $winnerUsers;
                                            }
                                        @endphp
                                        @forelse ($contest->places as $place)
                                            <label class="extra-small text-muted mb-0">
                                                {{ $place->displayTitle() }} · PKR {{ rtrim(rtrim(number_format((float) $place->prize_amount, 2, '.', ''), '0'), '.') }}
                                            </label>
                                            <select name="place_winners[{{ $place->id }}]" class="form-select form-select-sm border-0 rounded-3" aria-label="{{ $place->displayTitle() }} winner">
                                                <option value="">No winner yet</option>
                                                @foreach ($winnerOptions as $winnerUser)
                                                    <option value="{{ $winnerUser->id }}" @selected((int) $place->winner_user_id === (int) $winnerUser->id)>
                                                        {{ $winnerUser->full_name ?: '@'.$winnerUser->username }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        @empty
                                            <div class="extra-small text-muted">Add winner places from Edit before choosing winners.</div>
                                        @endforelse
                                        <button type="submit" class="btn btn-sm btn-primary rounded-3">
                                            <i class="bi bi-check2-circle me-1"></i>Update
                                        </button>
                                    </form>
                                    <div class="text-muted extra-small">
                                        @if ($contest->places->isNotEmpty())
                                            @foreach ($contest->places as $place)
                                                <div>
                                                    {{ \App\Models\ContestPlace::ordinal((int) $place->place) }}:
                                                    {{ $place->winnerUser ? ($place->winnerUser->full_name ?: '@'.$place->winnerUser->username) : 'not set' }}
                                                </div>
                                            @endforeach
                                        @elseif ($contest->winnerUser)
                                            Winner: {{ $contest->winnerUser->full_name ?: '@'.$contest->winnerUser->username }}
                                        @elseif ($contest->winnerTeam)
                                            Winner: {{ $contest->winnerTeam->name }}
                                        @else
                                            Winner not set
                                        @endif
                                    </div>
                                    <div class="d-flex gap-2">
                                        <form method="POST" action="{{ route('admin.contests.recalculate', $contest) }}" class="flex-fill">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-dynamic rounded-3 w-100">
                                                <i class="bi bi-arrow-repeat me-1"></i>Recalculate
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.contests.leaderboards.regenerate', $contest) }}" class="flex-fill">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-dynamic rounded-3 w-100">
                                                <i class="bi bi-graph-up-arrow me-1"></i>Snapshot
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center py-5 text-muted">No contests found for the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 bg-white-05 border-top border-white-05 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="text-muted small">
                Showing <span class="text-emphasis-dynamic fw-bold">{{ $contests->firstItem() ?? 0 }}-{{ $contests->lastItem() ?? 0 }}</span>
                of <span class="text-emphasis-dynamic fw-bold">{{ number_format($contests->total()) }}</span> contests
            </div>
            {{ $contests->links() }}
        </div>
    </div>
</div>
@endsection

@push('scripts')
<style>
    .contest-workflow .form-select,
    .contest-workflow .form-select option {
        color: var(--sb-text);
        background-color: var(--sb-panel-2, #241c28);
        color-scheme: dark;
    }
</style>
<script>
    document.querySelectorAll('[data-workflow-form]').forEach(function (form) {
        const selects = form.querySelectorAll('select[name^="place_winners"]');

        selects.forEach(function (select) {
            select.addEventListener('change', function () {
                if (!select.value) {
                    return;
                }

                selects.forEach(function (other) {
                    if (other !== select && other.value === select.value) {
                        other.value = '';
                    }
                });
            });
        });
    });
</script>
@endpush
