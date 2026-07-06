@extends('admin.layouts.app')

@section('content')
@component('admin.users.partials.secondary-table', [
    'title' => 'User Profiles',
    'action' => route('admin.users.profiles'),
    'headers' => ['Profile', 'User', 'Location', 'Visibility', 'Stats', 'Updated'],
    'paginator' => $profiles,
])
    @slot('filters')
        <select name="visibility" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Visibility</option>
            <option value="public" @selected(request('visibility') === 'public')>Public</option>
            <option value="private" @selected(request('visibility') === 'private')>Private</option>
            <option value="followers_only" @selected(request('visibility') === 'followers_only')>Followers Only</option>
        </select>
    @endslot

    @slot('rows')
        @forelse ($profiles as $profile)
            <tr class="border-white-05">
                <td class="ps-4">
                    <div class="fw-bold small">{{ $profile->display_name ?: ($profile->user?->full_name ?: $profile->user?->username) }}</div>
                    <div class="text-muted extra-small text-truncate" style="max-width: 300px;">{{ $profile->bio ?: 'No bio added' }}</div>
                </td>
                <td>
                    @if ($profile->user)
                        <a href="{{ route('admin.users.show', $profile->user) }}" class="text-decoration-none">{{ '@'.$profile->user->username }}</a>
                    @else
                        <span class="text-muted small">Removed account</span>
                    @endif
                </td>
                <td><span class="text-muted small">{{ collect([$profile->city, $profile->country])->filter()->implode(', ') ?: '—' }}</span></td>
                <td><span class="badge {{ $profile->visibility === 'public' ? 'bg-success-soft text-success' : 'bg-warning-soft text-warning' }} rounded-pill">{{ str($profile->visibility)->replace('_', ' ')->title() }}</span></td>
                <td><span class="text-muted small">{{ number_format($profile->post_count ?? 0) }} posts · {{ number_format($profile->follower_count ?? 0) }} followers</span></td>
                <td class="pe-4"><span class="text-muted small">{{ $profile->updated_at?->format('M d, Y') }}</span></td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-center py-5 text-muted">No profiles found.</td></tr>
        @endforelse
    @endslot
@endcomponent
@endsection
