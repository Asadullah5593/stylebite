@extends('admin.layouts.app')

@section('content')
@component('admin.users.partials.secondary-table', [
    'title' => 'Password Resets',
    'action' => route('admin.users.password_resets'),
    'headers' => ['User', 'Email', 'IP Address', 'Created', 'Expires', 'Used', 'Status'],
    'paginator' => $passwordResets,
])
    @slot('filters')
        <select name="status" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Status</option>
            <option value="pending" @selected(request('status') === 'pending')>Pending</option>
            <option value="used" @selected(request('status') === 'used')>Used</option>
        </select>
    @endslot

    @slot('rows')
        @forelse ($passwordResets as $reset)
            <tr class="border-white-05">
                <td class="ps-4">
                    @if ($reset->user)
                        <a href="{{ route('admin.users.show', $reset->user) }}" class="text-decoration-none">{{ '@'.$reset->user->username }}</a>
                    @else
                        <span class="text-muted small">Removed account</span>
                    @endif
                </td>
                <td><span class="text-muted small">{{ $reset->email }}</span></td>
                <td><span class="font-monospace text-muted small">{{ $reset->ip_address ?: '—' }}</span></td>
                <td><span class="text-muted small">{{ $reset->created_at?->format('M d, Y H:i') ?? '—' }}</span></td>
                <td><span class="text-muted small">{{ $reset->expires_at?->format('M d, Y H:i') ?? '—' }}</span></td>
                <td><span class="text-muted small">{{ $reset->used_at?->format('M d, Y H:i') ?? '—' }}</span></td>
                <td class="pe-4"><span class="badge {{ $reset->used_at ? 'bg-success-soft text-success' : 'bg-warning-soft text-warning' }} rounded-pill">{{ $reset->used_at ? 'Used' : 'Pending' }}</span></td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center py-5 text-muted">No password reset records found.</td></tr>
        @endforelse
    @endslot
@endcomponent
@endsection
