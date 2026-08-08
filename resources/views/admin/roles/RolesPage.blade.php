@extends('admin.layouts.app')

@section('content')
<div class="users-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold">Roles &amp; Permissions</span>
    </nav>

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <div>
            <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">Roles &amp; Permissions</h1>
            <p class="text-muted small mb-0">Who can see and do what in the admin panel</p>
        </div>
        @can('roles.create')
            <a href="{{ route('admin.roles.create') }}" class="btn bg-primary-gradient text-white fw-bold rounded-3 px-3 shadow-glow border-0">
                <i class="bi bi-plus-lg me-2"></i>New Role
            </a>
        @endcan
    </div>

    @if (session('status'))
        <div class="glass rounded-4 p-3 mb-4 border border-primary-soft bg-primary-soft-opaque">
            <i class="bi bi-check-circle me-2 text-success"></i>{{ session('status') }}
        </div>
    @endif

    <div class="glass rounded-4 overflow-hidden border-white-05">
        <div class="table-responsive scrollbar-hidden">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-white-05">
                    <tr>
                        <th class="ps-4 text-muted small fw-bold text-uppercase py-3">Role</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Type</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Permissions</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Users</th>
                        <th class="text-end pe-4 text-muted small fw-bold text-uppercase py-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($roles as $role)
                        @php
                            $isLocked = in_array($role->name, $lockedRoles, true);
                            $isSeeded = in_array($role->name, $seededRoles, true);
                        @endphp
                        <tr class="border-white-05">
                            <td class="ps-4">
                                <div class="fw-bold small text-capitalize">{{ $role->name }}</div>
                            </td>
                            <td>
                                @if ($isLocked)
                                    <span class="badge bg-primary-soft text-primary rounded-pill px-3 py-1 fw-bold text-uppercase" style="font-size: 0.65rem;">Locked</span>
                                @elseif ($isSeeded)
                                    <span class="badge bg-info-soft text-info rounded-pill px-3 py-1 fw-bold text-uppercase" style="font-size: 0.65rem;">Built-in</span>
                                @else
                                    <span class="badge bg-secondary-soft text-muted rounded-pill px-3 py-1 fw-bold text-uppercase" style="font-size: 0.65rem;">Custom</span>
                                @endif
                            </td>
                            <td><span class="text-muted small">{{ $role->permissions_count }} permission(s)</span></td>
                            <td><span class="text-muted small">{{ number_format($role->users_count) }} user(s)</span></td>
                            <td class="text-end pe-4">
                                <div class="d-flex justify-content-end gap-1">
                                    @can('roles.update')
                                        <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-icon btn-sm hover-bg-white-10" title="{{ $isLocked ? 'View permissions' : 'Edit role' }}">
                                            <i class="bi {{ $isLocked ? 'bi-eye' : 'bi-pencil' }}"></i>
                                        </a>
                                    @endcan
                                    @can('roles.delete')
                                        @unless ($isSeeded)
                                            <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" onsubmit="return confirm('Delete the \'{{ $role->name }}\' role?');">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-icon btn-sm hover-bg-white-10 text-danger" type="submit" title="Delete role">
                                                    <i class="bi bi-trash3"></i>
                                                </button>
                                            </form>
                                        @endunless
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center py-5 text-muted">No roles defined.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="glass rounded-4 p-3 mt-4 border border-white-05 text-muted small">
        <i class="bi bi-info-circle me-2"></i>
        Users with at least one permission can sign in to this panel. The <span class="fw-bold">admin</span> role always holds every permission; built-in roles mirror the app's account types and cannot be renamed or deleted.
    </div>
</div>
@endsection
