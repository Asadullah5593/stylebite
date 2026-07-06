@extends('admin.layouts.app')

@section('content')
@component('admin.users.partials.secondary-table', [
    'title' => 'User Sessions',
    'action' => route('admin.users.sessions'),
    'headers' => ['User', 'Device', 'Platform', 'IP Address', 'Last Seen', 'Expires', 'Status'],
    'paginator' => $sessions,
])
    @slot('filters')
        <select name="platform" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Platforms</option>
            @foreach (['ios' => 'iOS', 'android' => 'Android', 'web' => 'Web', 'desktop' => 'Desktop'] as $value => $label)
                <option value="{{ $value }}" @selected(request('platform') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    @endslot

    @slot('rows')
        @forelse ($sessions as $session)
            <tr class="border-white-05">
                <td class="ps-4">
                    @if ($session->user)
                        <a href="{{ route('admin.users.show', $session->user) }}" class="text-decoration-none">{{ '@'.$session->user->username }}</a>
                    @else
                        <span class="text-muted small">Removed account</span>
                    @endif
                </td>
                <td><span class="fw-semibold small">{{ $session->device_name ?: 'Unknown device' }}</span></td>
                <td><span class="badge bg-secondary-soft text-muted rounded-pill text-uppercase">{{ $session->platform ?: 'unknown' }}</span></td>
                <td><span class="font-monospace text-muted small">{{ $session->ip_address ?: '—' }}</span></td>
                <td><span class="text-muted small">{{ $session->last_seen_at?->diffForHumans() ?? 'Never' }}</span></td>
                <td><span class="text-muted small">{{ $session->expires_at?->format('M d, Y H:i') ?? '—' }}</span></td>
                <td class="pe-4"><span class="badge {{ $session->revoked_at ? 'bg-danger-soft text-danger' : 'bg-success-soft text-success' }} rounded-pill">{{ $session->revoked_at ? 'Revoked' : 'Active' }}</span></td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center py-5 text-muted">No sessions found.</td></tr>
        @endforelse
    @endslot
@endcomponent
@endsection
