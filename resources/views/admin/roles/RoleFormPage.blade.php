@extends('admin.layouts.app')

@section('content')
@php
    $isEdit = $role !== null;
    $checked = collect(old('permissions', $rolePermissions));
@endphp
<div class="users-page">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <a href="{{ route('admin.roles.index') }}" class="text-decoration-none text-reset fw-bold">Roles &amp; Permissions</a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">{{ $isEdit ? ucfirst($role->name) : 'New Role' }}</span>
    </nav>

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <div>
            <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">{{ $isEdit ? 'Edit Role' : 'New Role' }}</h1>
            <p class="text-muted small mb-0">{{ $locked ? 'This role is locked — it always holds every permission.' : 'Name the role, then tick what it is allowed to do.' }}</p>
        </div>
        <a href="{{ route('admin.roles.index') }}" class="btn btn-outline-dynamic rounded-3"><i class="bi bi-arrow-left me-2"></i>Back to Roles</a>
    </div>

    @if (session('status'))
        <div class="glass rounded-4 p-3 mb-4 border border-primary-soft bg-primary-soft-opaque">
            <i class="bi bi-check-circle me-2 text-success"></i>{{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ $isEdit ? route('admin.roles.update', $role) : route('admin.roles.store') }}" class="glass rounded-4 p-4 border border-white-05">
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label class="form-label small fw-bold text-muted">Role Name</label>
                <input type="text" name="name" value="{{ old('name', $role?->name) }}"
                    class="form-control bg-dark-soft border-0 rounded-3 @error('name') is-invalid @enderror"
                    placeholder="e.g. support, finance"
                    @disabled($locked || ($isEdit && in_array($role->name, ['admin', 'moderator', 'creator', 'user'], true)))>
                @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                @if ($isEdit && ! $locked && in_array($role->name, ['moderator', 'creator', 'user'], true))
                    <div class="form-text text-muted extra-small">Built-in role names are fixed; permissions below can be changed.</div>
                @endif
            </div>
        </div>

        @error('permissions')
            <div class="glass rounded-4 p-3 mb-3 border border-danger text-danger small">{{ $message }}</div>
        @enderror

        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="bg-white-05">
                    <tr>
                        <th class="ps-3 text-muted small fw-bold text-uppercase py-2" style="width: 220px;">Module</th>
                        <th class="text-muted small fw-bold text-uppercase py-2">Permissions</th>
                        <th class="text-end pe-3 text-muted small fw-bold text-uppercase py-2" style="width: 110px;">
                            @unless ($locked)<a href="#" class="text-decoration-none extra-small" id="toggleAllPermissions">Toggle all</a>@endunless
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($permissionGroups as $module => $permissions)
                        <tr class="border-white-05">
                            <td class="ps-3">
                                <div class="fw-bold small text-capitalize">{{ str_replace('_', ' ', $module) }}</div>
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-3 py-2">
                                    @foreach ($permissions as $permission)
                                        <label class="form-check d-flex align-items-center gap-2 mb-0">
                                            <input class="form-check-input permission-box" type="checkbox" name="permissions[]"
                                                value="{{ $permission['name'] }}"
                                                @checked($locked || $checked->contains($permission['name']))
                                                @disabled($locked)>
                                            <span class="small text-capitalize">{{ $permission['action'] }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </td>
                            <td class="text-end pe-3">
                                @unless ($locked)
                                    <a href="#" class="text-decoration-none extra-small module-toggle">All</a>
                                @endunless
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @unless ($locked)
            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="{{ route('admin.roles.index') }}" class="btn btn-outline-dynamic rounded-3 px-4">Cancel</a>
                <button class="btn bg-primary-gradient text-white fw-bold rounded-3 px-4 shadow-glow border-0">
                    <i class="bi bi-check2 me-2"></i>{{ $isEdit ? 'Save Role' : 'Create Role' }}
                </button>
            </div>
        @endunless
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.module-toggle').forEach(function (link) {
        link.addEventListener('click', function (event) {
            event.preventDefault();
            const boxes = link.closest('tr').querySelectorAll('.permission-box');
            const allChecked = Array.from(boxes).every(box => box.checked);
            boxes.forEach(box => { box.checked = !allChecked; });
        });
    });

    document.getElementById('toggleAllPermissions')?.addEventListener('click', function (event) {
        event.preventDefault();
        const boxes = document.querySelectorAll('.permission-box');
        const allChecked = Array.from(boxes).every(box => box.checked);
        boxes.forEach(box => { box.checked = !allChecked; });
    });
});
</script>
@endsection
