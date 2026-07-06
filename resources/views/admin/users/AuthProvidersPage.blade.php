@extends('admin.layouts.app')

@section('content')
@component('admin.users.partials.secondary-table', [
    'title' => 'Auth Providers',
    'action' => route('admin.users.auth_providers'),
    'headers' => ['User', 'Provider', 'Provider User ID', 'Provider Email', 'Expires', 'Created'],
    'paginator' => $providers,
])
    @slot('rows')
        @forelse ($providers as $provider)
            <tr class="border-white-05">
                <td class="ps-4">
                    @if ($provider->user)
                        <a href="{{ route('admin.users.show', $provider->user) }}" class="text-decoration-none">{{ '@'.$provider->user->username }}</a>
                    @else
                        <span class="text-muted small">Removed account</span>
                    @endif
                </td>
                <td><span class="badge bg-info-soft text-info rounded-pill text-uppercase">{{ $provider->provider }}</span></td>
                <td><span class="text-muted small">{{ $provider->provider_user_id ?: '—' }}</span></td>
                <td><span class="text-muted small">{{ $provider->provider_email ?: '—' }}</span></td>
                <td><span class="text-muted small">{{ $provider->token_expires_at?->format('M d, Y H:i') ?? 'Never' }}</span></td>
                <td class="pe-4"><span class="text-muted small">{{ $provider->created_at?->format('M d, Y') }}</span></td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-center py-5 text-muted">No auth providers found.</td></tr>
        @endforelse
    @endslot
@endcomponent
@endsection
