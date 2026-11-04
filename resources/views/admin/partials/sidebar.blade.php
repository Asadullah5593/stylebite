@php
    $sidebarCounts = $sidebarCounts ?? [
        'users' => 0,
        'verification_requests' => 0,
        'social' => 0,
        'posts' => 0,
        'comments' => 0,
        'engagement' => 0,
        'media' => 0,
        'memories' => 0,
        'messaging' => 0,
        'notifications' => 0,
        'moderation' => 0,
        'contests' => 0,
        'contest_prizes' => 0,
        'earnings' => 0,
        'settings' => 0,
    ];
@endphp

<aside
    id="sidebar"
    class="admin-sidebar offcanvas-md offcanvas-start text-white"
    tabindex="-1"
    aria-labelledby="adminSidebarLabel"
>
    <div class="offcanvas-header d-md-none border-bottom" style="border-color: var(--sb-sidebar-border) !important;">
        <h5 class="offcanvas-title mb-0" id="adminSidebarLabel">StyleBite Admin</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="Close"></button>
    </div>

    <div class="offcanvas-body d-flex flex-column p-0">
        <div class="sidebar-brand">
            <div class="sidebar-brand-icon">
                <i class="bi bi-stars"></i>
            </div>
            <div>
                <h5 class="mb-0">StyleBite</h5>
                <small>Admin Console</small>
            </div>
        </div>

        <nav class="flex-grow-1 overflow-auto py-3 px-3 scrollbar-hidden">
            <div class="sidebar-section-title">Overview</div>

            <a href="{{ route('admin.dashboard') }}" class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }} d-flex align-items-center gap-3 px-3 py-2 rounded-4 mb-4">
                <i class="bi bi-grid-1x2"></i>
                <span class="small fw-bold">Dashboard</span>
            </a>

            <div class="mb-4">
                <div class="sidebar-section-title">Community</div>
                <a href="{{ route('admin.users.all_users') }}" class="nav-link {{ request()->routeIs('admin.users*') && ! request()->routeIs('admin.users.verification_requests') ? 'active' : 'text-white-50' }} d-flex align-items-center gap-3 px-3 py-2 rounded-3 small">
                    <i class="bi bi-people"></i> <span>Users</span>
                    <span class="nav-count ms-auto">{{ number_format($sidebarCounts['users'] ?? 0) }}</span>
                </a>
                <a href="{{ route('admin.users.verification_requests', ['status' => 'pending']) }}" class="nav-link {{ request()->routeIs('admin.users.verification_requests') ? 'active' : 'text-white-50' }} d-flex align-items-center gap-3 px-3 py-2 rounded-3 small">
                    <i class="bi bi-patch-check"></i> <span>Verification</span>
                    <span class="nav-count ms-auto">{{ number_format($sidebarCounts['verification_requests'] ?? 0) }}</span>
                </a>
                <a href="{{ route('admin.social.follows') }}" class="nav-link {{ request()->routeIs('admin.social*') ? 'active' : 'text-white-50' }} d-flex align-items-center gap-3 px-3 py-2 rounded-3 small">
                    <i class="bi bi-diagram-3"></i> <span>Social Graph</span>
                    <span class="nav-count ms-auto">{{ number_format($sidebarCounts['social'] ?? 0) }}</span>
                </a>
            </div>

            <div class="mb-3">
                <div class="sidebar-section-title">Content</div>
                <a href="{{ route('admin.posts.all_posts') }}" class="nav-link {{ request()->routeIs('admin.posts*') ? 'active' : 'text-white-50' }} d-flex align-items-center gap-3 px-3 py-2 rounded-3 small">
                    <i class="bi bi-file-earmark-text"></i> <span>Posts</span>
                    <span class="nav-count ms-auto">{{ number_format($sidebarCounts['posts'] ?? 0) }}</span>
                </a>
                <a href="{{ route('admin.comments.comments') }}" class="nav-link {{ request()->routeIs('admin.comments*') ? 'active' : 'text-white-50' }} d-flex align-items-center gap-3 px-3 py-2 rounded-3 small">
                    <i class="bi bi-chat-left"></i> <span>Comments</span>
                    <span class="nav-count ms-auto">{{ number_format($sidebarCounts['comments'] ?? 0) }}</span>
                </a>
                <a href="{{ route('admin.engagement.post_likes') }}" class="nav-link {{ request()->routeIs('admin.engagement*') ? 'active' : 'text-white-50' }} d-flex align-items-center gap-3 px-3 py-2 rounded-3 small">
                    <i class="bi bi-heart"></i> <span>Engagement</span>
                    <span class="nav-count ms-auto">{{ number_format($sidebarCounts['engagement'] ?? 0) }}</span>
                </a>
                <a href="{{ route('admin.media.uploads') }}" class="nav-link {{ request()->routeIs('admin.media*') ? 'active' : 'text-white-50' }} d-flex align-items-center gap-3 px-3 py-2 rounded-3 small">
                    <i class="bi bi-image"></i> <span>Media & Tags</span>
                    <span class="nav-count ms-auto">{{ number_format($sidebarCounts['media'] ?? 0) }}</span>
                </a>
                <a href="{{ route('admin.memories.users') }}" class="nav-link {{ request()->routeIs('admin.memories*') ? 'active' : 'text-white-50' }} d-flex align-items-center gap-3 px-3 py-2 rounded-3 small">
                    <i class="bi bi-bookmark-heart"></i> <span>Memories</span>
                    <span class="nav-count ms-auto">{{ number_format($sidebarCounts['memories'] ?? 0) }}</span>
                </a>
                <a href="{{ route('admin.reviews.index') }}" class="nav-link {{ request()->routeIs('admin.reviews*') ? 'active' : 'text-white-50' }} d-flex align-items-center gap-3 px-3 py-2 rounded-3 small">
                    <i class="bi bi-shop"></i> <span>Restaurant reviews</span>
                </a>
            </div>

            <div class="mb-4">
                <div class="sidebar-section-title">Communication</div>
                <a href="{{ route('admin.messaging.conversations') }}" class="nav-link {{ request()->routeIs('admin.messaging*') ? 'active' : 'text-white-50' }} d-flex align-items-center gap-3 px-3 py-2 rounded-3 small">
                    <i class="bi bi-send"></i> <span>Messaging</span>
                    <span class="nav-count ms-auto">{{ number_format($sidebarCounts['messaging'] ?? 0) }}</span>
                </a>
                <a href="{{ route('admin.notifications.notifications') }}" class="nav-link {{ request()->routeIs('admin.notifications*') ? 'active' : 'text-white-50' }} d-flex align-items-center gap-3 px-3 py-2 rounded-3 small">
                    <i class="bi bi-bell"></i> <span>Notifications</span>
                    <span class="nav-count ms-auto">{{ number_format($sidebarCounts['notifications'] ?? 0) }}</span>
                </a>
            </div>

            <div class="mb-4">
                <div class="sidebar-section-title">Trust & Safety</div>
                <a href="{{ route('admin.moderation.reports') }}" class="nav-link {{ request()->routeIs('admin.moderation*') ? 'active' : 'text-white-50' }} d-flex align-items-center gap-3 px-3 py-2 rounded-3 small">
                    <i class="bi bi-shield"></i> <span>Moderation</span>
                    <span class="nav-count ms-auto">{{ number_format($sidebarCounts['moderation'] ?? 0) }}</span>
                </a>
                <a href="{{ route('admin.activity.activity_logs') }}" class="nav-link {{ request()->routeIs('admin.activity*') ? 'active' : 'text-white-50' }} d-flex align-items-center gap-3 px-3 py-2 rounded-3 small">
                    <i class="bi bi-activity"></i> <span>Activity Logs</span>
                </a>
            </div>

            <div class="mb-4">
                <div class="sidebar-section-title">Monetization</div>
                <a href="{{ route('admin.contests.contests') }}" class="nav-link {{ request()->routeIs('admin.contests*') ? 'active' : 'text-white-50' }} d-flex align-items-center gap-3 px-3 py-2 rounded-3 small">
                    <i class="bi bi-trophy"></i> <span>Contests</span>
                    <span class="nav-count ms-auto">{{ number_format($sidebarCounts['contests'] ?? 0) }}</span>
                </a>
                <a href="{{ route('admin.earnings.contest_prizes') }}" class="nav-link {{ request()->routeIs('admin.earnings.contest_prizes*') ? 'active' : 'text-white-50' }} d-flex align-items-center gap-3 px-3 py-2 rounded-3 small">
                    <i class="bi bi-trophy-fill"></i> <span>Contest Prizes</span>
                    <span class="nav-count ms-auto">{{ number_format($sidebarCounts['contest_prizes'] ?? 0) }}</span>
                </a>
                <a href="{{ route('admin.earnings.wallets') }}" class="nav-link {{ request()->routeIs('admin.earnings*') && ! request()->routeIs('admin.earnings.contest_prizes*') ? 'active' : 'text-white-50' }} d-flex align-items-center gap-3 px-3 py-2 rounded-3 small">
                    <i class="bi bi-wallet2"></i> <span>Earnings</span>
                    <span class="nav-count ms-auto">{{ number_format($sidebarCounts['earnings'] ?? 0) }}</span>
                </a>
                <a href="{{ route('admin.users.monetization_requests', ['status' => 'pending']) }}" class="nav-link {{ request()->routeIs('admin.users.monetization_requests') ? 'active' : 'text-white-50' }} d-flex align-items-center gap-3 px-3 py-2 rounded-3 small">
                    <i class="bi bi-cash-coin"></i> <span>Monetization</span>
                    <span class="nav-count ms-auto">{{ number_format($sidebarCounts['monetization_requests'] ?? 0) }}</span>
                </a>
            </div>

            <div class="mb-2">
                <div class="sidebar-section-title">System</div>
                <a href="{{ route('admin.settings.configs') }}" class="nav-link {{ request()->routeIs('admin.settings*') ? 'active' : 'text-white-50' }} d-flex align-items-center gap-3 px-3 py-2 rounded-3 small">
                    <i class="bi bi-gear"></i> <span>App Settings</span>
                    <span class="nav-count ms-auto">{{ number_format($sidebarCounts['settings'] ?? 0) }}</span>
                </a>
            </div>
        </nav>
    </div>
