@extends('admin.layouts.app')

@section('content')
<div class="settings-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold">Settings</span>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">System Health</span>
    </nav>

    <div class="mb-4">
        <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">System Health</h1>
        <p class="text-muted small mb-0">This page is currently hidden from the active admin workflow.</p>
    </div>

    <div class="glass rounded-4 p-4 border border-white-05">
        <div class="text-muted small">System health widgets are hidden for now.</div>
    </div>
</div>
@endsection
