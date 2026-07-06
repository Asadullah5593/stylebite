@extends('admin.layouts.app')

@section('content')
<div class="users-page">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold">Account</span>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">Settings</span>
    </nav>

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <div>
            <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">Account Settings</h1>
            <p class="text-muted small mb-0">Update your admin profile, identity, locale, and password.</p>
        </div>
        <a href="{{ route('admin.account.profile') }}" class="btn btn-outline-dynamic rounded-3">
            <i class="bi bi-person me-2"></i>View Profile
        </a>
    </div>

    @if (session('status'))
        <div class="alert alert-success rounded-3 border-0 mb-4">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.account.update') }}" class="glass rounded-4 p-4 border border-white-05">
        @csrf
        @method('PUT')

        <div class="row g-4">
            <div class="col-12 col-lg-7">
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
                        <label class="form-label small fw-bold text-muted">Display Name</label>
                        <input type="text" name="profile[display_name]" value="{{ old('profile.display_name', $user->profile?->display_name) }}" class="form-control bg-dark-soft border-0 rounded-3 @error('profile.display_name') is-invalid @enderror">
                        @error('profile.display_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Headline</label>
                        <input type="text" name="profile[headline]" value="{{ old('profile.headline', $user->profile?->headline) }}" class="form-control bg-dark-soft border-0 rounded-3 @error('profile.headline') is-invalid @enderror">
                        @error('profile.headline')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">City</label>
                        <input type="text" name="profile[city]" value="{{ old('profile.city', $user->profile?->city) }}" class="form-control bg-dark-soft border-0 rounded-3 @error('profile.city') is-invalid @enderror">
                        @error('profile.city')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Country</label>
                        <input type="text" name="profile[country]" value="{{ old('profile.country', $user->profile?->country) }}" class="form-control bg-dark-soft border-0 rounded-3 @error('profile.country') is-invalid @enderror">
                        @error('profile.country')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold text-muted">Website</label>
                        <input type="url" name="profile[website_url]" value="{{ old('profile.website_url', $user->profile?->website_url) }}" class="form-control bg-dark-soft border-0 rounded-3 @error('profile.website_url') is-invalid @enderror">
                        @error('profile.website_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold text-muted">Bio</label>
                        <textarea name="profile[bio]" rows="4" class="form-control bg-dark-soft border-0 rounded-3 @error('profile.bio') is-invalid @enderror">{{ old('profile.bio', $user->profile?->bio) }}</textarea>
                        @error('profile.bio')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-5">
                <div class="bg-white-05 rounded-4 p-3 h-100 border border-white-05">
                    <label class="form-label small fw-bold text-muted">Locale</label>
                    <input type="text" name="locale" value="{{ old('locale', $user->locale) }}" class="form-control bg-dark-soft border-0 rounded-3 mb-3 @error('locale') is-invalid @enderror">
                    @error('locale')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror

                    <label class="form-label small fw-bold text-muted">Timezone</label>
                    <input type="text" name="timezone" value="{{ old('timezone', $user->timezone) }}" class="form-control bg-dark-soft border-0 rounded-3 mb-3 @error('timezone') is-invalid @enderror">
                    @error('timezone')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror

                    <hr class="border-white-05 my-4">

                    <label class="form-label small fw-bold text-muted">New Password</label>
                    <input type="password" name="password" class="form-control bg-dark-soft border-0 rounded-3 mb-3 @error('password') is-invalid @enderror" placeholder="Leave blank to keep current password">
                    @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror

                    <label class="form-label small fw-bold text-muted">Confirm New Password</label>
                    <input type="password" name="password_confirmation" class="form-control bg-dark-soft border-0 rounded-3" placeholder="Repeat new password">

                    <div class="mt-4 small text-muted">
                        These settings update your own admin account only. App-wide configuration remains under App Settings.
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4">
            <a href="{{ route('admin.account.profile') }}" class="btn btn-outline-dynamic rounded-3 px-4">Cancel</a>
            <button class="btn bg-primary-gradient text-white fw-bold rounded-3 px-4 shadow-glow border-0">
                <i class="bi bi-check2 me-2"></i>Save Changes
            </button>
        </div>
    </form>
</div>
@endsection
