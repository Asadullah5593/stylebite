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
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rules as $rule)
                        <tr class="border-white-05">
                            <td class="ps-4">
                                <div class="fw-semibold small">#{{ $rule->id }}</div>
                                <div class="text-muted small" style="max-width: 420px;">{{ $rule->rule_text }}</div>
                            </td>
                            <td>
                                <div class="small fw-semibold">{{ $rule->contest?->title ?: 'Missing contest' }}</div>
                                <div class="text-muted extra-small">{{ $rule->contest?->slug ?: 'No slug' }}</div>
                            </td>
                            <td><span class="badge {{ $rule->contest?->status === 'active' ? 'bg-success-soft text-success' : 'bg-warning-soft text-warning' }} rounded-pill">{{ str($rule->contest?->status ?: 'unknown')->title() }}</span></td>
                            <td><span class="text-muted small">{{ number_format($rule->sort_order) }}</span></td>
                            <td><span class="text-muted small">{{ $rule->created_at?->format('M d, Y H:i') }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center py-5 text-muted">No contest rules found for the selected filters.</td></tr>
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
