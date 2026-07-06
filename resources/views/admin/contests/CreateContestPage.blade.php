@extends('admin.layouts.app')

@section('content')
<div class="contests-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <a href="{{ route('admin.contests.contests') }}" class="text-decoration-none text-reset">Contests</a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">Create</span>
    </nav>

    <div class="mb-4 d-flex align-items-start justify-content-between flex-wrap gap-3">
        <div>
            <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">Create Contest</h1>
            <p class="text-muted small mb-0">Create an admin contest that users can join from the app.</p>
        </div>
        <a href="{{ route('admin.contests.contests') }}" class="btn btn-outline-dynamic rounded-3 px-3">
            <i class="bi bi-arrow-left me-1"></i>Back to Contests
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger rounded-3 border-0 mb-4">
            <div class="fw-semibold mb-2">Please fix the highlighted fields.</div>
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="glass rounded-4 p-4 border-white-05">
        @include('admin.contests.partials.form', [
            'action' => route('admin.contests.store'),
            'method' => 'POST',
            'formTitle' => 'Create Contest',
            'submitLabel' => 'Create Contest',
        ])
    </div>
</div>
@endsection
