{{--
    KPI tile grid for the dashboard tabs: uppercase label, large value, sub
    line, optional delta comparison. A tile carrying a `route` renders as a
    link so it doubles as a shortcut to its queue.

    $cards       — label, value, sub, delta (deltaLabel, route optional)
    $columnClass — grid width, defaults to four across on xl

    A null delta hides the comparison line, for metrics that have no
    previous-period figure to compare against.
--}}
<div class="row g-2 g-xl-3">
    @foreach ($cards as $card)
        @php $tag = empty($card['route']) ? 'div' : 'a'; @endphp
        <div class="{{ $columnClass ?? 'col-12 col-sm-6 col-xl-3' }}">
            <{{ $tag }}{!! $tag === 'a' ? ' href="'.e($card['route']).'"' : '' !!} class="card glass border-0 rounded-4 p-3 stat-card metric-card kpi-tile h-100 text-decoration-none">
                <p class="mini-label text-muted mb-2">{{ $card['label'] }}</p>
                <h2 class="stat-value text-white mb-1">{{ $card['value'] }}</h2>
                @if (($card['sub'] ?? '') !== '')
                    <p class="text-muted small mb-0">{{ $card['sub'] }}</p>
                @endif
                @if (! is_null($card['delta'] ?? null))
                    <div class="small fw-semibold {{ $card['delta'] < 0 ? 'text-danger' : 'text-success' }} d-flex align-items-center gap-1 flex-wrap kpi-delta">
                        <i class="bi {{ $card['delta'] < 0 ? 'bi-arrow-down-right' : 'bi-arrow-up-right' }}"></i>
                        <span>{{ $card['delta'] }}%</span>
                        <span class="text-muted fw-normal">{{ $card['deltaLabel'] ?? 'vs last period' }}</span>
                    </div>
                @endif
            </{{ $tag }}>
        </div>
    @endforeach
</div>
