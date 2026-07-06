@extends('admin.layouts.app')

@section('content')
<div class="social-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold">Social Graph</span>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">Blocks</span>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">Social Graph</h1>
            <p class="text-muted small mb-0">Blocking relationships and safety notes between users.</p>
        </div>
    </div>

    @include('admin.social.partials.tabs')

    @if (session('status'))
        <div class="alert alert-success rounded-3 border-0 mb-4">{{ session('status') }}</div>
    @endif

    <form method="GET" action="{{ route('admin.social.blocks') }}" class="glass rounded-4 p-3 d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="position-relative flex-grow-1" style="min-width: 250px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control ps-5 border-0 bg-dark-soft rounded-3" placeholder="Search by user, reason, or block ID...">
        </div>

        <button class="btn btn-outline-dynamic rounded-3 px-3" type="submit"><i class="bi bi-funnel me-2"></i>Filter</button>
        <a href="{{ route('admin.social.blocks') }}" class="btn btn-outline-dynamic rounded-3 px-3"><i class="bi bi-arrow-clockwise me-2"></i>Reset</a>
    </form>

    <div class="glass rounded-4 overflow-hidden border-white-05">
        <div class="table-responsive scrollbar-hidden">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-white-05">
                    <tr>
                        <th class="ps-4 text-muted small fw-bold text-uppercase py-3">Block</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Blocker</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Blocked User</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Reason</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Created</th>
                        <th class="text-muted small fw-bold text-uppercase py-3 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($blocks as $block)
                        <tr class="border-white-05">
                            <td class="ps-4">
                                <div class="fw-bold small">#{{ $block->id }}</div>
                                <div class="text-muted extra-small">Safety record</div>
                            </td>
                            <td>
                                @if ($block->blocker)
                                    <a href="{{ route('admin.users.show', $block->blocker->id) }}" class="text-decoration-none text-reset d-flex align-items-center gap-3">
                                        <img src="{{ $block->blocker->avatar_url ?: 'https://ui-avatars.com/api/?name='.urlencode($block->blocker->full_name ?: $block->blocker->username ?: 'User').'&background=1f172a&color=ffffff' }}" alt="{{ $block->blocker->username }}" class="rounded-circle border border-white-05" width="42" height="42" style="object-fit: cover;">
                                        <div>
                                            <div class="small fw-semibold">{{ $block->blocker->full_name ?: 'User #'.$block->blocker->id }}</div>
                                            <div class="text-muted extra-small">{{ '@'.$block->blocker->username }}</div>
                                        </div>
                                    </a>
                                @else
                                    <span class="text-muted small">User unavailable</span>
                                @endif
                            </td>
                            <td>
                                @if ($block->blocked)
                                    <a href="{{ route('admin.users.show', $block->blocked->id) }}" class="text-decoration-none text-reset d-flex align-items-center gap-3">
                                        <img src="{{ $block->blocked->avatar_url ?: 'https://ui-avatars.com/api/?name='.urlencode($block->blocked->full_name ?: $block->blocked->username ?: 'User').'&background=1f172a&color=ffffff' }}" alt="{{ $block->blocked->username }}" class="rounded-circle border border-white-05" width="42" height="42" style="object-fit: cover;">
                                        <div>
                                            <div class="small fw-semibold">{{ $block->blocked->full_name ?: 'User #'.$block->blocked->id }}</div>
                                            <div class="text-muted extra-small">{{ '@'.$block->blocked->username }}</div>
                                        </div>
                                    </a>
                                @else
                                    <span class="text-muted small">User unavailable</span>
                                @endif
                            </td>
                            <td style="min-width: 220px;">
                                @if (filled($block->reason))
                                    <div class="small text-muted">{{ $block->reason }}</div>
                                @else
                                    <span class="text-muted small">No reason recorded</span>
                                @endif
                            </td>
                            <td><span class="text-muted small">{{ $block->created_at?->format('M d, Y H:i') ?? '-' }}</span></td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-2 flex-wrap justify-content-end">
                                    @if ($block->blocker)
                                        <a href="{{ route('admin.users.show', $block->blocker->id) }}" class="btn btn-sm btn-outline-dynamic rounded-3 px-3">
                                            <i class="bi bi-person me-1"></i>Blocker
                                        </a>
                                    @endif
                                    @if ($block->blocked)
                                        <a href="{{ route('admin.users.show', $block->blocked->id) }}" class="btn btn-sm btn-outline-dynamic rounded-3 px-3">
                                            <i class="bi bi-person-badge me-1"></i>Blocked
                                        </a>
                                    @endif
                                    <form method="POST" action="{{ route('admin.social.blocks.delete', $block) }}" onsubmit="return confirm('Remove this block record?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger rounded-3 px-3">
                                            <i class="bi bi-trash3 me-1"></i>Remove
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center py-5 text-muted">No block records found for the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 bg-white-05 border-top border-white-05 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="text-muted small">
                Showing <span class="text-emphasis-dynamic fw-bold">{{ $blocks->firstItem() ?? 0 }}-{{ $blocks->lastItem() ?? 0 }}</span>
                of <span class="text-emphasis-dynamic fw-bold">{{ number_format($blocks->total()) }}</span> block records
            </div>
            {{ $blocks->links() }}
        </div>
    </div>
</div>
@endsection
