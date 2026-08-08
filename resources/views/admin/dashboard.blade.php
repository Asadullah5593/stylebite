@extends('admin.layouts.app')

@section('content')
<div class="admin-dashboard">
    <div class="mb-4">
        <nav class="d-flex align-items-center gap-3 text-muted small mb-3 flex-wrap dashboard-breadcrumb">
            <a href="{{ route('admin.dashboard') }}" class="text-decoration-none breadcrumb-home">
                <i class="bi bi-house-door"></i>
            </a>
            <i class="bi bi-chevron-right opacity-50"></i>
            <span class="fw-bold text-white">Dashboard</span>
        </nav>

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-4">
            <div>
                <h1 class="dashboard-title mb-1">Dashboard</h1>
                <p class="dashboard-subtitle mb-0">Action queues, growth signals, and operational health in one place.</p>
            </div>
            <div class="dashboard-date">{{ now()->format('l, F j, Y') }}</div>
        </div>
    </div>

    <section class="mb-4">
        <div class="section-label mb-3">
            <i class="bi bi-stars"></i>
            <span>Needs your attention</span>
        </div>
        <div class="card glass border-0 rounded-4 attention-strip">
            @foreach ($actionCards as $card)
                <a href="{{ $card['route'] }}" class="attention-item text-decoration-none">
                    <div class="action-icon {{ $card['urgency'] }}">{{ $card['count'] }}</div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="attention-title text-truncate">{{ $card['label'] }}</div>
                        <div class="text-muted extra-small text-truncate">{{ $card['hint'] }}</div>
                    </div>
                    <i class="bi bi-chevron-right text-muted"></i>
                </a>
            @endforeach
        </div>
    </section>

    <ul class="nav dash-tabs" id="dashboardTabs" role="tablist">
        @foreach ([
            'pane-overview' => 'Overview',
            'pane-audience' => 'Audience',
            'pane-content' => 'Content',
            'pane-money' => 'Money',
            'pane-trust' => 'Trust & Safety',
        ] as $paneId => $tabLabel)
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $loop->first ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#{{ $paneId }}" type="button" role="tab" aria-controls="{{ $paneId }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}">
                    {{ $tabLabel }}
                </button>
            </li>
        @endforeach
    </ul>

    <div class="tab-content dash-tab-content">
        <div class="tab-pane fade show active" id="pane-overview" role="tabpanel">
            <section class="mb-4">
                @include('admin.partials.metric-cards', ['cards' => $tabTiles['overview']])
            </section>

            <section class="mb-4">
                <div class="row g-3 g-xl-4">
                    <div class="col-12 col-xl-8">
                        <div class="card glass border-0 rounded-4 p-4 chart-card h-100 chart-shell">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                                <div>
                                    <h3 class="panel-title mb-1">Users & Posts Growth</h3>
                                    <p class="text-muted panel-subtitle mb-0">Last 14 days</p>
                                </div>
                                <span class="chart-link"><i class="bi bi-graph-up-arrow"></i></span>
                            </div>
                            <div class="chart-wrap">
                                <canvas id="growthChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-xl-4">
                        @include('admin.partials.withdrawal-queue')
                    </div>
                </div>
            </section>

            <section>
                <div class="row g-3 g-xl-4">
                    <div class="col-12 col-xl-6">
                        <div class="card glass border-0 rounded-4 p-4 chart-card h-100 chart-shell">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                                <div>
                                    <h3 class="panel-title mb-1">Top Posts</h3>
                                    <p class="text-muted panel-subtitle mb-0">Images & carousels · by likes, comments & shares</p>
                                </div>
                                <a href="{{ route('admin.posts.all_posts') }}" class="panel-link">All posts</a>
                            </div>
                            @if (empty($topPosts))
                                <div class="chart-wrap d-flex align-items-center justify-content-center text-muted small">
                                    No engagement recorded yet.
                                </div>
                            @else
                                <div class="chart-wrap">
                                    <canvas id="topPostsChartOverview"></canvas>
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="col-12 col-xl-6">
                        <div class="card glass border-0 rounded-4 p-3 p-xl-4 h-100 list-shell">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div>
                                    <h3 class="panel-title mb-1">Activity Feed</h3>
                                    <p class="text-muted extra-small mb-0">System & admin events</p>
                                </div>
                                <a href="{{ route('admin.activity.activity_logs') }}" class="panel-link">View all</a>
                            </div>
                            <div class="d-flex flex-column gap-1 bottom-card-list activity-list">
                                @foreach ($recentActivity as $entry)
                                    <a href="{{ route('admin.activity.activity_logs') }}" class="bottom-list-row activity-row text-decoration-none">
                                        <div class="timeline-icon"><i class="bi bi-clock-history"></i></div>
                                        <div class="flex-grow-1 min-w-0">
                                            <p class="recent-title mb-1 activity-title">{{ $entry['action'] }}</p>
                                            <p class="text-muted extra-small mb-0 activity-meta">{{ $entry['meta'] }}</p>
                                        </div>
                                        <span class="text-muted extra-small activity-time">{{ $entry['time'] }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div class="tab-pane fade" id="pane-audience" role="tabpanel">
            <section class="mb-4">
                @include('admin.partials.metric-cards', ['cards' => $tabTiles['audience']])
            </section>

            <section>
                <div class="row g-3 g-xl-4">
                    <div class="col-12 col-xl-6">
                        <div class="card glass border-0 rounded-4 p-3 p-xl-4 h-100 list-shell">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div>
                                    <h3 class="panel-title mb-1">Recent Users</h3>
                                    <p class="text-muted extra-small mb-0">Latest accounts to join</p>
                                </div>
                                <a href="{{ route('admin.users.all_users') }}" class="panel-link">View all</a>
                            </div>
                            <div class="d-flex flex-column gap-1 bottom-card-list">
                                @foreach ($recentUsers as $user)
                                    <a href="{{ route('admin.users.show', $user['id']) }}" class="bottom-list-row user-row text-decoration-none">
                                        @php
                                            $avatar = $user['avatar'];
                                            $avatarUrl = $avatar ? (str_starts_with($avatar, 'http') || str_starts_with($avatar, '/') ? $avatar : asset($avatar)) : null;
                                        @endphp
                                        @if ($avatarUrl)
                                            <img src="{{ $avatarUrl }}" alt="{{ $user['name'] }}" class="recent-avatar">
                                        @else
                                            <div class="recent-avatar avatar-fallback">{{ str($user['name'])->substr(0, 1)->upper() }}</div>
                                        @endif
                                        <div class="flex-grow-1 min-w-0">
                                            <p class="recent-title mb-0 user-name">{{ $user['name'] }}</p>
                                            <p class="text-muted extra-small mb-0 user-handle">{{ $user['username'] }}</p>
                                        </div>
                                        <span class="pill-status role {{ strtolower(str_replace(' ', '-', $user['role'])) }}">{{ $user['role'] }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div class="tab-pane fade" id="pane-content" role="tabpanel">
            <section class="mb-4">
                @include('admin.partials.metric-cards', ['cards' => $tabTiles['content']])
            </section>

            <section class="mb-4">
                <div class="row g-3 g-xl-4">
                    @foreach ([['Top Posts', 'topPostsChart', $topPosts, 'Images & carousels'], ['Top Reels', 'topReelsChart', $topReels, 'Video posts']] as [$title, $canvasId, $rows, $subtitle])
                        <div class="col-12 col-xl-6">
                            <div class="card glass border-0 rounded-4 p-4 chart-card h-100 chart-shell">
                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                                    <div>
                                        <h3 class="panel-title mb-1">{{ $title }}</h3>
                                        <p class="text-muted panel-subtitle mb-0">{{ $subtitle }} · by likes, comments & shares</p>
                                    </div>
                                    <a href="{{ route('admin.posts.all_posts') }}" class="panel-link">All posts</a>
                                </div>
                                @if (empty($rows))
                                    <div class="chart-wrap d-flex align-items-center justify-content-center text-muted small">
                                        No engagement recorded yet.
                                    </div>
                                @else
                                    <div class="chart-wrap">
                                        <canvas id="{{ $canvasId }}"></canvas>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            <section>
                <div class="row g-3 g-xl-4">
                    <div class="col-12 col-lg-6">
                        <div class="card glass border-0 rounded-4 p-4 chart-card h-100 chart-shell">
                            <h3 class="panel-title mb-1">Media Uploads by Type</h3>
                            <p class="text-muted panel-subtitle mb-4">Distribution of uploads</p>
                            <div class="chart-wrap">
                                <canvas id="mediaChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div class="tab-pane fade" id="pane-money" role="tabpanel">
            <section class="mb-4">
                @include('admin.partials.metric-cards', ['cards' => $tabTiles['money']])
            </section>

            <section>
                <div class="row g-3 g-xl-4">
                    <div class="col-12 col-xl-6">
                        <div class="card glass border-0 rounded-4 p-4 chart-card h-100 chart-shell">
                            <h3 class="panel-title mb-1">Earnings & Withdrawals</h3>
                            <p class="text-muted panel-subtitle mb-4">Last 7 days</p>
                            <div class="chart-wrap">
                                <canvas id="earningsChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-xl-6">
                        @include('admin.partials.withdrawal-queue')
                    </div>
                </div>
            </section>
        </div>

        <div class="tab-pane fade" id="pane-trust" role="tabpanel">
            <section class="mb-4">
                @include('admin.partials.metric-cards', ['cards' => $tabTiles['trust']])
            </section>

            <section>
                <div class="row g-3 g-xl-4">
                    <div class="col-12 col-xl-5">
                        <div class="card glass border-0 rounded-4 p-4 chart-card h-100 chart-shell">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                                <div>
                                    <h3 class="panel-title mb-1">Top Report Reasons</h3>
                                    <p class="text-muted panel-subtitle mb-0">Last 30 days</p>
                                </div>
                                <a href="{{ route('admin.moderation.reports') }}" class="panel-link">Review queue</a>
                            </div>
                            <div class="chart-wrap">
                                <canvas id="reportReasonsChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-xl-7">
                        <div class="card glass border-0 rounded-4 p-3 p-xl-4 h-100 list-shell">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div>
                                    <h3 class="panel-title mb-1">Recent Reports</h3>
                                    <p class="text-muted extra-small mb-0">Newest moderation items and assignment state</p>
                                </div>
                                <a href="{{ route('admin.moderation.reports') }}" class="panel-link">Open reports</a>
                            </div>
                            <div class="d-flex flex-column gap-2 bottom-card-list">
                                @forelse ($recentReports as $report)
                                    <a href="{{ route('admin.moderation.reports') }}" class="bottom-list-row queue-row text-decoration-none">
                                        <div class="queue-icon warning"><i class="bi bi-flag"></i></div>
                                        <div class="flex-grow-1 min-w-0">
                                            <p class="recent-title mb-1">#{{ $report['id'] }} {{ $report['reason'] }}</p>
                                            <p class="text-muted extra-small mb-0">{{ $report['target'] }} · {{ $report['reporter'] }}{{ $report['reviewer'] ? ' · Reviewer: '.$report['reviewer'] : '' }}</p>
                                        </div>
                                        <div class="text-end">
                                            <span class="pill-status report-status {{ str_replace('_', '-', $report['status']) }}">{{ str($report['status'])->replace('_', ' ')->title() }}</span>
                                            <div class="text-muted extra-small mt-1">{{ $report['time'] }}</div>
                                        </div>
                                    </a>
                                @empty
                                    <div class="text-muted small">No reports available yet.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>

<style>
    .admin-dashboard {
        --primary-color: #ff557a;
        --secondary-color: #ff8a57;
        --info-color: #5c94ff;
        --success-color: #32d36b;
        --warning-color: #f2a81d;
        --danger-color: #ff505f;
        --muted-color: #a79aa7;
        --strip-divider: rgba(255, 255, 255, 0.07);
    }

    .glass {
        background: rgba(38, 28, 36, 0.88);
        backdrop-filter: blur(18px);
        border: 1px solid rgba(255, 255, 255, 0.08) !important;
        border-radius: var(--admin-card-radius) !important;
        box-shadow: inset 0 1px 0 rgba(255,255,255,0.015), 0 16px 36px rgba(0, 0, 0, 0.16);
        overflow: hidden;
    }

    .dashboard-breadcrumb,
    .dashboard-date {
        color: #aaa0ad !important;
    }

    .breadcrumb-home {
        color: #b9b0bd;
        font-size: 1.15rem;
    }

    .dashboard-title {
        font-size: clamp(1.7rem, 2.5vw, 2.7rem);
        font-weight: 700;
        letter-spacing: -0.04em;
        line-height: 1;
    }

    .dashboard-subtitle {
        color: #f1e8ee;
        font-size: 0.9rem;
    }

    .dashboard-date {
        color: #f7eff4 !important;
        font-size: 0.9rem;
        font-weight: 600;
    }

    .section-label,
    .mini-label {
        text-transform: uppercase;
        letter-spacing: 0.04em;
        font-size: 0.78rem;
        font-weight: 800;
    }

    .section-label {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        color: #f2e9ef;
    }

    /* ── Needs-your-attention strip ─────────────────────────────────── */

    .attention-strip {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
    }

    .attention-item {
        display: flex;
        align-items: center;
        gap: 0.9rem;
        min-width: 0;
        padding: 1.05rem 1.3rem;
        transition: background-color 0.2s ease;
    }

    .attention-item:hover {
        background-color: rgba(255, 255, 255, 0.04);
    }

    .attention-title {
        color: #ffffff;
        font-weight: 700;
        font-size: 0.86rem;
    }

    @media (min-width: 1200px) {
        .attention-item + .attention-item {
            border-left: 1px solid var(--strip-divider);
        }
    }

    @media (min-width: 768px) and (max-width: 1199.98px) {
        .attention-strip {
            grid-template-columns: repeat(2, 1fr);
        }

        .attention-item:nth-child(even) {
            border-left: 1px solid var(--strip-divider);
        }

        .attention-item:nth-child(n+3) {
            border-top: 1px solid var(--strip-divider);
        }
    }

    @media (max-width: 767.98px) {
        .attention-strip {
            grid-template-columns: 1fr;
        }

        .attention-item + .attention-item {
            border-top: 1px solid var(--strip-divider);
        }
    }

    .action-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        width: 46px;
        height: 46px;
        border-radius: 14px;
        font-size: 1.05rem;
        font-weight: 800;
        border: 1px solid rgba(255, 255, 255, 0.10);
    }

    .action-icon.high {
        color: var(--danger-color);
        background: rgba(132, 36, 49, 0.28);
        border-color: rgba(183, 53, 70, 0.42);
    }

    .action-icon.med {
        color: var(--warning-color);
        background: rgba(100, 66, 26, 0.32);
        border-color: rgba(154, 108, 37, 0.42);
    }

    .action-icon.low {
        color: var(--info-color);
        background: rgba(44, 58, 101, 0.30);
        border-color: rgba(72, 106, 190, 0.35);
    }

    /* ── Tab bar ────────────────────────────────────────────────────── */

    .dash-tabs {
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        gap: 0.25rem;
        flex-wrap: wrap;
    }

    .dash-tabs .nav-link {
        border: 0;
        border-bottom: 2px solid transparent;
        border-radius: 0;
        background: transparent;
        color: #b6abbc;
        font-weight: 700;
        font-size: 0.9rem;
        padding: 0.65rem 0.9rem;
        margin-bottom: -1px;
    }

    .dash-tabs .nav-link:hover {
        color: #ffffff;
    }

    .dash-tabs .nav-link.active {
        color: #ffffff;
        background: transparent;
        border-bottom-color: var(--primary-color);
    }

    .dash-tab-content {
        padding-top: 1.4rem;
    }

    /* ── KPI tiles ──────────────────────────────────────────────────── */

    .kpi-tile {
        min-height: 150px;
    }

    .kpi-tile .stat-value {
        font-size: clamp(1.7rem, 2.2vw, 2.05rem);
    }

    .kpi-delta {
        margin-top: auto;
        padding-top: 0.55rem;
    }

    .stat-card,
    .metric-card,
    .chart-shell,
    .list-shell {
        overflow: hidden;
    }

    .stat-card {
        transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
    }

    .stat-card:hover,
    .chart-card:hover {
        transform: translateY(-3px);
        border-color: rgba(255, 85, 122, 0.18) !important;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.22);
    }

    .stat-value {
        font-size: clamp(1.95rem, 2.4vw, 2.3rem);
        font-weight: 800;
        line-height: 1.1;
    }

    /* ── Panels & lists ─────────────────────────────────────────────── */

    .chart-shell {
        min-height: 400px;
    }

    .list-shell {
        min-height: 400px;
        background: rgba(29, 24, 34, 0.96);
    }

    .bottom-card-list {
        min-width: 0;
    }

    .bottom-list-row {
        min-width: 0;
        padding: 0.7rem 0.8rem;
        margin: 0 -0.35rem;
        border-radius: 12px;
        transition: background-color 0.2s ease;
        cursor: pointer;
    }

    .bottom-list-row:hover {
        background-color: rgba(255, 255, 255, 0.05);
    }

    .user-row,
    .activity-row,
    .queue-row {
        display: grid;
        width: 100%;
        column-gap: 0.875rem;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr) auto;
    }

    .user-row {
        align-items: center;
    }

    .activity-row,
    .queue-row {
        align-items: start;
    }

    .queue-icon,
    .timeline-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        font-size: 0.95rem;
    }

    .queue-icon.warning {
        color: #f0aa1d;
        background: rgba(109, 70, 31, 0.55);
    }

    .queue-icon.success {
        color: #5ade8b;
        background: rgba(37, 90, 54, 0.52);
    }

    .timeline-icon {
        color: #ff5c81;
        background: rgba(116, 44, 61, 0.52);
    }

    .chart-link {
        color: #ff5d82;
        font-size: 1.35rem;
    }

    .chart-wrap {
        position: relative;
        min-height: 276px;
    }

    .extra-small {
        font-size: 0.78rem;
    }

    .recent-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
    }

    .avatar-fallback {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        background: linear-gradient(135deg, #ff557a, #ff8a57);
        font-weight: 800;
    }

    .pill-status {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        padding: 0.34rem 0.95rem;
        font-size: 0.76rem;
        border: 1px solid transparent;
        white-space: nowrap;
        font-weight: 700;
        flex-shrink: 0;
    }

    .pill-status.role.user,
    .pill-status.role.creator {
        background: rgba(43, 43, 54, 0.92);
        color: #bcb7c7;
        border-color: rgba(255, 255, 255, 0.06);
    }

    .pill-status.role.moderator {
        background: rgba(46, 64, 128, 0.36);
        color: #62a0ff;
        border-color: rgba(92, 148, 255, 0.35);
    }

    .pill-status.role.admin {
        background: rgba(97, 35, 54, 0.92);
        color: #ff6688;
        border-color: rgba(255, 102, 136, 0.25);
    }

    .pill-status.report-status.open,
    .pill-status.payout-status.pending {
        background: rgba(95, 63, 20, 0.9);
        color: #ffb31f;
        border-color: rgba(255, 179, 31, 0.28);
    }

    .pill-status.report-status.resolved,
    .pill-status.payout-status.completed {
        background: rgba(24, 84, 51, 0.88);
        color: #35dc76;
        border-color: rgba(53, 220, 118, 0.24);
    }

    .pill-status.report-status.rejected,
    .pill-status.report-status.under-review,
    .pill-status.payout-status.processing {
        background: rgba(43, 43, 54, 0.92);
        color: #bcb7c7;
        border-color: rgba(255,255,255,0.06);
    }

    .panel-title {
        color: #ffffff;
        font-size: 1rem;
        font-weight: 800;
        letter-spacing: -0.03em;
    }

    .panel-subtitle {
        color: #f0e7ee !important;
        font-size: 0.82rem;
    }

    .panel-link {
        color: #ff5e82;
        text-decoration: none;
        font-size: 0.86rem;
        font-weight: 700;
        flex-shrink: 0;
    }

    .recent-title {
        color: #ffffff;
        font-size: 0.80rem;
        font-weight: 700;
        line-height: 1.2;
    }

    .user-name,
    .user-handle,
    .activity-title,
    .activity-meta {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        min-width: 0;
    }

    .activity-time {
        white-space: nowrap;
        align-self: start;
        padding-top: 0.15rem;
    }

    .metric-card .text-muted,
    .attention-item .text-muted,
    .chart-shell .text-muted,
    .list-shell .text-muted {
        color: #f0e7ee !important;
    }

    .activity-list {
        max-height: 360px;
        overflow-y: auto;
        padding-right: 0.25rem;
    }

    .activity-list::-webkit-scrollbar {
        width: 6px;
    }

    .activity-list::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.18);
        border-radius: 999px;
    }

    /* ── Light theme (new elements only; shared classes are handled by
         the layout's theme-light block) ──────────────────────────────── */

    body.theme-light .admin-dashboard {
        --strip-divider: rgba(92, 62, 73, 0.10);
    }

    body.theme-light .admin-dashboard .attention-item:hover {
        background-color: rgba(255, 120, 158, 0.05);
    }

    body.theme-light .admin-dashboard .attention-title {
        color: #26212c;
    }

    body.theme-light .admin-dashboard .attention-item .text-muted {
        color: #6f6a78 !important;
    }

    body.theme-light .admin-dashboard .dash-tabs {
        border-bottom-color: rgba(92, 62, 73, 0.12);
    }

    body.theme-light .admin-dashboard .dash-tabs .nav-link {
        color: #6f6a78;
        background: transparent;
    }

    body.theme-light .admin-dashboard .dash-tabs .nav-link:hover,
    body.theme-light .admin-dashboard .dash-tabs .nav-link.active {
        color: #26212c;
    }

    body.theme-light .admin-dashboard .dash-tabs .nav-link.active {
        border-bottom-color: var(--primary-color);
    }

    @media (max-width: 767.98px) {
        .metric-card,
        .kpi-tile,
        .chart-shell,
        .list-shell {
            min-height: auto;
        }

        .chart-wrap {
            min-height: 240px;
        }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const growthData = @json($growth);
    const mediaData = @json($mediaByType);
    const earningsData = @json($earningsOverview);
    const reportReasonsData = @json($reportReasons);
    const topPostsData = @json($topPosts);
    const topReelsData = @json($topReels);
    const TAB_STORAGE_KEY = 'stylebite-admin-dashboard-tab';
    const chartInstances = new Map();

    const getThemePalette = () => {
        const isLight = document.body.classList.contains('theme-light');

        return {
            chartFontColor: isLight ? 'rgba(72, 64, 78, 0.86)' : 'rgba(203, 194, 206, 0.82)',
            gridColor: isLight ? 'rgba(124, 102, 112, 0.14)' : 'rgba(120, 108, 128, 0.20)',
            tooltipBg: isLight ? '#fff7f3' : '#111018',
            tooltipBorder: isLight ? 'rgba(95, 72, 81, 0.12)' : 'rgba(255,255,255,0.08)',
            primary: '#ff557a',
            secondary: '#ff8a57',
            tertiary: '#5c94ff',
            quaternary: '#32d36b',
            neutralBar: isLight ? '#d7c1bb' : '#c5c5c5',
            lineFillOne: isLight ? 'rgba(255, 85, 122, 0.12)' : 'rgba(255, 85, 122, 0.18)',
            lineFillTwo: isLight ? 'rgba(255, 138, 87, 0.10)' : 'rgba(255, 138, 87, 0.16)',
            lineSubtleOne: isLight ? 'rgba(255, 85, 122, 0.05)' : 'rgba(255, 85, 122, 0.08)',
            lineSubtleTwo: isLight ? 'rgba(255, 138, 87, 0.05)' : 'rgba(255, 138, 87, 0.08)',
        };
    };

    const roundedMax = (values, minimum = 10) => {
        const max = Math.max(minimum, ...values.map(value => Number(value) || 0));
        return Math.ceil((max * 1.2) / minimum) * minimum;
    };

    const tooltipStyle = palette => ({
        backgroundColor: palette.tooltipBg,
        borderColor: palette.tooltipBorder,
        borderWidth: 1,
        titleColor: palette.chartFontColor,
        bodyColor: palette.chartFontColor
    });

    const buildGrowthChart = (canvas, palette) => new Chart(canvas, {
        type: 'line',
        data: {
            labels: growthData.map(item => item.date),
            datasets: [
                {
                    label: 'Users',
                    data: growthData.map(item => item.users),
                    borderColor: palette.primary,
                    backgroundColor: palette.lineFillOne,
                    fill: true,
                    tension: 0.32,
                    pointRadius: 0,
                    pointHoverRadius: 5
                },
                {
                    label: 'Posts',
                    data: growthData.map(item => item.posts),
                    borderColor: palette.secondary,
                    backgroundColor: palette.lineFillTwo,
                    fill: true,
                    tension: 0.32,
                    pointRadius: 0,
                    pointHoverRadius: 5
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: tooltipStyle(palette)
            },
            scales: {
                x: {
                    ticks: { color: palette.chartFontColor },
                    grid: { color: palette.gridColor, borderDash: [4, 4] }
                },
                y: {
                    min: 0,
                    suggestedMax: roundedMax([...growthData.map(item => item.users), ...growthData.map(item => item.posts)]),
                    ticks: { color: palette.chartFontColor, precision: 0 },
                    grid: { color: palette.gridColor, borderDash: [4, 4] }
                }
            }
        }
    });

    const buildReportReasonsChart = (canvas, palette) => new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: reportReasonsData.map(item => item.name),
            datasets: [{
                data: reportReasonsData.map(item => item.value),
                backgroundColor: [palette.primary, palette.secondary, palette.tertiary, palette.quaternary, '#f2a81d', '#8b7dff'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { color: palette.chartFontColor, usePointStyle: true, boxWidth: 10 }
                },
                tooltip: tooltipStyle(palette)
            },
            cutout: '68%'
        }
    });

    // Top posts and top reels share one horizontal stacked bar: each row is
    // a single item, split into the three engagement types that make up it.
    const buildTopChart = (canvas, rows, palette) => {
        if (!rows.length) {
            return null;
        }

        const series = [
            { label: 'Likes', key: 'likes', color: palette.primary },
            { label: 'Comments', key: 'comments', color: palette.tertiary },
            { label: 'Shares', key: 'shares', color: palette.quaternary }
        ];

        return new Chart(canvas, {
            type: 'bar',
            data: {
                labels: rows.map(row => row.label),
                datasets: series.map(item => ({
                    label: item.label,
                    data: rows.map(row => row[item.key]),
                    backgroundColor: item.color,
                    borderRadius: 6,
                    maxBarThickness: 24
                }))
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: palette.chartFontColor, usePointStyle: true, boxWidth: 10 }
                    },
                    tooltip: {
                        ...tooltipStyle(palette),
                        callbacks: {
                            // The bar label is a truncated caption, so name the author here.
                            afterTitle: items => rows[items[0].dataIndex].author
                        }
                    }
                },
                scales: {
                    x: {
                        stacked: true,
                        min: 0,
                        ticks: { color: palette.chartFontColor, precision: 0 },
                        grid: { color: palette.gridColor, borderDash: [4, 4] }
                    },
                    y: {
                        stacked: true,
                        ticks: { color: palette.chartFontColor },
                        grid: { display: false }
                    }
                }
            }
        });
    };

    const buildMediaChart = (canvas, palette) => new Chart(canvas, {
        type: 'bar',
        data: {
            labels: mediaData.map(item => item.name),
            datasets: [{
                label: 'Uploads',
                data: mediaData.map(item => item.value),
                backgroundColor: [palette.primary, palette.primary, palette.primary, palette.primary, palette.neutralBar, palette.primary],
                borderRadius: 12,
                maxBarThickness: 70
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: {
                    ticks: { color: palette.chartFontColor },
                    grid: { color: palette.gridColor, borderDash: [4, 4] }
                },
                y: {
                    min: 0,
                    suggestedMax: roundedMax(mediaData.map(item => item.value), 5),
                    ticks: { color: palette.chartFontColor, precision: 0 },
                    grid: { color: palette.gridColor, borderDash: [4, 4] }
                }
            }
        }
    });

    const buildEarningsChart = (canvas, palette) => new Chart(canvas, {
        type: 'line',
        data: {
            labels: earningsData.map(item => item.date),
            datasets: [
                {
                    label: 'Earnings',
                    data: earningsData.map(item => item.earnings),
                    borderColor: palette.primary,
                    backgroundColor: palette.lineSubtleOne,
                    tension: 0.36,
                    fill: false,
                    pointRadius: 0
                },
                {
                    label: 'Withdrawals',
                    data: earningsData.map(item => item.withdrawals),
                    borderColor: palette.secondary,
                    backgroundColor: palette.lineSubtleTwo,
                    tension: 0.36,
                    fill: false,
                    pointRadius: 0
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { color: palette.chartFontColor, usePointStyle: true, boxWidth: 10 }
                }
            },
            scales: {
                x: {
                    ticks: { color: palette.chartFontColor },
                    grid: { color: palette.gridColor, borderDash: [4, 4] }
                },
                y: {
                    min: 0,
                    suggestedMax: roundedMax([...earningsData.map(item => item.earnings), ...earningsData.map(item => item.withdrawals)], 100),
                    ticks: { color: palette.chartFontColor },
                    grid: { color: palette.gridColor, borderDash: [4, 4] }
                }
            }
        }
    });

    const chartBuilders = {
        growthChart: (canvas, palette) => buildGrowthChart(canvas, palette),
        topPostsChartOverview: (canvas, palette) => buildTopChart(canvas, topPostsData, palette),
        topPostsChart: (canvas, palette) => buildTopChart(canvas, topPostsData, palette),
        topReelsChart: (canvas, palette) => buildTopChart(canvas, topReelsData, palette),
        mediaChart: (canvas, palette) => buildMediaChart(canvas, palette),
        earningsChart: (canvas, palette) => buildEarningsChart(canvas, palette),
        reportReasonsChart: (canvas, palette) => buildReportReasonsChart(canvas, palette),
    };

    const activePane = () => document.querySelector('.admin-dashboard .tab-pane.active');

    // Charts render inside tab panes, and a chart initialised in a hidden
    // (display: none) pane comes up with zero size — so each pane's charts
    // are built the first time that pane is actually shown.
    const initPaneCharts = pane => {
        if (!pane) {
            return;
        }

        const palette = getThemePalette();
        Chart.defaults.color = palette.chartFontColor;
        Chart.defaults.font.family = 'Manrope, system-ui, sans-serif';

        pane.querySelectorAll('canvas[id]').forEach(canvas => {
            const build = chartBuilders[canvas.id];

            if (!build || chartInstances.has(canvas.id)) {
                return;
            }

            const chart = build(canvas, palette);

            if (chart) {
                chartInstances.set(canvas.id, chart);
            }
        });
    };

    const destroyCharts = () => {
        chartInstances.forEach(chart => chart.destroy());
        chartInstances.clear();
    };

    const tabButtons = document.querySelectorAll('#dashboardTabs [data-bs-toggle="tab"]');

    tabButtons.forEach(button => {
        button.addEventListener('shown.bs.tab', event => {
            try {
                localStorage.setItem(TAB_STORAGE_KEY, event.target.getAttribute('data-bs-target'));
            } catch (error) {
                // Storage unavailable — the tab still works, it just won't persist.
            }

            initPaneCharts(document.querySelector(event.target.getAttribute('data-bs-target')));
        });
    });

    // Restore the last-viewed tab, then build whatever pane ends up visible.
    let storedTarget = null;
    try {
        storedTarget = localStorage.getItem(TAB_STORAGE_KEY);
    } catch (error) {
        storedTarget = null;
    }

    if (storedTarget && document.querySelector(storedTarget)) {
        const button = document.querySelector('#dashboardTabs [data-bs-target="' + storedTarget + '"]');

        if (button && !button.classList.contains('active')) {
            bootstrap.Tab.getOrCreateInstance(button).show();
        }
    }

    initPaneCharts(activePane());

    document.addEventListener('stylebite-theme-change', () => {
        destroyCharts();
        initPaneCharts(activePane());
    });
});
</script>
@endsection
