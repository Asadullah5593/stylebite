@extends('admin.layouts.app')

@section('content')
<div class="contests-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <a href="{{ route('admin.contests.contests') }}" class="text-decoration-none text-reset fw-bold">Contests</a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">Detail</span>
    </nav>

    <div class="mb-4 d-flex align-items-start justify-content-between flex-wrap gap-3">
        <div>
            <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">{{ $contest->title }}</h1>
            <p class="text-muted small mb-0">Winners, enrolled users, and ratings</p>
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
                        <th class="text-muted small fw-bold text-uppercase py-3 text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($participants as $participant)
                        @php
                            $prizePlace = $placeByUserId->get((int) $participant->user_id);
                            $submission = $submissionByUserId->get($participant->user_id);
                            $score = $participant->total_score ?? $submission?->final_score ?? $submission?->community_score;
                            $rating = $ratingsByUserId->get($participant->user_id) ?? $ratingsByUserId->get((string) $participant->user_id);
                            $ratingCount = is_array($rating) ? (int) $rating['count'] : 0;
                            $ratingAverage = is_array($rating) ? $rating['average'] : null;
                            $ratingVoters = is_array($rating) ? $rating['voters'] : collect();
                            $user = $participant->user;
                        @endphp
                        <tr class="border-white-05">
                            <td class="ps-4">
                                @if ($user)
                                    <a href="{{ route('admin.users.show', $user) }}" class="text-decoration-none d-flex align-items-center gap-3 py-1">
                                        @if ($user->avatar_url)
                                            <img src="{{ $user->avatar_url }}" alt="{{ $user->username }}" class="rounded-circle border border-white-05 flex-shrink-0" width="42" height="42" style="object-fit: cover;">
                                        @else
                                            <div class="rounded-circle border border-white-05 d-flex align-items-center justify-content-center text-muted flex-shrink-0" style="width:42px;height:42px;">
                                                <i class="bi bi-person"></i>
                                            </div>
                                        @endif
                                        <div class="min-w-0">
                                            <div class="small fw-semibold text-truncate">{{ $user->full_name ?: '@'.$user->username }}</div>
                                            <div class="text-muted extra-small text-truncate">{{ '@'.$user->username }}</div>
                                        </div>
                                    </a>
                                @else
                                    <span class="text-muted small">Removed account</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $participant->status === 'approved' ? 'bg-success-soft text-success' : ($participant->status === 'banned' ? 'bg-danger-soft text-danger' : 'bg-warning-soft text-warning') }} rounded-pill">
                                    {{ str($participant->status)->title() }}
                                </span>
                                @if ($user)
                                    <div class="text-muted extra-small mt-1">Account {{ $user->status === 'inactive' ? 'Suspended' : str($user->status)->title() }}</div>
                                @endif
                            </td>
                            <td>
                                @if ($participant->rank_position)
                                    <span class="small fw-semibold">{{ \App\Models\ContestPlace::ordinal((int) $participant->rank_position) }} place</span>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                                @if ($prizePlace)
                                    <div class="extra-small text-success mt-1">Prize: {{ $prizePlace->displayTitle() }}</div>
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
                            <td class="text-end pe-4">
                                @if ($user)
                                    <div class="d-flex justify-content-end gap-1 flex-wrap">
                                        <a href="{{ route('admin.users.show', $user) }}" class="btn btn-sm btn-outline-dynamic rounded-3" title="Open profile">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.activity.activity_logs', ['q' => $user->username]) }}" class="btn btn-sm btn-outline-dynamic rounded-3" title="Watch activity">
                                            <i class="bi bi-activity me-1"></i>Watch
                                        </a>
                                        @if ($user->status !== 'inactive')
                                            <form method="POST" action="{{ route('admin.users.status', $user) }}" id="suspend-contest-user-{{ $user->id }}" class="d-inline">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="action" value="suspend">
                                                <button class="btn btn-sm btn-outline-warning rounded-3" type="button" onclick="confirmContestUserAction('suspend-contest-user-{{ $user->id }}', 'Suspend this user?', 'This disables the account without permanently banning it.')">
                                                    <i class="bi bi-slash-circle me-1"></i>Suspend
                                                </button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('admin.users.status', $user) }}" id="activate-contest-user-{{ $user->id }}" class="d-inline">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="action" value="activate">
                                                <button class="btn btn-sm btn-outline-success rounded-3" type="button" onclick="confirmContestUserAction('activate-contest-user-{{ $user->id }}', 'Activate this user?', 'This restores normal access immediately.')">
                                                    <i class="bi bi-check-circle me-1"></i>Activate
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">No users have enrolled in this contest yet.</td>
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

<div class="modal fade confirm-modal" id="confirmContestUserActionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-white-05">
                <h5 class="modal-title" id="confirmContestUserActionTitle">Confirm action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-muted" id="confirmContestUserActionText"></div>
            <div class="modal-footer border-white-05">
                <button type="button" class="btn btn-outline-dynamic rounded-3" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary rounded-3" id="confirmContestUserActionSubmit">Confirm</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        const modalElement = document.getElementById('confirmContestUserActionModal');
        if (!modalElement || typeof bootstrap === 'undefined') return;

        const titleEl = document.getElementById('confirmContestUserActionTitle');
        const textEl = document.getElementById('confirmContestUserActionText');
        const submitEl = document.getElementById('confirmContestUserActionSubmit');
        const confirmModal = new bootstrap.Modal(modalElement);
        let pendingFormId = null;

        window.confirmContestUserAction = function (formId, title, text) {
            pendingFormId = formId;
            titleEl.textContent = title || 'Confirm action';
            textEl.textContent = text || '';
            confirmModal.show();
        };

        submitEl.addEventListener('click', function () {
            if (!pendingFormId) return;
            const form = document.getElementById(pendingFormId);
            confirmModal.hide();
            if (form) form.submit();
        });
    })();
</script>
@endpush
