@extends('admin.layouts.app')

@section('content')
<div class="users-page">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <a href="{{ route('admin.email_templates.index') }}" class="text-decoration-none text-reset fw-bold">Email Templates</a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">{{ $template->name }}</span>
    </nav>

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <div>
            <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">{{ $template->name }}</h1>
            <p class="text-muted small mb-0">{{ $template->description }}</p>
        </div>
        <a href="{{ route('admin.email_templates.index') }}" class="btn btn-outline-dynamic rounded-3"><i class="bi bi-arrow-left me-2"></i>Back</a>
    </div>

    @if (session('status'))
        <div class="glass rounded-4 p-3 mb-4 border border-primary-soft bg-primary-soft-opaque">
            <i class="bi bi-check-circle me-2 text-success"></i>{{ session('status') }}
        </div>
    @endif

    <div class="row g-4">
        <div class="col-12 col-lg-7">
            <form method="POST" action="{{ route('admin.email_templates.update', $template) }}" class="glass rounded-4 p-4 border border-white-05">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">Subject</label>
                    <input type="text" name="subject" value="{{ old('subject', $template->subject) }}" maxlength="191"
                        class="form-control bg-dark-soft border-0 rounded-3 @error('subject') is-invalid @enderror">
                    @error('subject')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">Heading</label>
                    <input type="text" name="heading" value="{{ old('heading', $template->heading) }}" maxlength="191"
                        class="form-control bg-dark-soft border-0 rounded-3 @error('heading') is-invalid @enderror">
                    @error('heading')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">Body</label>
                    <textarea name="body" rows="10" maxlength="5000"
                        class="form-control bg-dark-soft border-0 rounded-3 @error('body') is-invalid @enderror">{{ old('body', $template->body) }}</textarea>
                    <div class="form-text text-muted extra-small">Blank lines become paragraphs. The 6-digit code, where one applies, is added automatically in its own box — do not write it into the body.</div>
                    @error('body')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Button Text</label>
                        <input type="text" name="action_text" value="{{ old('action_text', $template->action_text) }}" maxlength="60"
                            class="form-control bg-dark-soft border-0 rounded-3" placeholder="Leave blank for no button">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Button URL</label>
                        <input type="text" name="action_url" value="{{ old('action_url', $template->action_url) }}" maxlength="1024"
                            class="form-control bg-dark-soft border-0 rounded-3" placeholder="https://...">
                    </div>
                </div>

                <div class="form-check form-switch mb-4">
                    <input class="form-check-input" type="checkbox" role="switch" id="isActive" name="is_active" value="1" @checked(old('is_active', $template->is_active))>
                    <label class="form-check-label small" for="isActive">
                        Use this wording. Turn it off to fall back to Stylebite's built-in copy.
                    </label>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('admin.email_templates.index') }}" class="btn btn-outline-dynamic rounded-3 px-4">Cancel</a>
                    <button class="btn bg-primary-gradient text-white fw-bold rounded-3 px-4 shadow-glow border-0">
                        <i class="bi bi-check2 me-2"></i>Save Template
                    </button>
                </div>
            </form>

            <div class="d-flex gap-2 mt-3 flex-wrap">
                <form method="POST" action="{{ route('admin.email_templates.test_send', $template) }}">
                    @csrf
                    <button class="btn btn-outline-dynamic rounded-3" type="submit">
                        <i class="bi bi-envelope-paper me-2"></i>Send test to me
                    </button>
                </form>

                @if ($defaultCopy)
                    <form method="POST" action="{{ route('admin.email_templates.reset', $template) }}"
                          onsubmit="return confirm('Restore the built-in wording? Your current text will be replaced.');">
                        @csrf
                        @method('PATCH')
                        <button class="btn btn-outline-dynamic rounded-3 text-warning" type="submit">
                            <i class="bi bi-arrow-counterclockwise me-2"></i>Restore built-in wording
                        </button>
                    </form>
                @endif
            </div>
        </div>

        <div class="col-12 col-lg-5">
            <div class="glass rounded-4 p-4 border border-white-05 mb-4">
                <h2 class="h6 fw-bold mb-3">Placeholders</h2>
                <p class="text-muted extra-small">Use these anywhere in the subject, heading or body. Anything unrecognised is removed before sending.</p>
                <div class="d-flex flex-wrap gap-2">
                    @foreach ($placeholders as $placeholder)
                        <code class="badge bg-dark-soft text-info rounded-pill px-3 py-2">{{ $placeholder }}</code>
                    @endforeach
                </div>
            </div>

            <div class="glass rounded-4 p-4 border border-white-05">
                <h2 class="h6 fw-bold mb-3">Preview <span class="text-muted extra-small fw-normal">(with sample data)</span></h2>
                <div class="bg-dark-soft rounded-3 p-3">
                    <div class="text-muted extra-small text-uppercase fw-bold mb-1">Subject</div>
                    <div class="small fw-semibold mb-3">{{ $preview['subject'] }}</div>

                    <div class="text-muted extra-small text-uppercase fw-bold mb-1">Heading</div>
                    <div class="small fw-semibold mb-3">{{ $preview['heading'] }}</div>

                    <div class="text-muted extra-small text-uppercase fw-bold mb-1">Body</div>
                    <div class="small" style="white-space: pre-wrap;">{{ $preview['body'] }}</div>

                    @if ($preview['action_text'])
                        <div class="mt-3">
                            <span class="badge bg-primary-gradient text-white rounded-3 px-3 py-2">{{ $preview['action_text'] }}</span>
                        </div>
                    @endif
                </div>
                <div class="form-text text-muted extra-small mt-2">Save to refresh this preview.</div>
            </div>
        </div>
    </div>
</div>
@endsection
