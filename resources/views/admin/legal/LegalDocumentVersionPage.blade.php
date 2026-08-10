@extends('admin.layouts.app')

@section('content')
<div class="users-page">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <a href="{{ route('admin.legal.index') }}" class="text-decoration-none text-reset fw-bold">Legal Documents</a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">{{ $document->keyLabel() }} v{{ $document->version }}</span>
    </nav>

    <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-4">
        <div>
            <h1 class="h3 fw-extrabold mb-1">{{ $document->title }}</h1>
            <p class="text-muted small mb-0">
                {{ $document->keyLabel() }} · v{{ $document->version }} ·
                {{ $document->is_published ? 'published '.$document->published_at?->format('M d, Y H:i') : 'draft' }}
                @if ($document->author) · by {{ $document->author->full_name ?: $document->author->username }} @endif
            </p>
        </div>
        <span class="badge {{ $document->is_published ? 'bg-success-soft text-success' : 'bg-warning-soft text-warning' }} rounded-pill px-3 py-2">
            {{ number_format($acceptances) }} acceptances
        </span>
    </div>

    @if ($document->summary_of_changes)
        <div class="glass rounded-4 p-3 mb-4 border border-white-05 small">
            <span class="text-muted">What changed:</span> {{ $document->summary_of_changes }}
        </div>
    @endif

    <div class="glass rounded-4 p-4 border border-white-05">
        @foreach ($document->paragraphs() as $paragraph)
            <p class="small">{{ $paragraph }}</p>
        @endforeach
    </div>

    <div class="glass rounded-4 p-3 mt-4 border border-white-05 text-muted small">
        <i class="bi bi-lock me-2"></i>
        Published versions are read-only on purpose — this is the exact wording {{ number_format($acceptances) }} user(s) agreed to. Edit the document to create a new version instead.
    </div>
</div>
@endsection
