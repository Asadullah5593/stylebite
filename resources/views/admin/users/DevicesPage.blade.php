@extends('admin.layouts.app')

@section('content')
@component('admin.users.partials.secondary-table', [
    'title' => 'Device Tokens',
    'action' => route('admin.users.devices'),
    'headers' => ['User', 'Device ID', 'Platform', 'Version', 'Token', 'Last Used', 'Status'],
    'paginator' => $devices,
])
    @slot('filters')
        <select name="platform" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Platforms</option>
            @foreach (['ios' => 'iOS', 'android' => 'Android', 'web' => 'Web'] as $value => $label)
                <option value="{{ $value }}" @selected(request('platform') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="active" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Status</option>
            <option value="1" @selected(request('active') === '1')>Active</option>
            <option value="0" @selected(request('active') === '0')>Inactive</option>
        </select>
    @endslot

    @slot('rows')
        @forelse ($devices as $device)
            <tr class="border-white-05">
                <td class="ps-4">
                    @if ($device->user)
                        <a href="{{ route('admin.users.show', $device->user) }}" class="text-decoration-none">{{ '@'.$device->user->username }}</a>
                    @else
                        <span class="text-muted small">Removed account</span>
                    @endif
                </td>
                <td><span class="font-monospace text-muted small">{{ $device->device_id ?: '—' }}</span></td>
                <td><span class="badge bg-info-soft text-info rounded-pill text-uppercase">{{ $device->platform ?: 'unknown' }}</span></td>
                <td><span class="text-muted small">{{ $device->app_version ?: '—' }}</span></td>
                <td><span class="font-monospace text-muted small">{{ str($device->push_token ?? '')->limit(22) ?: '—' }}</span></td>
                <td><span class="text-muted small">{{ $device->last_used_at?->diffForHumans() ?? 'Never' }}</span></td>
                <td class="pe-4"><span class="badge {{ $device->is_active ? 'bg-success-soft text-success' : 'bg-secondary-soft text-muted' }} rounded-pill">{{ $device->is_active ? 'Active' : 'Inactive' }}</span></td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center py-5 text-muted">No devices found.</td></tr>
        @endforelse
    @endslot
@endcomponent
@endsection
