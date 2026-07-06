@extends('admin.layouts.app')

@section('content')
<div class="users-page">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <a href="{{ route('admin.users.all_users') }}" class="text-decoration-none text-reset fw-bold">Users</a>
        <i class="bi bi-chevron-right small"></i>
        <a href="{{ route('admin.users.show', $user) }}" class="text-decoration-none text-reset fw-bold">{{ $user->full_name ?: $user->username }}</a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">Edit</span>
    </nav>

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <div>
            <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">Edit User</h1>
            <p class="text-muted small mb-0">Update account access, identity, and login details.</p>
        </div>
        <a href="{{ route('admin.users.show', $user) }}" class="btn btn-outline-dynamic rounded-3">
            <i class="bi bi-eye me-2"></i>View User
        </a>
    </div>

    @include('admin.users.partials.tabs')

    <form method="POST" action="{{ route('admin.users.update', $user) }}" class="glass rounded-4 p-4 border border-white-05">
        @csrf
        @method('PUT')

        <div class="row g-4">
            <div class="col-12 col-lg-8">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Full Name</label>
                        <input type="text" name="full_name" value="{{ old('full_name', $user->full_name) }}" class="form-control bg-dark-soft border-0 rounded-3 @error('full_name') is-invalid @enderror">
                        @error('full_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Username</label>
                        <input type="text" name="username" value="{{ old('username', $user->username) }}" class="form-control bg-dark-soft border-0 rounded-3 @error('username') is-invalid @enderror">
                        @error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold text-muted">Email</label>
                        <input type="email" name="email" value="{{ old('email', $user->email) }}" class="form-control bg-dark-soft border-0 rounded-3 @error('email') is-invalid @enderror">
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">New Password</label>
                        <input type="password" name="password" class="form-control bg-dark-soft border-0 rounded-3 @error('password') is-invalid @enderror" placeholder="Leave blank to keep current password">
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Confirm New Password</label>
                        <input type="password" name="password_confirmation" class="form-control bg-dark-soft border-0 rounded-3" placeholder="Repeat new password">
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-4">
                <div class="bg-white-05 rounded-4 p-3 h-100 border border-white-05">
                    <label class="form-label small fw-bold text-muted">Role</label>
                    <select name="role" class="form-select bg-dark-soft border-0 rounded-3 mb-3 @error('role') is-invalid @enderror">
                        @foreach (['user' => 'User', 'creator' => 'Creator', 'moderator' => 'Moderator', 'admin' => 'Admin'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('role', $user->role) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('role')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror

                    <label class="form-label small fw-bold text-muted">Status</label>
                    <select name="status" class="form-select bg-dark-soft border-0 rounded-3 mb-3 @error('status') is-invalid @enderror">
                        @foreach (['active' => 'Active', 'inactive' => 'Suspended', 'banned' => 'Banned'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $user->status) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('status')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror

                    <label class="form-label small fw-bold text-muted">Locale</label>
                    <input type="text" name="locale" value="{{ old('locale', $user->locale) }}" class="form-control bg-dark-soft border-0 rounded-3 mb-3">

                    <label class="form-label small fw-bold text-muted">Timezone</label>
                    <input type="text" name="timezone" value="{{ old('timezone', $user->timezone) }}" class="form-control bg-dark-soft border-0 rounded-3">
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4">
            <a href="{{ route('admin.users.show', $user) }}" class="btn btn-outline-dynamic rounded-3 px-4">Cancel</a>
            <button class="btn bg-primary-gradient text-white fw-bold rounded-3 px-4 shadow-glow border-0">
                <i class="bi bi-check2 me-2"></i>Save Changes
            </button>
        </div>
    </form>
</div>

@include('admin.users.partials.theme')
@endsection
