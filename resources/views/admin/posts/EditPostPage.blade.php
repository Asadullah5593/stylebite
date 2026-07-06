@extends('admin.layouts.app')

@section('content')
<div class="posts-page space-y-5">
    <nav class="d-flex align-items-center gap-2 mb-3 small opacity-75">
        <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-reset"><i class="bi bi-house-door"></i></a>
        <i class="bi bi-chevron-right small"></i>
        <a href="{{ route('admin.posts.all_posts') }}" class="text-decoration-none text-reset fw-bold">Posts</a>
        <i class="bi bi-chevron-right small"></i>
        <a href="{{ route('admin.posts.show', $post) }}" class="text-decoration-none text-reset fw-bold">Post #{{ $post->id }}</a>
        <i class="bi bi-chevron-right small"></i>
        <span class="fw-bold opacity-50">Edit</span>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h1 class="display-6 fw-extrabold mb-1" style="letter-spacing: -0.04em;">Edit Post</h1>
            <p class="text-muted small mb-0">Update public content metadata and moderation state</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.posts.update', $post) }}" class="glass rounded-4 p-4 border border-white-05">
        @csrf
        @method('PUT')

        <div class="row g-4">
            <div class="col-12">
                <label class="form-label small text-muted">Caption</label>
                <textarea name="caption" rows="4" class="form-control bg-dark-soft border-0 rounded-3 @error('caption') is-invalid @enderror">{{ old('caption', $post->caption) }}</textarea>
                @error('caption')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6">
                <label class="form-label small text-muted">Location Name</label>
                <input type="text" name="location_name" value="{{ old('location_name', $post->location_name) }}" class="form-control bg-dark-soft border-0 rounded-3 @error('location_name') is-invalid @enderror">
                @error('location_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3">
                <label class="form-label small text-muted">City</label>
                <input type="text" name="city" value="{{ old('city', $post->city) }}" class="form-control bg-dark-soft border-0 rounded-3 @error('city') is-invalid @enderror">
                @error('city')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3">
                <label class="form-label small text-muted">Country</label>
                <input type="text" name="country" value="{{ old('country', $post->country) }}" class="form-control bg-dark-soft border-0 rounded-3 @error('country') is-invalid @enderror">
                @error('country')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6">
                <label class="form-label small text-muted">Dish Name</label>
                <input type="text" name="dish_name" value="{{ old('dish_name', $post->dish_name) }}" class="form-control bg-dark-soft border-0 rounded-3 @error('dish_name') is-invalid @enderror">
                @error('dish_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6">
                <label class="form-label small text-muted">Restaurant</label>
                <input type="text" name="restaurant" value="{{ old('restaurant', $post->restaurant) }}" class="form-control bg-dark-soft border-0 rounded-3 @error('restaurant') is-invalid @enderror">
                @error('restaurant')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            @foreach ([
                'post_type' => ['outfit' => 'Outfit', 'food' => 'Food', 'reel' => 'Reel', 'memory' => 'Memory', 'contest_submission' => 'Contest Submission'],
                'content_type' => ['fashion' => 'Fashion', 'food' => 'Food', 'mixed' => 'Mixed', 'text_only' => 'Text Only'],
                'media_kind' => ['none' => 'None', 'image' => 'Image', 'video' => 'Video', 'carousel' => 'Carousel', 'mixed' => 'Mixed'],
                'feed_type' => ['style' => 'Style', 'bite' => 'Bite'],
                'visibility' => ['public' => 'Public', 'private' => 'Private', 'followers_only' => 'Followers Only'],
                'status' => ['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived', 'under_review' => 'Under Review', 'removed' => 'Removed'],
                'moderation_status' => ['clean' => 'Clean', 'flagged' => 'Flagged', 'restricted' => 'Restricted', 'blocked' => 'Blocked'],
            ] as $field => $options)
                <div class="col-md-4">
                    <label class="form-label small text-muted">{{ str($field)->replace('_', ' ')->title() }}</label>
                    <select name="{{ $field }}" class="form-select bg-dark-soft border-0 rounded-3 @error($field) is-invalid @enderror">
                        @if ($field === 'feed_type')
                            <option value="">Not Set</option>
                        @endif
                        @foreach ($options as $value => $label)
                            <option value="{{ $value }}" @selected(old($field, $post->{$field}) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error($field)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
            @endforeach

            <div class="col-12">
                <div class="d-flex flex-wrap gap-4">
                    @foreach ([
                        'allow_comments' => 'Allow comments',
                        'allow_shares' => 'Allow shares',
                        'rating_enabled' => 'Enable ratings',
                    ] as $field => $label)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="{{ $field }}" value="1" id="{{ $field }}" @checked(old($field, $post->{$field}))>
                            <label class="form-check-label" for="{{ $field }}">{{ $label }}</label>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4">
            <a href="{{ route('admin.posts.show', $post) }}" class="btn btn-outline-dynamic rounded-3">Cancel</a>
            <button class="btn bg-primary-gradient text-white rounded-3 border-0" type="submit">
                <i class="bi bi-save me-2"></i>Save Post
            </button>
        </div>
    </form>
</div>
@endsection
