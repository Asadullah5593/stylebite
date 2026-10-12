@php
    $file = $media->optimized_url ?: $media->file_url;
    $thumb = $media->thumbnail_url;
    if (! $file && $media->relationLoaded('upload')) {
        $file = $media->upload?->file_url;
        $thumb = $thumb ?: $media->upload?->thumbnail_url;
    }
    $fileUrl = $file ? stylebite_asset_url($file) : null;
    $thumbUrl = $thumb ? stylebite_asset_url($thumb) : null;
    $isVideo = $media->media_type === 'video';
    $width = $width ?? 72;
    $height = $height ?? 72;
    $large = $large ?? false;
@endphp

@if ($isVideo && $large && $fileUrl)
    <video
        src="{{ $fileUrl }}"
        @if ($thumbUrl) poster="{{ $thumbUrl }}" @endif
        controls
        playsinline
        class="rounded-3 border border-white-05 bg-black w-100"
        style="max-height: 480px; object-fit: contain;"
    ></video>
@elseif ($isVideo && $thumbUrl)
    <a href="{{ $fileUrl ?: $thumbUrl }}" target="_blank" rel="noopener" class="d-inline-block position-relative">
        <img
            src="{{ $thumbUrl }}"
            alt="Reel thumbnail"
            class="rounded-3 border border-white-05"
            style="width: {{ $width }}px; height: {{ $height }}px; object-fit: cover; background: #000;"
        >
        <span class="position-absolute top-50 start-50 translate-middle text-white" style="text-shadow: 0 1px 4px #000;"><i class="bi bi-play-circle-fill"></i></span>
    </a>
@elseif ($isVideo && $fileUrl)
    <video
        src="{{ $fileUrl }}"
        muted
        playsinline
        preload="metadata"
        class="rounded-3 border border-white-05 bg-black"
        style="width: {{ $width }}px; height: {{ $height }}px; object-fit: cover;"
    ></video>
@elseif ($fileUrl || $thumbUrl)
    <a href="{{ $fileUrl ?: $thumbUrl }}" target="_blank" rel="noopener" class="d-inline-block">
        <img
            src="{{ $thumbUrl ?: $fileUrl }}"
            alt="Post media"
            class="rounded-3 border border-white-05"
            style="width: {{ $large ? '100%' : $width.'px' }}; height: {{ $large ? 'auto' : $height.'px' }}; max-height: {{ $large ? '480px' : $height.'px' }}; object-fit: {{ $large ? 'contain' : 'cover' }}; background: rgba(255,255,255,0.04);"
        >
    </a>
@else
    <div class="rounded-3 border border-white-05 d-flex align-items-center justify-content-center text-muted" style="width: {{ $width }}px; height: {{ $height }}px;">
        <i class="bi bi-image"></i>
    </div>
@endif
