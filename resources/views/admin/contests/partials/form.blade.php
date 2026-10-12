@php
    $contest = $contest ?? null;
    $action = $action ?? route('admin.contests.store');
    $method = strtoupper($method ?? 'POST');
    $formTitle = $formTitle ?? 'Contest';
    $submitLabel = $submitLabel ?? 'Create Contest';
    $fieldDate = function (string $field) use ($contest): string {
        if (! $contest) {
            return '';
        }

        try {
            $value = $contest->getAttribute($field);
        } catch (\Throwable) {
            return '';
        }

        return $value instanceof \DateTimeInterface ? $value->format('Y-m-d\TH:i') : '';
    };
@endphp

<form method="POST" action="{{ $action }}" class="row g-3" enctype="multipart/form-data" data-contest-form>
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif
    <div class="col-md-6">
        <label class="form-label small text-muted">Title</label>
        <input type="text" name="title" value="{{ old('title', $contest?->title ?? '') }}" class="form-control border-0 bg-dark-soft rounded-3" placeholder="Summer Style Challenge" required>
    </div>
    <div class="col-md-6">
        <label class="form-label small text-muted">Subtitle</label>
        <input type="text" name="subtitle" value="{{ old('subtitle', $contest?->subtitle ?? '') }}" class="form-control border-0 bg-dark-soft rounded-3" placeholder="Urban fashion, minimal edit">
    </div>
    <div class="col-12">
        <label class="form-label small text-muted">Description</label>
        <textarea name="description" rows="3" class="form-control border-0 bg-dark-soft rounded-3" placeholder="Explain the contest rules, theme and what users should post.">{{ old('description', $contest?->description ?? '') }}</textarea>
    </div>
    <div class="col-md-4">
        <label class="form-label small text-muted">Contest Type</label>
        <input type="text" name="contest_type" value="{{ old('contest_type', $contest?->contest_type ?? '') }}" class="form-control border-0 bg-dark-soft rounded-3" placeholder="City vs City" maxlength="120" required>
    </div>
    <div class="col-md-4">
        <label class="form-label small text-muted">Status</label>
        <select name="status" class="form-select border-0 bg-dark-soft rounded-3 text-muted" required>
            @foreach (['draft' => 'Draft', 'upcoming' => 'Upcoming', 'active' => 'Active', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'archived' => 'Archived'] as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $contest?->status ?? 'upcoming') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <div class="form-text text-muted">Upcoming and Active contests appear in the app. Draft stays hidden until you publish it.</div>
    </div>
    <div class="col-md-4">
        <label class="form-label small text-muted">Visibility</label>
        <select name="visibility" class="form-select border-0 bg-dark-soft rounded-3 text-muted" required>
            @foreach (['public' => 'Public', 'followers_only' => 'Followers Only', 'private' => 'Private'] as $value => $label)
                <option value="{{ $value }}" @selected(old('visibility', $contest?->visibility ?? 'public') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label small text-muted">City</label>
        <input type="text" name="city" value="{{ old('city', $contest?->city ?? '') }}" class="form-control border-0 bg-dark-soft rounded-3" placeholder="Karachi">
    </div>
    <div class="col-md-4">
        <label class="form-label small text-muted">Country</label>
        <input type="text" name="country" value="{{ old('country', $contest?->country ?? '') }}" class="form-control border-0 bg-dark-soft rounded-3" placeholder="Pakistan">
    </div>
    <div class="col-md-4">
        <label class="form-label small text-muted">Prize Pool</label>
        <input type="number" min="0" step="0.01" name="prize_pool" value="{{ old('prize_pool', $contest?->prize_pool ?? 0) }}" class="form-control border-0 bg-dark-soft rounded-3" placeholder="1000">
    </div>
    <div class="col-md-6">
        <label class="form-label small text-muted">Start At</label>
        <input type="datetime-local" name="start_at" value="{{ old('start_at', $fieldDate('start_at')) }}" class="form-control border-0 bg-dark-soft rounded-3">
    </div>
    <div class="col-md-6">
        <label class="form-label small text-muted">End At</label>
        <input type="datetime-local" name="end_at" value="{{ old('end_at', $fieldDate('end_at')) }}" class="form-control border-0 bg-dark-soft rounded-3">
    </div>
    <div class="col-md-6">
        <label class="form-label small text-muted">Cover Image</label>
        <input type="file" name="cover_image" id="cover-image-input" class="form-control border-0 bg-dark-soft rounded-3" accept="image/*">
        <div class="form-text text-muted">Upload a cover image from your device.</div>
        <img
            id="cover-image-preview"
            src="{{ stylebite_asset_url($contest?->cover_image_url) ?? '' }}"
            alt="Cover image preview"
            class="img-fluid rounded-3 mt-2 border border-white-05 {{ empty($contest?->cover_image_url) ? 'd-none' : '' }}"
            style="max-height: 180px; object-fit: cover;"
        >
    </div>
    <div class="col-md-6">
        <label class="form-label small text-muted">Banner Image</label>
        <input type="file" name="banner_image" id="banner-image-input" class="form-control border-0 bg-dark-soft rounded-3" accept="image/*">
        <div class="form-text text-muted">Upload a banner image from your device.</div>
        <img
            id="banner-image-preview"
            src="{{ stylebite_asset_url($contest?->banner_image_url) ?? '' }}"
            alt="Banner image preview"
            class="img-fluid rounded-3 mt-2 border border-white-05 {{ empty($contest?->banner_image_url) ? 'd-none' : '' }}"
            style="max-height: 180px; object-fit: cover;"
        >
    </div>
    <div class="col-12">
        <label class="form-label small text-muted">Rules</label>
        <textarea name="rules_text" rows="4" class="form-control border-0 bg-dark-soft rounded-3" placeholder="One rule per line. Example:&#10;1. Post a full outfit photo&#10;2. Add a short caption&#10;3. Use brand tags if needed">{{ old('rules_text', $contest?->rules_text ?? '') }}</textarea>
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
        bindImagePreview('banner-image-input', 'banner-image-preview');

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
