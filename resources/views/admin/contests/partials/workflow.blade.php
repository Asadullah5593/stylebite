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
                $winnerOptions = $winnerUsers ?? collect();
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
    @if ($showEnrolledLink ?? true)
        <a href="{{ route('admin.contests.enrolled', $contest) }}" class="btn btn-sm btn-outline-dynamic rounded-3">
            <i class="bi bi-people me-1"></i>Enrolled users
        </a>
    @endif
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

@once
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
@endonce
