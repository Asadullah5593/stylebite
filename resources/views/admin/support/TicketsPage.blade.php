@extends('admin.layouts.app')

@section('content')
<div class="users-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold">Support Tickets</span>
    </nav>

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <div>
            <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">Support Tickets</h1>
            <p class="text-muted small mb-0">User support, bug reports and content appeals</p>
        </div>
    </div>

    @if (session('status'))
        <div class="glass rounded-4 p-3 mb-4 border border-primary-soft bg-primary-soft-opaque">
            <i class="bi bi-check-circle me-2 text-success"></i>{{ session('status') }}
        </div>
    @endif

    <div class="row g-3 mb-4">
        @foreach ([
            ['label' => 'Waiting on us', 'value' => $stats['needs_reply'], 'icon' => 'bi-reply', 'query' => ['needs_reply' => 1]],
            ['label' => 'Unassigned', 'value' => $stats['unassigned'], 'icon' => 'bi-person-dash', 'query' => ['assigned' => 'none']],
            ['label' => 'Urgent', 'value' => $stats['urgent'], 'icon' => 'bi-exclamation-triangle', 'query' => ['priority' => 'urgent']],
            ['label' => 'Open total', 'value' => $stats['open'], 'icon' => 'bi-inbox', 'query' => []],
        ] as $tile)
            <div class="col-6 col-lg-3">
                <a href="{{ route('admin.support.index', $tile['query']) }}" class="text-decoration-none text-reset">
                    <div class="glass rounded-4 p-3 detail-tile border border-white-05 h-100">
                        <i class="bi {{ $tile['icon'] }} text-primary mb-2 d-block"></i>
                        <div class="text-muted extra-small text-uppercase fw-bold">{{ $tile['label'] }}</div>
                        <div class="h4 fw-bold text-emphasis-dynamic mb-0">{{ number_format($tile['value']) }}</div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    <form method="GET" action="{{ route('admin.support.index') }}" class="glass rounded-4 p-3 d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="position-relative flex-grow-1" style="min-width: 240px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control ps-5 border-0 bg-dark-soft rounded-3" placeholder="Search subject, reference, user...">
        </div>

        <select name="status" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Statuses</option>
            @foreach (\App\Models\SupportTicket::STATUSES as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <select name="category" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Categories</option>
            @foreach (\App\Models\SupportTicket::CATEGORIES as $value => $label)
                <option value="{{ $value }}" @selected(request('category') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <select name="priority" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Priorities</option>
            @foreach (\App\Models\SupportTicket::PRIORITIES as $value => $label)
                <option value="{{ $value }}" @selected(request('priority') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <select name="assigned" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">Anyone</option>
            <option value="me" @selected(request('assigned') === 'me')>Assigned to me</option>
            <option value="none" @selected(request('assigned') === 'none')>Unassigned</option>
        </select>

        <div class="form-check form-switch text-muted small">
            <input class="form-check-input" type="checkbox" role="switch" id="needsReply" name="needs_reply" value="1" @checked(request()->boolean('needs_reply'))>
            <label class="form-check-label" for="needsReply">Waiting on us</label>
        </div>

        <button class="btn btn-outline-dynamic rounded-3 px-3" type="submit"><i class="bi bi-funnel me-2"></i>Filter</button>
        <a href="{{ route('admin.support.index') }}" class="btn btn-outline-dynamic rounded-3 px-3"><i class="bi bi-arrow-clockwise me-2"></i>Reset</a>
    </form>

    <div class="glass rounded-4 overflow-hidden border-white-05">
        <div class="table-responsive scrollbar-hidden">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-white-05">
                    <tr>
                        <th class="ps-4 text-muted small fw-bold text-uppercase py-3">Ticket</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">User</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Category</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Status</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Priority</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Assignee</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Last reply</th>
                        <th class="text-end pe-4 text-muted small fw-bold text-uppercase py-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tickets as $ticket)
                        @php
                            $statusClass = match ($ticket->status) {
                                'open' => 'bg-danger-soft text-danger',
                                'in_progress' => 'bg-info-soft text-info',
                                'waiting_on_user' => 'bg-warning-soft text-warning',
                                'resolved' => 'bg-success-soft text-success',
                                default => 'bg-secondary-soft text-muted',
                            };
                            $priorityClass = match ($ticket->priority) {
                                'urgent' => 'text-danger',
                                'high' => 'text-warning',
                                'low' => 'text-muted',
                                default => 'text-info',
                            };
                        @endphp
                        <tr class="border-white-05">
                            <td class="ps-4">
                                <div class="fw-bold small">{{ $ticket->reference }}</div>
                                <div class="text-muted extra-small text-truncate" style="max-width: 260px;">{{ $ticket->subject }}</div>
                                @if ($ticket->last_reply_by === 'user' && ! $ticket->isFinished())
                                    <span class="badge bg-danger-soft text-danger rounded-pill mt-1" style="font-size: 0.6rem;">Needs reply</span>
                                @endif
                            </td>
                            <td>
                                @if ($ticket->user)
                                    <a href="{{ route('admin.users.show', $ticket->user) }}" class="text-decoration-none">
                                        <div class="small fw-semibold">{{ $ticket->user->full_name ?: $ticket->user->username }}</div>
                                        <div class="text-muted extra-small">{{ '@'.$ticket->user->username }}</div>
                                    </a>
                                @else
                                    <span class="text-muted small">Deleted user</span>
                                @endif
                            </td>
                            <td><span class="text-muted small">{{ $ticket->categoryLabel() }}</span></td>
                            <td><span class="badge {{ $statusClass }} rounded-pill px-3 py-1 fw-bold text-uppercase" style="font-size: 0.62rem;">{{ $ticket->statusLabel() }}</span></td>
                            <td><span class="small fw-bold {{ $priorityClass }} text-capitalize">{{ $ticket->priority }}</span></td>
                            <td>
                                @if ($ticket->assignee)
                                    <span class="text-muted small">{{ $ticket->assignee->full_name ?: $ticket->assignee->username }}</span>
                                @else
                                    <span class="text-muted extra-small fst-italic">Unassigned</span>
                                @endif
                            </td>
                            <td>
                                <div class="text-muted small">{{ $ticket->last_reply_at?->diffForHumans() ?? '-' }}</div>
                                <div class="text-muted extra-small">by {{ $ticket->last_reply_by }}</div>
                            </td>
                            <td class="text-end pe-4">
                                <div class="d-flex justify-content-end gap-1">
                                    <a href="{{ route('admin.support.show', $ticket) }}" class="btn btn-sm btn-outline-dynamic rounded-3">
                                        <i class="bi bi-chat-left-text me-1"></i>Open
                                    </a>
                                    @can('tickets.manage')
                                        @unless ($ticket->assigned_to_user_id === auth()->id())
                                            <form method="POST" action="{{ route('admin.support.claim', $ticket) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button class="btn btn-sm btn-outline-dynamic rounded-3" type="submit" title="Assign to me">
                                                    <i class="bi bi-person-check"></i>
                                                </button>
                                            </form>
                                        @endunless
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center py-5 text-muted">No tickets match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 bg-white-05 border-top border-white-05 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="text-muted small">
                Showing <span class="text-emphasis-dynamic fw-bold">{{ $tickets->firstItem() ?? 0 }}-{{ $tickets->lastItem() ?? 0 }}</span>
                of <span class="text-emphasis-dynamic fw-bold">{{ number_format($tickets->total()) }}</span> tickets
            </div>
            {{ $tickets->links() }}
        </div>
    </div>

    <div class="glass rounded-4 p-3 mt-4 border border-white-05 text-muted small">
        <i class="bi bi-info-circle me-2"></i>
        Tickets are sorted by priority then most recent reply. <span class="fw-bold">Bug reports arrive here</span>, not in the moderation queue — they carry the app version and device the user was on. Content appeals also land here, so a banned user has a route to contest a decision.
    </div>
</div>
@endsection