</aside>

<style>
    .admin-sidebar {
        background: var(--sb-sidebar-bg);
        border-right: 1px solid var(--sb-sidebar-border);
        box-shadow: inset -1px 0 0 rgba(255, 255, 255, 0.02);
    }

    @media (min-width: 768px) {
        .admin-sidebar.offcanvas-md {
            position: fixed !important;
            top: 0;
            left: 0;
            bottom: 0;
            width: var(--admin-sidebar-width) !important;
            max-width: var(--admin-sidebar-width) !important;
            height: 100vh !important;
            visibility: visible !important;
            transform: none !important;
            z-index: 40;
        }

        .admin-sidebar .offcanvas-body {
            height: 100%;
            overflow: hidden;
        }
    }

    @media (min-width: 1400px) {
        .admin-sidebar.offcanvas-md {
            width: var(--admin-sidebar-width-lg) !important;
            max-width: var(--admin-sidebar-width-lg) !important;
        }
    }

    .sidebar-brand {
        min-height: var(--admin-header-height);
        padding: 33px 20px;
        display: flex;
        align-items: center;
        gap: 1rem;
        border-bottom: 1px solid var(--sb-sidebar-border);
        flex-shrink: 0;
    }

    .sidebar-brand h5 {
        font-size: 1rem;
        font-weight: 600;
        color: #ff6b67;
        letter-spacing: -0.03em;
    }

    .sidebar-brand small {
        color: var(--sb-muted);
        font-size: 0.80rem;
    }

    .sidebar-brand-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(180deg, #ff5381 0%, #ff8c5d 100%);
        color: #fff;
        font-size: 1.30rem;
    }

    .sidebar-section-title {
        padding: 0 0.65rem;
        margin-bottom: 0.6rem;
        color: var(--sb-muted);
        font-size: 0.67rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.06em;
    }

    #sidebar .nav-link {
        color: color-mix(in srgb, var(--sb-text) 72%, transparent) !important;
        transition: all 0.22s ease;
        font-size: 0.75rem;
        border-radius: 16px !important;
    }

    #sidebar .nav-link i {
        font-size: 1rem;
    }

    #sidebar .nav-link span:not(.nav-count) {
        font-size: 0.85rem;
        font-weight: 600;
    }

    #sidebar .nav-link:hover {
        background-color: rgba(255, 255, 255, 0.04);
        color: #fff !important;
    }

    #sidebar .nav-link.active {
        background: linear-gradient(135deg, #ff537e 0%, #ff875d 100%);
        color: #fff !important;
        box-shadow: 0 12px 24px rgba(255, 92, 114, 0.16);
    }

    .nav-count {
        color: var(--sb-muted);
        font-weight: 600;
        font-size: 0.84rem;
    }

    #sidebar .nav-link.active .nav-count {
        color: rgba(255, 255, 255, 0.92);
    }
</style>
