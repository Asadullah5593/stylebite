@php
    $sections = [
        '' => 'All media',
        'posts' => 'Posts',
        'stories' => 'Stories',
        'reels' => 'Reels',
        'food' => 'Food',
    ];
    $current = request('section', '');
    $kept = request()->except(['section', 'page']);
@endphp

<div class="d-flex flex-wrap gap-2 mb-3">
    @foreach ($sections as $value => $label)
        @php
            $query = $kept;
            if ($value !== '') {
                $query['section'] = $value;
            }
        @endphp
        <a
            href="{{ request()->url().($query === [] ? '' : '?'.http_build_query($query)) }}"
            class="btn btn-sm rounded-3 {{ $current === $value ? 'btn-primary' : 'btn-outline-dynamic' }}"
        >{{ $label }}</a>
    @endforeach
</div>
