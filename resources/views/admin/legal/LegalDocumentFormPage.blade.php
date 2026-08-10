@extends('admin.layouts.app')

@section('content')
<div class="users-page">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <a href="{{ route('admin.legal.index') }}" class="text-decoration-none text-reset fw-bold">Legal Documents</a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">{{ $label }}</span>
    </nav>

    <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-4">
        <div>
            <h1 class="h3 fw-extrabold mb-1" style="letter-spacing: -0.03em;">{{ $label }}</h1>
            <p class="text-muted small mb-0">
                @if ($current)
                    Live version is <span class="fw-bold">v{{ $current->version }}</span>.
                @else
                    Nothing published yet.
                @endif
                @if ($draft)
                    You have an unpublished draft (v{{ $draft->version }}).
                @else
                    Saving will create v{{ $nextVersion }}.
                @endif
            </p>
        </div>
        <a href="{{ route('admin.legal.index') }}" class="btn btn-outline-dynamic rounded-3"><i class="bi bi-arrow-left me-2"></i>Back</a>
    </div>

    @if (session('status'))
        <div class="glass rounded-4 p-3 mb-4 border border-primary-soft bg-primary-soft-opaque">
            <i class="bi bi-check-circle me-2 text-success"></i>{{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.legal.store', $documentKey) }}" class="glass rounded-4 p-4 border border-white-05">
        @csrf
        @php $source = $draft ?: $current; @endphp

        <div class="mb-3">
            <label class="form-label small fw-bold text-muted">Title</label>
            <input type="text" name="title" maxlength="191"
                   value="{{ old('title', $source?->title ?? $label) }}"
                   class="form-control bg-dark-soft border-0 rounded-3 @error('title') is-invalid @enderror">
            @error('title')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label class="form-label small fw-bold text-muted">Body</label>
            <textarea name="body" rows="22"
                      class="form-control bg-dark-soft border-0 rounded-3 @error('body') is-invalid @enderror"
                      placeholder="Paste the full document. Leave a blank line between paragraphs.">{{ old('body', $source?->body) }}</textarea>
            <div class="form-text text-muted extra-small">
                Plain text. A blank line starts a new paragraph. Markup is escaped, so pasted content can never break the public page.
            </div>
            @error('body')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label class="form-label small fw-bold text-muted">What changed (optional)</label>
            <input type="text" name="summary_of_changes" maxlength="500"
                   value="{{ old('summary_of_changes', $source?->summary_of_changes) }}"
                   class="form-control bg-dark-soft border-0 rounded-3"
                   placeholder="e.g. Added the section on data retention">
        </div>

        <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" role="switch" id="requiresReacceptance" name="requires_reacceptance" value="1"
                   @checked(old('requires_reacceptance', $source?->requires_reacceptance))>
            <label class="form-check-label small" for="requiresReacceptance">
                Material change — ask every user to accept again
            </label>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4">
            <a href="{{ route('admin.legal.index') }}" class="btn btn-outline-dynamic rounded-3 px-4">Cancel</a>
            <button class="btn btn-outline-dynamic rounded-3 px-4" type="submit" name="publish" value="0">
                <i class="bi bi-save me-2"></i>Save draft
            </button>
            <button class="btn bg-primary-gradient text-white fw-bold rounded-3 px-4 shadow-glow border-0" type="submit" name="publish" value="1">
                <i class="bi bi-globe me-2"></i>Publish
            </button>
        </div>
    </form>

    @if ($current)
        <div class="glass rounded-4 p-4 border border-white-05 mt-4">
            <h2 class="h6 fw-bold mb-3">Currently live (v{{ $current->version }})</h2>
            @foreach ($current->paragraphs() as $paragraph)
                <p class="small text-muted">{{ $paragraph }}</p>
            @endforeach
        </div>
    @endif
</div>
@endsection
