@extends('admin.layouts.app')

@section('content')
<div class="earnings-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold">Earnings</span>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">Withdrawals</span>
    </nav>

    <div class="mb-4">
        <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">Earnings</h1>
        <p class="text-muted small mb-0">Withdrawal requests with payout review controls and account references</p>
    </div>

    @include('admin.earnings.partials.tabs')

    @if (session('status'))
        <div class="alert alert-success rounded-3 border-0 mb-4">{{ session('status') }}</div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-2">
            <div class="glass rounded-4 p-3 h-100">
                <div class="text-muted small">Total</div>
                <div class="fs-5 fw-bold">{{ number_format($withdrawalStats['total'] ?? 0) }}</div>
            </div>
        </div>
        <div class="col-6 col-xl-2">
            <div class="glass rounded-4 p-3 h-100">
                <div class="text-muted small">Pending</div>
                <div class="fs-5 fw-bold">{{ number_format($withdrawalStats['pending'] ?? 0) }}</div>
            </div>
        </div>
        <div class="col-6 col-xl-2">
            <div class="glass rounded-4 p-3 h-100">
                <div class="text-muted small">Processing</div>
                <div class="fs-5 fw-bold">{{ number_format($withdrawalStats['processing'] ?? 0) }}</div>
            </div>
        </div>
        <div class="col-6 col-xl-2">
            <div class="glass rounded-4 p-3 h-100">
                <div class="text-muted small">Completed</div>
                <div class="fs-5 fw-bold">{{ number_format($withdrawalStats['completed'] ?? 0) }}</div>
            </div>
        </div>
        <div class="col-6 col-xl-2">
            <div class="glass rounded-4 p-3 h-100">
                <div class="text-muted small">Failed</div>
                <div class="fs-5 fw-bold">{{ number_format($withdrawalStats['failed'] ?? 0) }}</div>
            </div>
        </div>
        <div class="col-6 col-xl-2">
            <div class="glass rounded-4 p-3 h-100">
                <div class="text-muted small">Rejected</div>
                <div class="fs-5 fw-bold">{{ number_format($withdrawalStats['rejected'] ?? 0) }}</div>
            </div>
        </div>
        <div class="col-6 col-xl-2">
            <div class="glass rounded-4 p-3 h-100">
                <div class="text-muted small">Missing Linked Txn</div>
                <div class="fs-5 fw-bold">{{ number_format($withdrawalStats['missing_transactions'] ?? 0) }}</div>
            </div>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.earnings.withdrawals') }}" class="glass rounded-4 p-3 d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="position-relative flex-grow-1" style="min-width: 250px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control ps-5 border-0 bg-dark-soft rounded-3" placeholder="Search user, account ref, failure reason...">
        </div>

        <select name="status" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Status</option>
            @foreach (['pending' => 'Pending', 'processing' => 'Processing', 'completed' => 'Completed', 'failed' => 'Failed', 'rejected' => 'Rejected'] as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <select name="method" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Methods</option>
            @foreach (['bank_transfer' => 'Bank Transfer', 'paypal' => 'PayPal', 'stripe' => 'Stripe', 'wallet' => 'Wallet'] as $value => $label)
                <option value="{{ $value }}" @selected(request('method') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <button class="btn btn-outline-dynamic rounded-3 px-3" type="submit"><i class="bi bi-funnel me-2"></i>Filter</button>
        <a href="{{ route('admin.earnings.withdrawals') }}" class="btn btn-outline-dynamic rounded-3 px-3"><i class="bi bi-arrow-clockwise me-2"></i>Reset</a>
        <a href="{{ route('admin.earnings.withdrawals.export', request()->query()) }}" class="btn btn-outline-dynamic rounded-3 px-3"><i class="bi bi-download me-2"></i>Export CSV</a>
    </form>

    <div class="glass rounded-4 overflow-hidden border-white-05">
        <div class="table-responsive scrollbar-hidden">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-white-05">
                    <tr>
                        <th class="ps-4 text-muted small fw-bold text-uppercase py-3">Request</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">User</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Method</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Amount</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Status</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Timing</th>
                        <th class="text-muted small fw-bold text-uppercase py-3 text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($withdrawals as $withdrawal)
                        @php $auditModalId = 'withdrawalAudit'.$withdrawal->id; @endphp
                        <tr class="border-white-05">
                            <td class="ps-4">
                                <div class="small fw-semibold">#{{ $withdrawal->id }}</div>
                                <div class="text-muted extra-small">{{ $withdrawal->account_ref ?: 'No account ref' }}</div>
                                @if ($withdrawal->failure_reason)
                                    <button class="btn btn-link btn-sm text-danger p-0 mt-1 text-decoration-none" type="button" data-bs-toggle="modal" data-bs-target="#withdrawalReason{{ $withdrawal->id }}">
                                        View failure note
                                    </button>
                                    <div class="modal fade" id="withdrawalReason{{ $withdrawal->id }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content bg-dark border border-white-10">
                                                <div class="modal-header border-white-10">
                                                    <h5 class="modal-title">Withdrawal #{{ $withdrawal->id }} note</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p class="mb-0 text-white">{{ $withdrawal->failure_reason }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if ($withdrawal->user)
                                    <a href="{{ route('admin.users.show', $withdrawal->user) }}" class="text-decoration-none">
                                        <div class="small fw-semibold">{{ $withdrawal->user->full_name ?: '@'.$withdrawal->user->username }}</div>
                                        <div class="text-muted extra-small">{{ '@'.$withdrawal->user->username }}</div>
                                    </a>
                                @else
                                    <span class="text-muted small">Missing user</span>
                                @endif
                            </td>
                            <td><span class="badge bg-secondary-soft text-muted rounded-pill">{{ str($withdrawal->method)->replace('_', ' ')->title() }}</span></td>
                            <td>
                                <div class="small fw-semibold">{{ number_format((float) $withdrawal->amount, 2) }} {{ $withdrawal->currency_code }}</div>
                                <div class="text-muted extra-small">Wallet #{{ $withdrawal->wallet_id }}</div>
                            </td>
                            <td><span class="badge {{ $withdrawal->status === 'completed' ? 'bg-success-soft text-success' : ($withdrawal->status === 'processing' ? 'bg-info-soft text-info' : ($withdrawal->status === 'pending' ? 'bg-warning-soft text-warning' : 'bg-danger-soft text-danger')) }} rounded-pill">{{ str($withdrawal->status)->title() }}</span></td>
                            <td>
                                <div class="text-muted small">{{ $withdrawal->requested_at?->format('M d, Y H:i') }}</div>
                                <div class="text-muted extra-small">{{ $withdrawal->processed_at?->format('M d, Y H:i') ? 'Processed '.$withdrawal->processed_at->format('M d, Y H:i') : 'Not processed yet' }}</div>
                            </td>
                            <td class="text-end" style="min-width: 280px;">
                                <button class="btn btn-sm btn-outline-dynamic rounded-3 px-3" type="button" data-bs-toggle="modal" data-bs-target="#{{ $auditModalId }}">
                                    <i class="bi bi-cash-coin me-1"></i>Audit
                                </button>

                                <div class="modal fade" id="{{ $auditModalId }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                        <div class="modal-content bg-dark border border-white-10">
                                            <div class="modal-header border-white-10">
                                                <h5 class="modal-title">Withdrawal Audit #{{ $withdrawal->id }}</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row g-3 mb-4">
                                                    <div class="col-md-6"><div class="small text-muted">User</div><div class="fw-semibold">{{ $withdrawal->user?->full_name ?: ($withdrawal->user?->username ? '@'.$withdrawal->user->username : 'Removed account') }}</div></div>
                                                    <div class="col-md-6"><div class="small text-muted">Method</div><div>{{ str($withdrawal->method)->replace('_', ' ')->title() }}</div></div>
                                                    <div class="col-md-4"><div class="small text-muted">Amount</div><div>{{ number_format((float) $withdrawal->amount, 2) }} {{ $withdrawal->currency_code }}</div></div>
                                                    <div class="col-md-4"><div class="small text-muted">Wallet Available</div><div>{{ number_format((float) ($withdrawal->wallet?->available_balance ?? 0), 2) }}</div></div>
                                                    <div class="col-md-4"><div class="small text-muted">Wallet Pending</div><div>{{ number_format((float) ($withdrawal->wallet?->pending_balance ?? 0), 2) }}</div></div>
                                                    <div class="col-md-6"><div class="small text-muted">Linked Transaction</div><div>{{ $withdrawal->payoutTransaction ? '#'.$withdrawal->payoutTransaction->id.' ('.str($withdrawal->payoutTransaction->status)->title().')' : 'Missing linked withdrawal transaction' }}</div></div>
                                                    <div class="col-md-6"><div class="small text-muted">Processed</div><div>{{ $withdrawal->processed_at?->format('M d, Y H:i') ?? 'Not processed yet' }}</div></div>
                                                    <div class="col-12"><div class="small text-muted">Account Reference</div><div>{{ $withdrawal->account_ref ?: 'No account reference provided' }}</div></div>
                                                </div>
                                                <form method="POST" action="{{ route('admin.earnings.withdrawals.update', $withdrawal) }}" class="d-grid gap-3">
                                                    @csrf
                                                    @method('PATCH')
                                                    <div>
                                                        <label class="form-label small text-muted">Update Status</label>
                                                        <select name="status" class="form-select border-0 bg-dark-soft rounded-3 text-muted">
                                                            @foreach (['pending' => 'Pending', 'processing' => 'Processing', 'completed' => 'Completed', 'failed' => 'Failed', 'rejected' => 'Rejected'] as $value => $label)
                                                                <option value="{{ $value }}" @selected($withdrawal->status === $value)>{{ $label }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="form-label small text-muted">Failure or Rejection Reason</label>
                                                        <textarea name="failure_reason" rows="3" class="form-control border-0 bg-dark-soft rounded-3 text-muted" placeholder="Required for failed or rejected requests">{{ $withdrawal->failure_reason }}</textarea>
                                                    </div>
                                                    <div class="d-flex justify-content-end gap-2">
                                                        <button type="button" class="btn btn-outline-light rounded-3 px-3" data-bs-dismiss="modal">Close</button>
                                                        <button type="submit" class="btn btn-primary rounded-3 px-3"><i class="bi bi-check2-circle me-1"></i>Save Update</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center py-5 text-muted">No withdrawals found for the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 bg-white-05 border-top border-white-05 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="text-muted small">
                Showing <span class="text-emphasis-dynamic fw-bold">{{ $withdrawals->firstItem() ?? 0 }}-{{ $withdrawals->lastItem() ?? 0 }}</span>
                of <span class="text-emphasis-dynamic fw-bold">{{ number_format($withdrawals->total()) }}</span> withdrawals
            </div>
            {{ $withdrawals->links() }}
        </div>
    </div>
</div>
@endsection
