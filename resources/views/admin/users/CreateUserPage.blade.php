@extends('admin.layouts.app')

@section('content')
<div class="users-page">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <a href="{{ route('admin.users.all_users') }}" class="text-decoration-none text-reset fw-bold">Users</a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">Add User</span>
    </nav>

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <div>
            <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">Add User</h1>
            <p class="text-muted small mb-0">Create a member, creator, moderator, or admin account.</p>
        </div>
        <a href="{{ route('admin.users.all_users') }}" class="btn btn-outline-dynamic rounded-3">
            <i class="bi bi-arrow-left me-2"></i>Back to Users
        </a>
    </div>

    @include('admin.users.partials.tabs')

    <form method="POST" action="{{ route('admin.users.store') }}" class="glass rounded-4 p-4 border border-white-05">
        @csrf

        <div class="row g-4">
            <div class="col-12 col-lg-8">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Full Name</label>
                        <input type="text" name="full_name" value="{{ old('full_name') }}" class="form-control bg-dark-soft border-0 rounded-3 @error('full_name') is-invalid @enderror" placeholder="Avery Stone">
                        @error('full_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Username</label>
                        <input type="text" name="username" value="{{ old('username') }}" class="form-control bg-dark-soft border-0 rounded-3 @error('username') is-invalid @enderror" placeholder="avery">
                        @error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold text-muted">Email</label>
                        <input type="email" name="email" value="{{ old('email') }}" class="form-control bg-dark-soft border-0 rounded-3 @error('email') is-invalid @enderror" placeholder="avery@example.com">
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Password</label>
                        <input type="password" name="password" class="form-control bg-dark-soft border-0 rounded-3 @error('password') is-invalid @enderror" placeholder="Minimum 8 characters">
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Confirm Password</label>
                        <input type="password" name="password_confirmation" class="form-control bg-dark-soft border-0 rounded-3" placeholder="Repeat password">
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-4">
                <div class="bg-white-05 rounded-4 p-3 h-100 border border-white-05">
                    <label class="form-label small fw-bold text-muted">Role</label>
                    <select name="role" class="form-select bg-dark-soft border-0 rounded-3 mb-3 @error('role') is-invalid @enderror">
                        @foreach (['user' => 'User', 'creator' => 'Creator', 'moderator' => 'Moderator', 'admin' => 'Admin'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('role', 'user') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('role')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror

                    <label class="form-label small fw-bold text-muted">Status</label>
                    <select name="status" class="form-select bg-dark-soft border-0 rounded-3 mb-3 @error('status') is-invalid @enderror">
                        @foreach (['active' => 'Active', 'inactive' => 'Suspended', 'banned' => 'Banned'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', 'active') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('status')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror

                    <label class="form-label small fw-bold text-muted">Locale</label>
                    <input type="text" name="locale" value="{{ old('locale', 'en') }}" class="form-control bg-dark-soft border-0 rounded-3 mb-3">

                    <label class="form-label small fw-bold text-muted">Timezone</label>
                    <input type="text" name="timezone" value="{{ old('timezone', config('app.timezone', 'UTC')) }}" class="form-control bg-dark-soft border-0 rounded-3">
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4">
            <a href="{{ route('admin.users.all_users') }}" class="btn btn-outline-dynamic rounded-3 px-4">Cancel</a>
            <button class="btn bg-primary-gradient text-white fw-bold rounded-3 px-4 shadow-glow border-0">
                <i class="bi bi-plus-lg me-2"></i>Create User
            </button>
        </div>
    </form>
</div>

@include('admin.users.partials.theme')
@endsection
