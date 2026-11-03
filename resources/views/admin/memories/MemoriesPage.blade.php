@extends('admin.layouts.app')

@section('content')
<div class="memories-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <a href="{{ route('admin.memories.users') }}" class="text-decoration-none text-reset fw-bold">Memories</a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">{{ $filterUser ? '@'.$filterUser->username : 'All Memories' }}</span>
    </nav>

    <div class="mb-4 d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">
                @if ($filterUser)
                    Memories by {{ $filterUser->full_name ?: '@'.$filterUser->username }}
                @else
                    All Memories
                @endif
            </h1>
            <p class="text-muted small mb-0">
                @if ($filterUser)
                    All memories for {{ '@'.$filterUser->username }} (including private). Media opens on View.
                @else
                    Flat list of every memory. Prefer <a href="{{ route('admin.memories.users') }}">By User</a> to avoid duplicate names.
                @endif
            </p>
        </div>
        @if ($filterUser)
            <a href="{{ route('admin.memories.users') }}" class="btn btn-outline-dynamic rounded-3">
                <i class="bi bi-people me-2"></i>Back to users
            </a>
        @endif
    </div>

    @include('admin.memories.partials.tabs')

    <form method="GET" action="{{ route('admin.memories.memories') }}" class="glass rounded-4 p-3 d-flex flex-wrap align-items-center gap-3 mb-4" data-live-search="0">
        @if ($filterUser)
            <input type="hidden" name="user_id" value="{{ $filterUser->id }}">
        @endif
        <div class="position-relative flex-grow-1" style="min-width: 250px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control ps-5 border-0 bg-dark-soft rounded-3" placeholder="Search title, user, city..." data-live-search-min="3" autocomplete="off">
        </div>

        <select name="status" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Status</option>
            @foreach (['active' => 'Active', 'archived' => 'Archived', 'deleted' => 'Deleted'] as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <select name="visibility" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Visibility</option>
            @foreach (['public' => 'Public', 'private' => 'Private', 'followers_only' => 'Followers Only'] as $value => $label)
                <option value="{{ $value }}" @selected(request('visibility') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <button class="btn btn-outline-dynamic rounded-3 px-3" type="submit"><i class="bi bi-funnel me-2"></i>Filter</button>
        <a href="{{ $filterUser ? route('admin.memories.memories', ['user_id' => $filterUser->id]) : route('admin.memories.memories') }}" class="btn btn-outline-dynamic rounded-3 px-3"><i class="bi bi-arrow-clockwise me-2"></i>Reset</a>
    </form>

    <div class="glass rounded-4 overflow-hidden border-white-05">
        <div class="table-responsive scrollbar-hidden">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-white-05">
                    <tr>
                        <th class="ps-4 text-muted small fw-bold text-uppercase py-3">Memory</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">User</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Location</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Stats</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Visibility</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Status</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Date</th>
                        <th class="text-muted small fw-bold text-uppercase py-3 pe-4 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($memories as $memory)
                        @php $preview = $memory->media->first(); @endphp
                        <tr class="border-white-05">
                            <td class="ps-4">
                                <div class="d-flex align-items-center gap-3">
                                    @if ($preview)
                                        @include('admin.memories.partials.media-preview', ['media' => $preview])
                                    @else
                                        <div class="rounded-3 border border-white-05 d-flex align-items-center justify-content-center text-muted" style="width: 72px; height: 72px;">
                                            <i class="bi bi-image"></i>
                                        </div>
                                    @endif
                                    <div>
                                        <a href="{{ route('admin.memories.show', $memory) }}" class="fw-bold small text-decoration-none text-reset">
                                            #{{ $memory->id }} · {{ $memory->title }}
                                        </a>
                                        <div class="text-muted extra-small text-truncate" style="max-width: 280px;">{{ $memory->description ?: 'No description' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if ($memory->user)
                                    <a href="{{ route('admin.memories.memories', ['user_id' => $memory->user_id]) }}" class="small fw-semibold text-decoration-none">
                                        {{ $memory->user->full_name ?: '@'.$memory->user->username }}
                                    </a>
                                    <div class="text-muted extra-small">{{ '@'.$memory->user->username }}</div>
                                @else
                                    <span class="text-muted small">Unknown</span>
                                @endif
                            </td>
                            <td><span class="text-muted small">{{ collect([$memory->location_name, $memory->city, $memory->country])->filter()->implode(', ') ?: '—' }}</span></td>
                            <td><span class="text-muted small">{{ $memory->media_count }} media · {{ $memory->like_count }} likes · {{ $memory->save_count }} saves</span></td>
                            <td><span class="badge bg-info-soft text-info rounded-pill">{{ str($memory->visibility)->replace('_', ' ')->title() }}</span></td>
                            <td><span class="badge {{ $memory->status === 'active' ? 'bg-success-soft text-success' : 'bg-warning-soft text-warning' }} rounded-pill">{{ str($memory->status)->title() }}</span></td>
                            <td><span class="text-muted small">{{ $memory->memory_date?->format('M d, Y') ?? '—' }}</span></td>
                            <td class="pe-4 text-end">
                                <a href="{{ route('admin.memories.show', $memory) }}" class="btn btn-sm btn-outline-dynamic rounded-3">
                                    <i class="bi bi-eye me-1"></i>View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center py-5 text-muted">No memories found for the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 bg-white-05 border-top border-white-05 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="text-muted small">
                Showing <span class="text-emphasis-dynamic fw-bold">{{ $memories->firstItem() ?? 0 }}-{{ $memories->lastItem() ?? 0 }}</span>
                of <span class="text-emphasis-dynamic fw-bold">{{ number_format($memories->total()) }}</span> memories
            </div>
            {{ $memories->links() }}
        </div>
    </div>
</div>
@endsection
