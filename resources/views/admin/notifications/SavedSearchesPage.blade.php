@extends('admin.layouts.app')

@section('content')
<div class="notifications-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold">Notifications</span>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">Saved Searches</span>
    </nav>

    <div class="mb-4">
        <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">Notifications</h1>
        <p class="text-muted small mb-0">Saved user searches with scope and filters preview</p>
    </div>

    @include('admin.notifications.partials.tabs')

    <form method="GET" action="{{ route('admin.notifications.saved_searches') }}" class="glass rounded-4 p-3 d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="position-relative flex-grow-1" style="min-width: 250px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control ps-5 border-0 bg-dark-soft rounded-3" placeholder="Search query, scope, user...">
        </div>

        <select name="result_scope" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Scopes</option>
            @foreach (['users' => 'Users', 'posts' => 'Posts', 'reels' => 'Reels', 'food' => 'Food', 'contests' => 'Contests', 'all' => 'All'] as $value => $label)
                <option value="{{ $value }}" @selected(request('result_scope') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <button class="btn btn-outline-dynamic rounded-3 px-3" type="submit"><i class="bi bi-funnel me-2"></i>Filter</button>
        <a href="{{ route('admin.notifications.saved_searches') }}" class="btn btn-outline-dynamic rounded-3 px-3"><i class="bi bi-arrow-clockwise me-2"></i>Reset</a>
    </form>

    <div class="glass rounded-4 overflow-hidden border-white-05">
        <div class="table-responsive scrollbar-hidden">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-white-05">
                    <tr>
                        <th class="ps-4 text-muted small fw-bold text-uppercase py-3">User</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Query</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Scope</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Filters</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Last Used</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($savedSearches as $savedSearch)
                        <tr class="border-white-05">
                            <td class="ps-4">
                                @if ($savedSearch->user)
                                    <a href="{{ route('admin.users.show', $savedSearch->user) }}" class="text-decoration-none">
                                        <div class="small fw-semibold">{{ $savedSearch->user->full_name ?: '@'.$savedSearch->user->username }}</div>
                                        <div class="text-muted extra-small">{{ '@'.$savedSearch->user->username }}</div>
                                    </a>
                                @else
                                    <span class="text-muted small">Missing user</span>
                                @endif
                            </td>
                            <td><div class="small fw-semibold">{{ $savedSearch->query }}</div></td>
                            <td><span class="badge bg-info-soft text-info rounded-pill">{{ str($savedSearch->result_scope)->title() }}</span></td>
                            <td style="min-width: 220px;">
                                @if ($savedSearch->filters_json)
                                    <button class="btn btn-sm btn-outline-dynamic rounded-3 px-3" type="button" data-bs-toggle="modal" data-bs-target="#savedSearchFilters{{ $savedSearch->id }}">
                                        <i class="bi bi-sliders me-1"></i>View filters
                                    </button>
                                    <div class="modal fade" id="savedSearchFilters{{ $savedSearch->id }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered modal-lg">
                                            <div class="modal-content bg-dark border border-white-10">
                                                <div class="modal-header border-white-10">
                                                    <h5 class="modal-title">Saved search #{{ $savedSearch->id }} filters</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <pre class="mb-0 small text-white bg-black rounded-3 p-3 border border-white-10" style="white-space: pre-wrap;">{{ json_encode($savedSearch->filters_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <span class="text-muted small">No filters</span>
                                @endif
                            </td>
                            <td><span class="text-muted small">{{ $savedSearch->last_used_at?->format('M d, Y H:i') ?? '-' }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center py-5 text-muted">No saved searches found for the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 bg-white-05 border-top border-white-05 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="text-muted small">Showing <span class="text-emphasis-dynamic fw-bold">{{ $savedSearches->firstItem() ?? 0 }}-{{ $savedSearches->lastItem() ?? 0 }}</span> of <span class="text-emphasis-dynamic fw-bold">{{ number_format($savedSearches->total()) }}</span> saved searches</div>
            {{ $savedSearches->links() }}
        </div>
    </div>
</div>
@endsection
