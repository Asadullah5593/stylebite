@extends('admin.layouts.app')

@section('content')
<div class="contests-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold">Contests</span>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">Invitations</span>
    </nav>

    <div class="mb-4">
        <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">Contests</h1>
        <p class="text-muted small mb-0">Contest invites and join requests with response controls</p>
    </div>

    @include('admin.contests.partials.tabs')

    @if (session('status'))
        <div class="alert alert-success rounded-3 border-0 mb-4">{{ session('status') }}</div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-md-3 col-sm-6"><div class="glass rounded-4 p-3 h-100"><div class="text-muted small">Invitations</div><div class="fs-4 fw-bold">{{ number_format($invitationStats['total'] ?? 0) }}</div></div></div>
        <div class="col-md-3 col-sm-6"><div class="glass rounded-4 p-3 h-100"><div class="text-muted small">Pending</div><div class="fs-4 fw-bold">{{ number_format($invitationStats['pending'] ?? 0) }}</div></div></div>
        <div class="col-md-3 col-sm-6"><div class="glass rounded-4 p-3 h-100"><div class="text-muted small">Accepted</div><div class="fs-4 fw-bold">{{ number_format($invitationStats['accepted'] ?? 0) }}</div></div></div>
        <div class="col-md-3 col-sm-6"><div class="glass rounded-4 p-3 h-100"><div class="text-muted small">Expiring Soon</div><div class="fs-4 fw-bold">{{ number_format($invitationStats['expiring_soon'] ?? 0) }}</div></div></div>
    </div>

    <div class="glass rounded-4 p-3 mb-4 border border-white-05">
        <form method="POST" action="{{ route('admin.contests.invitations.store') }}" class="row g-3 align-items-end">
            @csrf
            <div class="col-md-4">
                <label class="form-label small fw-bold text-muted">Contest</label>
                <select name="contest_id" class="form-select border-0 bg-dark-soft rounded-3 text-muted">
                    <option value="">Select contest</option>
                    @foreach ($contestOptions as $contestOption)
                        <option value="{{ $contestOption->id }}">{{ $contestOption->title }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-bold text-muted">Receiver</label>
                <select name="receiver_user_id" class="form-select border-0 bg-dark-soft rounded-3 text-muted">
                    <option value="">Select receiver</option>
                    @foreach ($userOptions as $userOption)
                        <option value="{{ $userOption->id }}">{{ $userOption->full_name ?: '@'.$userOption->username }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-muted">Type</label>
                <select name="request_type" class="form-select border-0 bg-dark-soft rounded-3 text-muted">
                    <option value="invite">Invite</option>
                    <option value="join_request">Join Request</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-muted">Expiry</label>
                <input type="datetime-local" name="expires_at" class="form-control border-0 bg-dark-soft rounded-3 text-muted">
            </div>
            <div class="col-12 text-end">
                <button class="btn btn-primary rounded-3 px-4" type="submit">
                    <i class="bi bi-send-plus me-2"></i>Create Invitation
                </button>
            </div>
        </form>
    </div>

    <form method="GET" action="{{ route('admin.contests.invitations') }}" class="glass rounded-4 p-3 d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="position-relative flex-grow-1" style="min-width: 250px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control ps-5 border-0 bg-dark-soft rounded-3" placeholder="Search contest, sender, receiver, type...">
        </div>

        <select name="request_type" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Types</option>
            @foreach (['invite' => 'Invite', 'join_request' => 'Join Request'] as $value => $label)
                <option value="{{ $value }}" @selected(request('request_type') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <select name="status" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Status</option>
            @foreach (['pending' => 'Pending', 'accepted' => 'Accepted', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled'] as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <button class="btn btn-outline-dynamic rounded-3 px-3" type="submit"><i class="bi bi-funnel me-2"></i>Filter</button>
        <a href="{{ route('admin.contests.invitations') }}" class="btn btn-outline-dynamic rounded-3 px-3"><i class="bi bi-arrow-clockwise me-2"></i>Reset</a>
    </form>

    <div class="glass rounded-4 overflow-hidden border-white-05">
        <div class="table-responsive scrollbar-hidden">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-white-05">
                    <tr>
                        <th class="ps-4 text-muted small fw-bold text-uppercase py-3">Contest</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Sender</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Receiver</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Type</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Status</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Expiry</th>
                        <th class="text-muted small fw-bold text-uppercase py-3 text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($invitations as $invitation)
                        @php $invitationModalId = 'invitationReview'.$invitation->id; @endphp
                        <tr class="border-white-05">
                            <td class="ps-4">
                                <div class="small fw-semibold">{{ $invitation->contest?->title ?: 'Missing contest' }}</div>
                                <div class="text-muted extra-small">{{ str($invitation->contest?->contest_type ?: 'unknown')->replace('_', ' ')->title() }}</div>
                            </td>
                            <td>
                                @if ($invitation->sender)
                                    <a href="{{ route('admin.users.show', $invitation->sender) }}" class="text-decoration-none">
                                        <div class="small fw-semibold">{{ $invitation->sender->full_name ?: '@'.$invitation->sender->username }}</div>
                                        <div class="text-muted extra-small">{{ '@'.$invitation->sender->username }}</div>
                                    </a>
                                @else
                                    <span class="text-muted small">Missing sender</span>
                                @endif
                            </td>
                            <td>
                                @if ($invitation->receiver)
                                    <a href="{{ route('admin.users.show', $invitation->receiver) }}" class="text-decoration-none">
                                        <div class="small fw-semibold">{{ $invitation->receiver->full_name ?: '@'.$invitation->receiver->username }}</div>
                                        <div class="text-muted extra-small">{{ '@'.$invitation->receiver->username }}</div>
                                    </a>
                                @else
                                    <span class="text-muted small">Missing receiver</span>
                                @endif
                            </td>
                            <td><span class="badge bg-info-soft text-info rounded-pill">{{ str($invitation->request_type)->replace('_', ' ')->title() }}</span></td>
                            <td><span class="badge {{ $invitation->status === 'accepted' ? 'bg-success-soft text-success' : ($invitation->status === 'pending' ? 'bg-warning-soft text-warning' : 'bg-danger-soft text-danger') }} rounded-pill">{{ str($invitation->status)->title() }}</span></td>
                            <td>
                                <div class="text-muted small">{{ $invitation->expires_at?->format('M d, Y H:i') ?? '-' }}</div>
                                <div class="text-muted extra-small">{{ $invitation->responded_at?->format('M d, Y H:i') ? 'Responded '.$invitation->responded_at->format('M d, Y H:i') : 'Awaiting response' }}</div>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-dynamic rounded-3 px-3" type="button" data-bs-toggle="modal" data-bs-target="#{{ $invitationModalId }}">
                                    <i class="bi bi-envelope-open me-1"></i>Review
                                </button>

                                <div class="modal fade" id="{{ $invitationModalId }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                        <div class="modal-content bg-dark border border-white-10">
                                            <div class="modal-header border-white-10">
                                                <h5 class="modal-title">Invitation Review #{{ $invitation->id }}</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row g-3 mb-4">
                                                    <div class="col-md-6"><div class="small text-muted">Contest</div><div class="fw-semibold">{{ $invitation->contest?->title ?: 'Missing contest' }}</div></div>
                                                    <div class="col-md-6"><div class="small text-muted">Type</div><div>{{ str($invitation->request_type)->replace('_', ' ')->title() }}</div></div>
                                                    <div class="col-md-6"><div class="small text-muted">Sender</div><div>{{ $invitation->sender?->full_name ?: ($invitation->sender?->username ? '@'.$invitation->sender->username : 'Removed account') }}</div></div>
                                                    <div class="col-md-6"><div class="small text-muted">Receiver</div><div>{{ $invitation->receiver?->full_name ?: ($invitation->receiver?->username ? '@'.$invitation->receiver->username : 'Removed account') }}</div></div>
                                                    <div class="col-md-6"><div class="small text-muted">Expiry</div><div>{{ $invitation->expires_at?->format('M d, Y H:i') ?? 'No expiry' }}</div></div>
                                                    <div class="col-md-6"><div class="small text-muted">Responded</div><div>{{ $invitation->responded_at?->format('M d, Y H:i') ?? 'Awaiting response' }}</div></div>
                                                </div>
                                                <form method="POST" action="{{ route('admin.contests.invitations.update', $invitation) }}" class="d-grid gap-3">
                                                    @csrf
                                                    @method('PATCH')
                                                    <div>
                                                        <label class="form-label small text-muted">Update Status</label>
                                                        <select name="status" class="form-select border-0 bg-dark-soft rounded-3 text-muted">
                                                            @foreach (['pending' => 'Pending', 'accepted' => 'Accepted', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled'] as $value => $label)
                                                                <option value="{{ $value }}" @selected($invitation->status === $value)>{{ $label }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="d-flex justify-content-end gap-2">
                                                        <button type="button" class="btn btn-outline-light rounded-3 px-3" data-bs-dismiss="modal">Close</button>
                                                        <button type="submit" class="btn btn-primary rounded-3 px-3"><i class="bi bi-send-check me-1"></i>Save Status</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center py-5 text-muted">No invitations found for the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 bg-white-05 border-top border-white-05 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="text-muted small">
                Showing <span class="text-emphasis-dynamic fw-bold">{{ $invitations->firstItem() ?? 0 }}-{{ $invitations->lastItem() ?? 0 }}</span>
                of <span class="text-emphasis-dynamic fw-bold">{{ number_format($invitations->total()) }}</span> invitations
            </div>
            {{ $invitations->links() }}
        </div>
    </div>
</div>
@endsection
