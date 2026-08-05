{{--
    Shared metric card grid. Overview, Audience and Engagement all render
    through this, so a card looks the same wherever it appears.

    $cards       — label, value, sub, delta, icon, accent (deltaLabel optional)
    $columnClass — grid width, so a section can pick 3-across or 4-across

    A null delta hides the comparison line, for metrics that have no
    previous-period figure to compare against.
--}}
<div class="row g-2 g-xl-3">
    @foreach ($cards as $card)
        <div class="{{ $columnClass ?? 'col-12 col-sm-6 col-xl-4' }}">
            <div class="card glass border-0 rounded-4 p-3 stat-card h-100 metric-card">
                <div class="d-flex justify-content-between align-items-start mb-3 gap-3">
                    <div class="min-w-0">
                        <p class="mini-label text-muted mb-2">{{ $card['label'] }}</p>
                        <h2 class="stat-value text-white mb-1">{{ $card['value'] }}</h2>
                        <p class="text-muted small mb-0">{{ $card['sub'] }}</p>
                    </div>
                    <div class="stat-icon {{ $card['accent'] }}">
                        <i class="bi {{ $card['icon'] }}"></i>
                    </div>
                </div>
                @if (! is_null($card['delta']))
                    <div class="small fw-semibold {{ $card['delta'] < 0 ? 'text-danger' : 'text-success' }} d-flex align-items-center gap-1 flex-wrap">
                        <i class="bi {{ $card['delta'] < 0 ? 'bi-arrow-down-right' : 'bi-arrow-up-right' }}"></i>
                        <span>{{ $card['delta'] }}%</span>
                        <span class="text-muted fw-normal">{{ $card['deltaLabel'] ?? 'vs last period' }}</span>
                    </div>
                @endif
            </div>
        </div>
    @endforeach
</div>
