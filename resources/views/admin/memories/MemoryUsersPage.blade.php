@extends('admin.layouts.app')

@section('content')
<div class="memories-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold">Memories</span>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">By User</span>
    </nav>

    <div class="mb-4">
        <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">Memories by User</h1>
        <p class="text-muted small mb-0">One row per creator. Open a user to see all of their memories and media.</p>
    </div>

    @include('admin.memories.partials.tabs')

    <form method="GET" action="{{ route('admin.memories.users') }}" class="glass rounded-4 p-3 d-flex flex-wrap align-items-center gap-3 mb-4" data-live-search="0">
        <div class="position-relative flex-grow-1" style="min-width: 250px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control ps-5 border-0 bg-dark-soft rounded-3" placeholder="Search username or name..." data-live-search-min="3" autocomplete="off">
        </div>
        <button class="btn btn-outline-dynamic rounded-3 px-3" type="submit"><i class="bi bi-funnel me-2"></i>Filter</button>
        <a href="{{ route('admin.memories.users') }}" class="btn btn-outline-dynamic rounded-3 px-3"><i class="bi bi-arrow-clockwise me-2"></i>Reset</a>
    </form>

    <div class="glass rounded-4 overflow-hidden border-white-05">
        <div class="table-responsive scrollbar-hidden">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-white-05">
                    <tr>
                        <th class="ps-4 text-muted small fw-bold text-uppercase py-3">User</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Latest memory date</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Count</th>
                        <th class="text-muted small fw-bold text-uppercase py-3 pe-4 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr class="border-white-05">
                            <td class="ps-4">
                                <div class="small fw-semibold">{{ $user->full_name ?: '@'.$user->username }}</div>
                                <div class="text-muted extra-small">{{ '@'.$user->username }}</div>
                            </td>
                            <td>
                                <span class="text-muted small">
                                    {{ $user->latest_memory_at ? \Illuminate\Support\Carbon::parse($user->latest_memory_at)->format('M d, Y') : '—' }}
                                </span>
                            </td>
                            <td><span class="badge bg-info-soft text-info rounded-pill">{{ number_format($user->memories_count) }}</span></td>
                            <td class="pe-4 text-end">
                                <a href="{{ route('admin.memories.memories', ['user_id' => $user->id]) }}" class="btn btn-sm btn-outline-dynamic rounded-3">
                                    <i class="bi bi-journal-richtext me-1"></i>Open memories
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center py-5 text-muted">No users with memories found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 bg-white-05 border-top border-white-05 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="text-muted small">
                Showing <span class="text-emphasis-dynamic fw-bold">{{ $users->firstItem() ?? 0 }}-{{ $users->lastItem() ?? 0 }}</span>
                of <span class="text-emphasis-dynamic fw-bold">{{ number_format($users->total()) }}</span> users
            </div>
            {{ $users->links() }}
        </div>
    </div>
</div>
@endsection
