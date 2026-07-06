@extends('admin.layouts.app')

@section('content')
<div class="memories-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold">Memories</span>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">Memory Comments</span>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">Memory Comments</h1>
            <p class="text-muted small mb-0">Comment review and moderation for memory posts.</p>
        </div>
    </div>

    @include('admin.memories.partials.tabs')

    @if (session('status'))
        <div class="alert alert-success rounded-3 border-0 mb-4">{{ session('status') }}</div>
    @endif

    <form method="GET" action="{{ route('admin.memories.memory-comments') }}" class="glass rounded-4 p-3 d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="position-relative flex-grow-1" style="min-width: 250px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control ps-5 border-0 bg-dark-soft rounded-3" placeholder="Search comment, memory, or user...">
        </div>

        <select name="status" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Statuses</option>
            @foreach (['active' => 'Active', 'hidden' => 'Hidden', 'deleted' => 'Deleted', 'blocked' => 'Blocked'] as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <select name="reported" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Report Flags</option>
            <option value="1" @selected(request('reported') === '1')>Reported Only</option>
            <option value="0" @selected(request('reported') === '0')>Not Reported</option>
        </select>

        <button class="btn btn-outline-dynamic rounded-3 px-3" type="submit"><i class="bi bi-funnel me-2"></i>Filter</button>
        <a href="{{ route('admin.memories.memory-comments') }}" class="btn btn-outline-dynamic rounded-3 px-3"><i class="bi bi-arrow-clockwise me-2"></i>Reset</a>
    </form>

    <div class="glass rounded-4 overflow-hidden border-white-05">
        <div class="table-responsive scrollbar-hidden">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-white-05">
                    <tr>
                        <th class="ps-4 text-muted small fw-bold text-uppercase py-3">Comment</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">User</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Memory</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Status</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Flags</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Created</th>
                        <th class="text-muted small fw-bold text-uppercase py-3 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($memoryComments as $comment)
                        @php
                            $statusClass = match ($comment->status) {
                                'active' => 'bg-success-soft text-success',
                                'hidden' => 'bg-warning-soft text-warning',
                                'blocked' => 'bg-danger-soft text-danger',
                                default => 'bg-secondary-soft text-muted',
                            };
                        @endphp
                        <tr class="border-white-05">
                            <td class="ps-4" style="min-width: 300px;">
                                <div class="fw-bold small">#{{ $comment->id }}</div>
                                <div class="small text-muted mt-1">{{ \Illuminate\Support\Str::limit($comment->body, 120) }}</div>
                            </td>
                            <td>
                                @if ($comment->user)
                                    <a href="{{ route('admin.users.show', $comment->user->id) }}" class="text-decoration-none text-reset small fw-semibold">{{ $comment->user->full_name ?: '@'.$comment->user->username }}</a>
                                @else
                                    <span class="text-muted small">User unavailable</span>
                                @endif
                            </td>
                            <td>
                                @if ($comment->memory)
                                    <div class="small fw-semibold">{{ $comment->memory->title ?: 'Untitled memory' }}</div>
                                    <div class="text-muted extra-small">Memory #{{ $comment->memory->id }}</div>
                                @else
                                    <span class="text-muted small">Memory unavailable</span>
                                @endif
                            </td>
                            <td><span class="badge {{ $statusClass }} rounded-pill">{{ str($comment->status)->title() }}</span></td>
                            <td>
                                <div class="d-flex flex-column gap-1">
                                    <span class="badge {{ $comment->is_reported ? 'bg-danger-soft text-danger' : 'bg-secondary-soft text-muted' }} rounded-pill">{{ $comment->is_reported ? 'Reported' : 'Not Reported' }}</span>
                                    <span class="badge {{ $comment->is_blocked ? 'bg-danger-soft text-danger' : 'bg-secondary-soft text-muted' }} rounded-pill">{{ $comment->is_blocked ? 'Blocked' : 'Open' }}</span>
                                </div>
                            </td>
                            <td><span class="text-muted small">{{ $comment->created_at?->format('M d, Y H:i') ?? '-' }}</span></td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('admin.memories.memory-comments.update', $comment) }}" class="d-inline-flex gap-2 align-items-center justify-content-end flex-wrap">
                                    @csrf
                                    @method('PATCH')
                                    <select name="status" class="form-select form-select-sm border-0 bg-dark-soft rounded-3 text-muted" style="width: auto; min-width: 120px;">
                                        @foreach (['active' => 'Active', 'hidden' => 'Hidden', 'deleted' => 'Deleted', 'blocked' => 'Blocked'] as $value => $label)
                                            <option value="{{ $value }}" @selected($comment->status === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-outline-dynamic rounded-3 px-3">
                                        <i class="bi bi-check2-circle me-1"></i>Update
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center py-5 text-muted">No memory comments found for the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 bg-white-05 border-top border-white-05 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="text-muted small">Showing <span class="text-emphasis-dynamic fw-bold">{{ $memoryComments->firstItem() ?? 0 }}-{{ $memoryComments->lastItem() ?? 0 }}</span> of <span class="text-emphasis-dynamic fw-bold">{{ number_format($memoryComments->total()) }}</span> memory comments</div>
            {{ $memoryComments->links() }}
        </div>
    </div>
</div>
@endsection
