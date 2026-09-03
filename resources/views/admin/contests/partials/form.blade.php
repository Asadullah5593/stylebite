@php
    $contest = $contest ?? null;
    $action = $action ?? route('admin.contests.store');
    $method = strtoupper($method ?? 'POST');
    $formTitle = $formTitle ?? 'Contest';
    $submitLabel = $submitLabel ?? 'Create Contest';
@endphp

<form method="POST" action="{{ $action }}" class="row g-3" enctype="multipart/form-data" data-contest-form>
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif
    <div class="col-md-6">
        <label class="form-label small text-muted">Title</label>
        <input type="text" name="title" value="{{ old('title', $contest->title ?? '') }}" class="form-control border-0 bg-dark-soft rounded-3" placeholder="Summer Style Challenge" required>
    </div>
    <div class="col-md-6">
        <label class="form-label small text-muted">Subtitle</label>
        <input type="text" name="subtitle" value="{{ old('subtitle', $contest->subtitle ?? '') }}" class="form-control border-0 bg-dark-soft rounded-3" placeholder="Urban fashion, minimal edit">
    </div>
    <div class="col-12">
        <label class="form-label small text-muted">Description</label>
        <textarea name="description" rows="3" class="form-control border-0 bg-dark-soft rounded-3" placeholder="Explain the contest rules, theme and what users should post.">{{ old('description', $contest->description ?? '') }}</textarea>
    </div>
    <div class="col-md-4">
        <label class="form-label small text-muted">Contest Type</label>
        <input type="text" name="contest_type" value="{{ old('contest_type', $contest->contest_type ?? '') }}" class="form-control border-0 bg-dark-soft rounded-3" placeholder="City vs City" maxlength="120" required>
        <div class="form-text text-muted">Shown to users. Type anything you like.</div>
    </div>
    <div class="col-md-4">
        <label class="form-label small text-muted">Status</label>
        <select name="status" class="form-select border-0 bg-dark-soft rounded-3 text-muted" required>
            @foreach (['draft' => 'Draft', 'upcoming' => 'Upcoming', 'active' => 'Active', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'archived' => 'Archived'] as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $contest->status ?? 'draft') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <div class="form-text text-muted">Upcoming, Active and Completed advance automatically from the start and end dates.</div>
    </div>
    <div class="col-md-4">
        <label class="form-label small text-muted">Voting Type</label>
        <input type="text" name="voting_type" value="{{ old('voting_type', $contest->voting_type ?? 'Community') }}" class="form-control border-0 bg-dark-soft rounded-3" placeholder="Community" maxlength="120" required>
        <div class="form-text text-muted">Shown to users. Type anything you like.</div>
    </div>
    <div class="col-md-4">
        <label class="form-label small text-muted">City</label>
        <input type="text" name="city" value="{{ old('city', $contest->city ?? '') }}" class="form-control border-0 bg-dark-soft rounded-3" placeholder="Karachi">
    </div>
    <div class="col-md-4">
        <label class="form-label small text-muted">Country</label>
        <input type="text" name="country" value="{{ old('country', $contest->country ?? '') }}" class="form-control border-0 bg-dark-soft rounded-3" placeholder="Pakistan">
    </div>
    <div class="col-md-4">
        <label class="form-label small text-muted">Max Participants</label>
        <input type="number" min="1" name="max_participants" value="{{ old('max_participants', $contest->max_participants ?? '') }}" class="form-control border-0 bg-dark-soft rounded-3" placeholder="100">
    </div>
    <div class="col-md-4">
        <label class="form-label small text-muted">Entry Fee</label>
        <input type="number" min="0" step="0.01" name="entry_fee" value="{{ old('entry_fee', $contest->entry_fee ?? 0) }}" class="form-control border-0 bg-dark-soft rounded-3" placeholder="0">
    </div>
    <div class="col-md-4">
        <label class="form-label small text-muted">Prize Pool</label>
        <input type="number" min="0" step="0.01" name="prize_pool" value="{{ old('prize_pool', $contest->prize_pool ?? 0) }}" class="form-control border-0 bg-dark-soft rounded-3" placeholder="1000">
    </div>
    <div class="col-md-4">
        <label class="form-label small text-muted">Start At <span class="text-muted">({{ stylebite_reporting_timezone() }})</span></label>
        <input type="datetime-local" name="start_at" value="{{ old('start_at', stylebite_admin_datetime_input($contest->start_at ?? null)) }}" class="form-control border-0 bg-dark-soft rounded-3">
    </div>
    <div class="col-md-4">
        <label class="form-label small text-muted">End At <span class="text-muted">({{ stylebite_reporting_timezone() }})</span></label>
        <input type="datetime-local" name="end_at" value="{{ old('end_at', stylebite_admin_datetime_input($contest->end_at ?? null)) }}" class="form-control border-0 bg-dark-soft rounded-3">
    </div>
    <div class="col-md-8">
        <label class="form-label small text-muted">Contest Image</label>
        <input type="file" name="contest_image" id="cover-image-input" class="form-control border-0 bg-dark-soft rounded-3" accept="image/jpeg,image/png,image/webp">
        <div class="form-text text-muted">
            Used as both the banner and the cover. Square works best &mdash; minimum
            1200 &times; 1200px. Anything larger than 2000 &times; 2000px is resized
            automatically, so a big photo is fine.
        </div>
        <img
            id="cover-image-preview"
            src="{{ $contest?->cover_image_url ?? '' }}"
            alt="Contest image preview"
            class="img-fluid rounded-3 mt-2 border border-white-05 {{ empty($contest?->cover_image_url) ? 'd-none' : '' }}"
            style="max-height: 180px; object-fit: cover;"
        >
    </div>
    <div class="col-md-4">
        <label class="form-label small text-muted">Featured</label>
        <div class="form-check form-switch mt-2">
            <input type="hidden" name="is_featured" value="0">
            <input class="form-check-input" type="checkbox" role="switch" id="is-featured-input" name="is_featured" value="1" @checked(old('is_featured', $contest->is_featured ?? false))>
            <label class="form-check-label small text-muted" for="is-featured-input">Feature this contest</label>
        </div>
        <div class="form-text text-muted">Only one contest can be featured. Turning this on removes it from whichever contest currently has it.</div>
    </div>
    <div class="col-12">
        <label class="form-label small text-muted">Rules</label>
        <textarea name="rules_text" rows="4" class="form-control border-0 bg-dark-soft rounded-3" placeholder="One rule per line. Example:&#10;1. Post a full outfit photo&#10;2. Add a short caption&#10;3. Use brand tags if needed">{{ old('rules_text', $contest->rules_text ?? '') }}</textarea>
    </div>
    <div class="col-12 d-flex justify-content-end">
        <button type="submit" class="btn btn-primary rounded-3 px-4"><i class="bi bi-plus-circle me-1"></i>{{ $submitLabel }}</button>
    </div>
</form>

<script>
    (function () {
        const bindImagePreview = (inputId, previewId) => {
            const input = document.getElementById(inputId);
            const preview = document.getElementById(previewId);

            if (!input || !preview) {
                return;
            }

            input.addEventListener('change', function () {
                const file = input.files && input.files[0];

                if (!file) {
                    if (!preview.getAttribute('src')) {
                        preview.classList.add('d-none');
                    }
                    return;
                }

                const objectUrl = URL.createObjectURL(file);
                preview.src = objectUrl;
                preview.classList.remove('d-none');

                preview.onload = function () {
                    URL.revokeObjectURL(objectUrl);
                };
            });
        };

        bindImagePreview('cover-image-input', 'cover-image-preview');

        // Prevent duplicate contests from double-click / repeat submits while
        // the (slow, image-upload) request is still in flight.
        document.querySelectorAll('form[data-contest-form]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (form.dataset.submitted === 'true') {
                    event.preventDefault();
                    return;
                }

                form.dataset.submitted = 'true';

                const submitButton = form.querySelector('button[type="submit"]');

                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving…';
                }
            });
        });
    })();
</script>
