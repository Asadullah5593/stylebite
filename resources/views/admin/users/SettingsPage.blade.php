@extends('admin.layouts.app')

@section('content')
@component('admin.users.partials.secondary-table', [
    'title' => 'User Settings',
    'action' => route('admin.users.settings'),
    'headers' => ['User', 'Language', 'Timezone', 'Theme', 'Notifications', 'Activity', 'Updated'],
    'paginator' => $settings,
])
    @slot('rows')
        @forelse ($settings as $setting)
            <tr class="border-white-05">
                <td class="ps-4">
                    @if ($setting->user)
                        <a href="{{ route('admin.users.show', $setting->user) }}" class="text-decoration-none">{{ '@'.$setting->user->username }}</a>
                    @else
                        <span class="text-muted small">Removed account</span>
                    @endif
                </td>
                <td><span class="text-muted small">{{ $setting->language ?: '—' }}</span></td>
                <td><span class="text-muted small">{{ $setting->timezone ?: '—' }}</span></td>
                <td><span class="badge {{ $setting->dark_mode ? 'bg-info-soft text-info' : 'bg-secondary-soft text-muted' }} rounded-pill">{{ $setting->dark_mode ? 'Dark' : 'Light' }}</span></td>
                <td><span class="text-muted small">Push {{ $setting->push_notifications_enabled ? 'on' : 'off' }} · Email {{ $setting->email_notifications_enabled ? 'on' : 'off' }}</span></td>
                <td><span class="text-muted small">{{ $setting->show_activity_status ? 'Visible' : 'Hidden' }}</span></td>
                <td class="pe-4"><span class="text-muted small">{{ $setting->updated_at?->format('M d, Y') }}</span></td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center py-5 text-muted">No settings found.</td></tr>
        @endforelse
    @endslot
@endcomponent
@endsection
