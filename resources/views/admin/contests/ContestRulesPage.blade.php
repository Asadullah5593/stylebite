@extends('admin.layouts.app')

@section('content')
<div class="contests-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold">Contests</span>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">Rules</span>
    </nav>

    <div class="mb-4">
        <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">Contests</h1>
        <p class="text-muted small mb-0">Contests, teams, submissions and votes</p>
    </div>

    @include('admin.contests.partials.tabs')

    {{-- Platform winning rules (backend-enforced) --}}
    <div class="glass rounded-4 p-4 mb-4 border-white-05">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
            <div>
                <h2 class="h5 fw-bold mb-1">Winning rules (platform)</h2>
                <p class="text-muted small mb-0">
                    These apply to every contest in the backend and app when ranking and awarding prizes.
                </p>
            </div>
            <span class="badge bg-success-soft text-success rounded-pill">Live in system</span>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="bg-white-05 rounded-3 p-3 h-100">
                    <div class="text-muted extra-small text-uppercase fw-bold mb-1">Score formula</div>
                    <div class="fw-semibold small">{{ $winningRules['score_formula_label'] }}</div>
                    <div class="text-muted extra-small mt-1"><code>{{ $winningRules['score_formula'] }}</code></div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="bg-white-05 rounded-3 p-3 h-100">
                    <div class="text-muted extra-small text-uppercase fw-bold mb-1">Eligibility</div>
                    <div class="fw-semibold small">
                        Eligible submissions first — need at least
                        <span class="text-emphasis-dynamic">{{ number_format($winningRules['min_rating_voters']) }}</span>
                        active raters before a prize place can be won.
                    </div>
                    <div class="text-muted extra-small mt-1">Banned / deleted accounts and blocked posts are excluded from ranking.</div>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.contests.contest_rules.winning') }}" class="d-flex flex-wrap align-items-end gap-3">
            @csrf
            <div style="min-width: 200px;">
                <label class="form-label small text-muted mb-1" for="min_rating_voters">Min active raters to win</label>
                <input
                    type="number"
                    min="1"
                    max="1000"
                    name="min_rating_voters"
                    id="min_rating_voters"
                    value="{{ old('min_rating_voters', $winningRules['min_rating_voters']) }}"
                    class="form-control border-0 bg-dark-soft rounded-3 @error('min_rating_voters') is-invalid @enderror"
                    required
                >
                @error('min_rating_voters')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <button type="submit" class="btn btn-primary-gradient rounded-3 px-4">
                <i class="bi bi-save me-2"></i>Save winning rule
            </button>
        </form>
        <p class="text-muted extra-small mt-2 mb-0">
            Changing this updates <code>contests.min_rating_voters</code> immediately for score eligibility and prize assignment.
        </p>
    </div>

    {{-- Per-contest display rules --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
        <div>
            <h2 class="h5 fw-bold mb-1">Contest display rules</h2>
            <p class="text-muted small mb-0">
                Shown on each contest in the app. Edit text below; seed defaults for contests that still have none.
            </p>
        </div>
        <form method="POST" action="{{ route('admin.contests.contest_rules.seed') }}" class="d-inline"
              onsubmit="return confirm('Add default rules to every contest that currently has zero rules? Existing rules will not be changed.');">
            @csrf
            <button type="submit" class="btn btn-outline-dynamic rounded-3 px-3">
                <i class="bi bi-magic me-2"></i>Seed defaults
                @if (($contestsMissingRules ?? 0) > 0)
                    <span class="badge bg-warning-soft text-warning ms-1">{{ number_format($contestsMissingRules) }} empty</span>
                @endif
            </button>
        </form>
    </div>

    <form method="GET" action="{{ route('admin.contests.contest_rules') }}" class="glass rounded-4 p-3 d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="position-relative flex-grow-1" style="min-width: 250px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control ps-5 border-0 bg-dark-soft rounded-3" placeholder="Search rules, contest title...">
        </div>
        <button class="btn btn-outline-dynamic rounded-3 px-3" type="submit"><i class="bi bi-funnel me-2"></i>Filter</button>
        <a href="{{ route('admin.contests.contest_rules') }}" class="btn btn-outline-dynamic rounded-3 px-3"><i class="bi bi-arrow-clockwise me-2"></i>Reset</a>
    </form>

    <div class="glass rounded-4 overflow-hidden border-white-05">
        <div class="table-responsive scrollbar-hidden">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-white-05">
                    <tr>
                        <th class="ps-4 text-muted small fw-bold text-uppercase py-3">Rule</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Contest</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Status</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Order</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Created</th>
                        <th class="pe-4 text-muted small fw-bold text-uppercase py-3 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rules as $rule)
                        <tr class="border-white-05">
                            <td class="ps-4" style="min-width: 320px;">
                                <form method="POST" action="{{ route('admin.contests.contest_rules.update', $rule) }}" class="d-flex flex-column gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <div class="fw-semibold small text-muted">#{{ $rule->id }}</div>
                                    <textarea
                                        name="rule_text"
                                        rows="2"
                                        maxlength="500"
                                        class="form-control form-control-sm border-0 bg-dark-soft rounded-3"
                                        required
                                    >{{ old('rule_text', $rule->rule_text) }}</textarea>
                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                        <input
                                            type="number"
                                            name="sort_order"
                                            min="0"
                                            max="1000"
                                            value="{{ old('sort_order', $rule->sort_order) }}"
                                            class="form-control form-control-sm border-0 bg-dark-soft rounded-3"
                                            style="width: 90px;"
                                            title="Sort order"
                                        >
                                        <button type="submit" class="btn btn-sm btn-outline-dynamic rounded-3">
                                            <i class="bi bi-check2 me-1"></i>Save
                                        </button>
                                    </div>
                                </form>
                            </td>
                            <td>
                                <div class="small fw-semibold">{{ $rule->contest?->title ?: 'Missing contest' }}</div>
                                <div class="text-muted extra-small">{{ $rule->contest?->slug ?: 'No slug' }}</div>
                            </td>
                            <td>
                                <span class="badge {{ $rule->contest?->status === 'active' ? 'bg-success-soft text-success' : 'bg-warning-soft text-warning' }} rounded-pill">
                                    {{ str($rule->contest?->status ?: 'unknown')->title() }}
                                </span>
                            </td>
                            <td><span class="text-muted small">{{ number_format($rule->sort_order) }}</span></td>
                            <td><span class="text-muted small">{{ $rule->created_at?->format('M d, Y H:i') }}</span></td>
                            <td class="pe-4 text-end">
                                <form method="POST" action="{{ route('admin.contests.contest_rules.destroy', $rule) }}" class="d-inline"
                                      onsubmit="return confirm('Delete this rule? The app will stop showing it.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-3">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                No contest rules found.
                                @if (($contestsMissingRules ?? 0) > 0)
                                    Use <strong>Seed defaults</strong> to create the standard rules for {{ number_format($contestsMissingRules) }} contest{{ $contestsMissingRules === 1 ? '' : 's' }}.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 bg-white-05 border-top border-white-05 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="text-muted small">
                Showing <span class="text-emphasis-dynamic fw-bold">{{ $rules->firstItem() ?? 0 }}-{{ $rules->lastItem() ?? 0 }}</span>
                of <span class="text-emphasis-dynamic fw-bold">{{ number_format($rules->total()) }}</span> rules
            </div>
            {{ $rules->links() }}
        </div>
    </div>
</div>
@endsection
