@extends('admin.layouts.app')

@section('content')
<div class="engagement-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold">Engagement</span>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">Shares</span>
    </nav>
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">Shares</h1>
            <p class="text-muted small mb-0">Post share events across app channels.</p>
        </div>
    </div>
    @include('admin.engagement.partials.tabs')
    <form method="GET" action="{{ route('admin.engagement.shares') }}" class="glass rounded-4 p-3 d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="position-relative flex-grow-1" style="min-width: 250px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control ps-5 border-0 bg-dark-soft rounded-3" placeholder="Search by user, caption, or share ID...">
        </div>
        <select name="share_channel" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Channels</option>
            @foreach (['copy_link' => 'Copy Link', 'direct_message' => 'Direct Message', 'story' => 'Story', 'external' => 'External'] as $value => $label)
                <option value="{{ $value }}" @selected(request('share_channel') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="btn btn-outline-dynamic rounded-3 px-3" type="submit"><i class="bi bi-funnel me-2"></i>Filter</button>
        <a href="{{ route('admin.engagement.shares') }}" class="btn btn-outline-dynamic rounded-3 px-3"><i class="bi bi-arrow-clockwise me-2"></i>Reset</a>
    </form>
    <div class="glass rounded-4 overflow-hidden border-white-05">
        <div class="table-responsive scrollbar-hidden">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-white-05">
                    <tr>
                        <th class="ps-4 text-muted small fw-bold text-uppercase py-3">Share</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">User</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Post</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Channel</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Target</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Created</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($shares as $share)
                        <tr class="border-white-05">
                            <td class="ps-4"><div class="fw-bold small">#{{ $share->id }}</div></td>
                            <td>
                                @if ($share->user)
                                    <a href="{{ route('admin.users.show', $share->user->id) }}" class="text-decoration-none text-reset small fw-semibold">{{ $share->user->full_name ?: '@'.$share->user->username }}</a>
                                @else
                                    <span class="text-muted small">User unavailable</span>
                                @endif
                            </td>
                            <td style="min-width: 280px;">
                                @if ($share->post)
                                    <a href="{{ route('admin.posts.show', $share->post->id) }}" class="text-decoration-none text-reset">
                                        <div class="small fw-semibold">{{ \Illuminate\Support\Str::limit($share->post->caption ?: 'Untitled post', 80) }}</div>
                                        <div class="text-muted extra-small">Post #{{ $share->post->id }}</div>
                                    </a>
                                @else
                                    <span class="text-muted small">Post unavailable</span>
                                @endif
                            </td>
                            <td><span class="badge bg-info-soft text-info rounded-pill">{{ str($share->share_channel)->replace('_', ' ')->title() }}</span></td>
                            <td>
                                @if ($share->targetUser)
                                    <a href="{{ route('admin.users.show', $share->targetUser->id) }}" class="text-decoration-none text-reset small">{{ $share->targetUser->full_name ?: '@'.$share->targetUser->username }}</a>
                                @else
                                    <span class="text-muted small">No target user</span>
                                @endif
                            </td>
                            <td><span class="text-muted small">{{ $share->created_at?->format('M d, Y H:i') ?? '-' }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center py-5 text-muted">No shares found for the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 bg-white-05 border-top border-white-05 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="text-muted small">Showing <span class="text-emphasis-dynamic fw-bold">{{ $shares->firstItem() ?? 0 }}-{{ $shares->lastItem() ?? 0 }}</span> of <span class="text-emphasis-dynamic fw-bold">{{ number_format($shares->total()) }}</span> shares</div>
            {{ $shares->links() }}
        </div>
    </div>
</div>
@endsection
