@extends('admin.layouts.app')

@section('content')
<div class="users-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset">
            <i class="bi bi-house-door"></i>
        </a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold">Users</span>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">All Users</span>
    </nav>

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <div>
            <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">Users</h1>
            <p class="text-muted small mb-0">Members, profiles, sessions and access</p>
        </div>
    </div>

    @include('admin.users.partials.tabs')

    @if (session('status'))
        <div class="glass rounded-4 p-3 mb-4 border border-primary-soft bg-primary-soft-opaque">
            <i class="bi bi-check-circle me-2 text-success"></i>{{ session('status') }}
        </div>
    @endif

    <form method="GET" action="{{ route('admin.users.all_users') }}" class="glass rounded-4 p-3 d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="position-relative flex-grow-1" style="min-width: 250px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control ps-5 border-0 bg-dark-soft rounded-3" placeholder="Search users by email, username, name...">
        </div>

        <select name="role" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Roles</option>
            @foreach (['user' => 'User', 'creator' => 'Creator', 'moderator' => 'Moderator', 'admin' => 'Admin'] as $value => $label)
                <option value="{{ $value }}" @selected(request('role') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <select name="status" class="form-select border-0 bg-dark-soft rounded-3 text-muted" style="width: auto;">
            <option value="">All Status</option>
            @foreach (['active' => 'Active', 'inactive' => 'Suspended', 'banned' => 'Banned', 'deleted' => 'Deleted'] as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <div class="form-check form-switch text-muted small ms-1">
            <input class="form-check-input" type="checkbox" role="switch" id="withDeletedSwitch" name="with_deleted" value="1" @checked(request()->boolean('with_deleted') || request('status') === 'deleted')>
            <label class="form-check-label" for="withDeletedSwitch">Include deleted</label>
        </div>

        <button class="btn btn-outline-dynamic rounded-3 px-3" type="submit">
            <i class="bi bi-funnel me-2"></i>Filter
        </button>

        <a href="{{ route('admin.users.all_users') }}" class="btn btn-outline-dynamic rounded-3 px-3">
            <i class="bi bi-arrow-clockwise me-2"></i>Reset
        </a>

        <button class="btn btn-outline-dynamic rounded-3 px-3" type="button" onclick="exportToCSV()">
            <i class="bi bi-download me-2"></i>Export
        </button>

        <a href="{{ route('admin.users.create') }}" class="btn bg-primary-gradient text-white fw-bold rounded-3 px-3 shadow-glow border-0">
            <i class="bi bi-plus-lg me-2"></i>Add User
        </a>
    </form>

    <div class="glass rounded-4 overflow-hidden border-white-05">
        <div class="table-responsive scrollbar-hidden">
            <table class="table table-hover align-middle mb-0" id="usersTable">
                <thead class="bg-white-05">
                    <tr>
                        <th class="ps-4 text-muted small fw-bold text-uppercase py-3">User</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Role</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Status</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Content</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Last Login</th>
                        <th class="text-muted small fw-bold text-uppercase py-3">Created</th>
                        <th class="text-end pe-4 text-muted small fw-bold text-uppercase py-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        @php
                            $name = $user->full_name ?: $user->username;
                            $avatar = $user->avatar_url;
                            $avatarUrl = $avatar ? (str_starts_with($avatar, 'http') || str_starts_with($avatar, '/') ? $avatar : asset($avatar)) : null;
                            $statusClass = match ($user->status) {
                                'active' => 'bg-success',
                                'banned' => 'bg-danger',
                                'deleted' => 'bg-danger',
                                default => 'bg-warning',
                            };
                            $roleClass = match ($user->role) {
                                'admin' => 'bg-primary-soft text-primary',
                                'moderator' => 'bg-info-soft text-info',
                                'creator' => 'bg-warning-soft text-warning',
                                default => 'bg-secondary-soft text-muted',
                            };
                        @endphp
                        <tr class="border-white-05 group">
                            <td class="ps-4">
                                <div class="d-flex align-items-center gap-3">
                                    @if ($avatarUrl)
                                        <img src="{{ $avatarUrl }}" alt="{{ $name }}" class="user-avatar border border-2 border-primary-soft shadow-sm">
                                    @else
                                        <div class="avatar-fallback border border-2 border-primary-soft shadow-sm">{{ str($name)->substr(0, 1)->upper() }}</div>
                                    @endif
                                    <div>
                                        <div class="fw-bold small mb-0">{{ $name }}</div>
                                        <div class="text-muted extra-small">{{ $user->email }}</div>
                                        <div class="text-muted extra-small">{{ '@'.$user->username }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge {{ $roleClass }} rounded-pill px-3 py-1 fw-bold text-uppercase" style="font-size: 0.65rem;">
                                    {{ $user->role }}
                                </span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="dot-indicator {{ $statusClass }}"></span>
                                    <span class="small fw-medium text-capitalize">{{ $user->status === 'inactive' ? 'Suspended' : $user->status }}</span>
                                </div>
                                @if ($user->trashed())
                                    <div class="text-muted extra-small">Deleted {{ $user->deleted_at?->diffForHumans() }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="text-muted small">{{ $user->posts_count }} posts</span>
                                <span class="text-muted small mx-1">·</span>
                                <span class="text-muted small">{{ $user->memories_count }} memories</span>
                            </td>
                            <td><span class="text-muted small">{{ $user->last_login_at?->diffForHumans() ?? 'Never' }}</span></td>
                            <td><span class="text-muted small">{{ $user->created_at?->format('M d, Y') }}</span></td>
                            <td class="text-end pe-4">
                                <div class="d-flex justify-content-end gap-1 opacity-50 group-hover-opacity-100 transition-all">
                                    <a href="{{ route('admin.users.show', $user) }}" class="btn btn-icon btn-sm hover-bg-white-10" title="View Detail"><i class="bi bi-eye"></i></a>
                                    <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-icon btn-sm hover-bg-white-10" title="Edit User"><i class="bi bi-pencil"></i></a>
                                    <div class="dropdown">
                                        <button class="btn btn-icon btn-sm hover-bg-white-10" data-bs-toggle="dropdown" type="button"><i class="bi bi-three-dots"></i></button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-lg border-white-10 glass">
                                            <li>
                                                <form method="POST" action="{{ route('admin.users.status', $user) }}" id="activate-user-{{ $user->id }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="action" value="activate">
                                                    <button class="dropdown-item small py-2 text-success" type="button" onclick="confirmAction('activate-user-{{ $user->id }}', 'Activate this user?', 'This will restore normal access immediately.')">
                                                        <i class="bi bi-check-circle me-2"></i>Activate
                                                    </button>
                                                </form>
                                            </li>
                                            <li>
                                                <form method="POST" action="{{ route('admin.users.status', $user) }}" id="suspend-user-{{ $user->id }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="action" value="suspend">
                                                    <button class="dropdown-item small py-2 text-warning" type="button" onclick="confirmAction('suspend-user-{{ $user->id }}', 'Suspend this user?', 'This disables the account without permanently banning it.')">
                                                        <i class="bi bi-slash-circle me-2"></i>Suspend
                                                    </button>
                                                </form>
                                            </li>
                                            <li>
                                                <form method="POST" action="{{ route('admin.users.status', $user) }}" id="ban-user-{{ $user->id }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="action" value="ban">
                                                    <button class="dropdown-item small py-2 text-danger" type="button" onclick="confirmAction('ban-user-{{ $user->id }}', 'Ban this user?', 'This blocks access and marks the account as banned until an admin activates it again.')">
                                                        <i class="bi bi-shield-x me-2"></i>Ban
                                                    </button>
                                                </form>
                                            </li>
                                            <li><hr class="dropdown-divider border-white-10"></li>
                                            @if ($user->trashed())
                                                <li>
                                                    <form method="POST" action="{{ route('admin.users.restore', $user) }}" id="restore-user-{{ $user->id }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button class="dropdown-item small py-2 text-success" type="button" onclick="confirmAction('restore-user-{{ $user->id }}', 'Restore this user?', 'This will bring the account back into the admin list and reactivate access.')">
                                                            <i class="bi bi-arrow-counterclockwise me-2"></i>Restore
                                                        </button>
                                                    </form>
                                                </li>
                                            @else
                                            <li>
                                                <form method="POST" action="{{ route('admin.users.destroy', $user) }}" id="delete-user-{{ $user->id }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="dropdown-item small py-2 text-danger" type="button" onclick="confirmAction('delete-user-{{ $user->id }}', 'Delete this user?', 'This will remove the user from the admin list. This action needs confirmation.')">
                                                        <i class="bi bi-trash3 me-2"></i>Delete
                                                    </button>
                                                </form>
                                            </li>
                                            @endif
                                        </ul>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">No users found for the selected filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 bg-white-05 border-top border-white-05 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="text-muted small">
                Showing <span class="text-emphasis-dynamic fw-bold">{{ $users->firstItem() ?? 0 }}-{{ $users->lastItem() ?? 0 }}</span>
                of <span class="text-emphasis-dynamic fw-bold">{{ number_format($users->total()) }}</span> users
            </div>
            {{ $users->links() }}
        </div>
    </div>
</div>

<div class="modal fade confirm-modal" id="confirmUserActionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-white-05">
                <h5 class="modal-title" id="confirmUserActionTitle">Confirm action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-muted" id="confirmUserActionText"></div>
            <div class="modal-footer border-white-05">
                <button type="button" class="btn btn-outline-dynamic rounded-3" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn bg-primary-gradient text-white rounded-3 border-0" id="confirmUserActionButton">Confirm</button>
            </div>
        </div>
    </div>
</div>

@include('admin.users.partials.theme')

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modalElement = document.getElementById('confirmUserActionModal');
    const titleElement = document.getElementById('confirmUserActionTitle');
    const textElement = document.getElementById('confirmUserActionText');
    const confirmButton = document.getElementById('confirmUserActionButton');
    const confirmModal = new bootstrap.Modal(modalElement);
    let pendingFormId = null;

    window.confirmAction = function(formId, title, text) {
        pendingFormId = formId;
        titleElement.textContent = title;
        textElement.textContent = text;
        confirmModal.show();
    };

    confirmButton.addEventListener('click', function() {
        if (pendingFormId) {
            document.getElementById(pendingFormId).submit();
        }
    });

    window.exportToCSV = function() {
        let csv = 'Name,Username,Email,Role,Status,Created\n';
        document.querySelectorAll('#usersTable tbody tr').forEach(row => {
            const cells = row.querySelectorAll('td');
            if (cells.length < 7) {
                return;
            }

            const identity = cells[0].innerText.trim().split('\n').filter(Boolean);
            const role = cells[1].innerText.trim();
            const status = cells[2].innerText.trim();
            const created = cells[5].innerText.trim();

            csv += `"${identity[0] ?? ''}","${identity[2]?.replace('@', '') ?? ''}","${identity[1] ?? ''}","${role}","${status}","${created}"\n`;
        });

        const blob = new Blob([csv], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'stylebite-users.csv';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);
    };
});
</script>
@endsection
