{{--
    One post-media slot, rendered as an actual preview rather than a URL string.

        @include('admin.posts.partials.media-thumb', ['item' => $media, 'size' => 52])

    `item` may be null (a post with no media) — the placeholder covers that, so
    callers never have to guard the include themselves.

    Video uses <video preload="metadata"> instead of an <img>: nothing in this
    schema generates poster frames (thumbnail_url is null on every row the app
    writes), so the browser pulling the first frame itself is the only preview
    there is. It is muted and uncontrolled, so it stays a thumbnail.

    Files whose bytes never made it to this host — older posts still pointing at
    Hostinger — fail to load with no event a server-side check would catch, so
    the onerror handler swaps in the placeholder rather than leaving a broken
    image icon in the table.
--}}
@php
    $size = $size ?? 52;
    $url = $item?->display_url;
    $thumb = $item?->display_thumbnail_url;
    $isVideo = $item?->media_type === 'video';
    $thumbId = 'media-thumb-'.($item?->id ?? 'none').'-'.uniqid();
@endphp

<div class="post-media-thumb rounded-3 bg-dark-soft border border-white-05 flex-shrink-0 position-relative overflow-hidden"
     style="width: {{ $size }}px; height: {{ $size }}px;">
    @if (! $url)
        <div class="d-flex align-items-center justify-content-center h-100 text-muted">
            <i class="bi bi-image"></i>
        </div>
    @else
        <a href="{{ $url }}" target="_blank" rel="noopener" class="d-block h-100 text-reset text-decoration-none" title="Open full media">
            <div id="{{ $thumbId }}-fallback" class="d-none align-items-center justify-content-center h-100 text-muted">
                <i class="bi {{ $isVideo ? 'bi-film' : 'bi-image-alt' }}"></i>
            </div>

            @if ($isVideo && ! $thumb)
                <video src="{{ $url }}#t=0.1" muted playsinline preload="metadata"
                       class="w-100 h-100" style="object-fit: cover;"
                       onerror="document.getElementById('{{ $thumbId }}-fallback').classList.replace('d-none','d-flex'); this.remove();"></video>
            @else
                <img src="{{ $thumb ?: $url }}" alt="Post media #{{ $item->id }}" loading="lazy"
                     class="w-100 h-100" style="object-fit: cover;"
                     onerror="document.getElementById('{{ $thumbId }}-fallback').classList.replace('d-none','d-flex'); this.remove();">
            @endif

            @if ($isVideo)
                <span class="position-absolute bottom-0 end-0 m-1 badge bg-dark bg-opacity-75 text-white rounded-pill d-flex align-items-center"
                      style="font-size: 0.55rem; padding: 0.15rem 0.35rem;">
                    <i class="bi bi-play-fill"></i>
                </span>
            @endif
        </a>
    @endif
</div>
