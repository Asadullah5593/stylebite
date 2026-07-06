@php
    $sidebarCounts = $sidebarCounts ?? [
        'users' => 7,
        'social' => 2,
        'posts' => 4,
        'comments' => 2,
        'engagement' => 6,
        'media' => 2,
        'memories' => 3,
        'messaging' => 5,
        'notifications' => 3,
        'moderation' => 2,
        'activity' => 0,
        'contests' => 8,
        'earnings' => 3,
        'settings' => 0,
    ];
@endphp

<aside id="sidebar" class="admin-sidebar text-white h-100 flex-column d-none d-md-flex">
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

        <!-- Community Section -->
        <div class="mb-4">
            <div class="sidebar-section-title">Community</div>
            <a href="{{ route('admin.users.all_users') }}" class="nav-link {{ request()->routeIs('admin.users*') ? 'active' : 'text-white-50' }} d-flex align-items-center gap-3 px-3 py-2 rounded-3 small">
                <i class="bi bi-people"></i> <span>Users</span>
                <span class="nav-count ms-auto">{{ $sidebarCounts['users'] }}</span>
            </a>
            <a href="{{ route('admin.social.follows') }}" class="nav-link {{ request()->routeIs('admin.social*') ? 'active' : 'text-white-50' }} d-flex align-items-center gap-3 px-3 py-2 rounded-3 small">
                <i class="bi bi-diagram-3"></i> <span>Social Graph</span>
                <span class="nav-count ms-auto">{{ $sidebarCounts['social'] }}</span>
            </a>
        </div>

        <!-- Content Section -->
        <div class="mb-3">
            <div class="sidebar-section-title">Content</div>
            <a href="{{ route('admin.posts.all_posts') }}" class="nav-link {{ request()->routeIs('admin.posts*') ? 'active' : 'text-white-50' }} d-flex align-items-center gap-3 px-3 py-2 rounded-3 small">
                <i class="bi bi-file-earmark-text"></i> <span>Posts</span>
                <span class="nav-count ms-auto">{{ $sidebarCounts['posts'] }}</span>
            </a>
            <a href="{{ route('admin.comments.comments') }}" class="nav-link {{ request()->routeIs('admin.comments*') ? 'active' : 'text-white-50' }} d-flex align-items-center gap-3 px-3 py-2 rounded-3 small">
                <i class="bi bi-chat-left"></i> <span>Comments</span>
                <span class="nav-count ms-auto">{{ $sidebarCounts['comments'] }}</span>
            </a>
            <a href="{{ route('admin.engagement.post_likes') }}" class="nav-link {{ request()->routeIs('admin.engagement*') ? 'active' : 'text-white-50' }} d-flex align-items-center gap-3 px-3 py-2 rounded-3 small">
                <i class="bi bi-heart"></i> <span>Engagement</span>
                <span class="nav-count ms-auto">{{ $sidebarCounts['engagement'] }}</span>
            </a>
            <a href="{{ route('admin.media.tags') }}" class="nav-link {{ request()->routeIs('admin.media*') ? 'active' : 'text-white-50' }} d-flex align-items-center gap-3 px-3 py-2 rounded-3 small">
                <i class="bi bi-image"></i> <span>Media & Tags</span>
                <span class="nav-count ms-auto">{{ $sidebarCounts['media'] }}</span>
            </a>
            <a href="{{ route('admin.memories.memories') }}" class="nav-link {{ request()->routeIs('admin.memories*') ? 'active' : 'text-white-50' }} d-flex align-items-center gap-3 px-3 py-2 rounded-3 small">
                <i class="bi bi-bookmark-heart"></i> <span>Memories</span>
                <span class="nav-count ms-auto">{{ $sidebarCounts['memories'] }}</span>
            </a>
        </div>

        <!-- Communication -->
        <div class="mb-4">
            <div class="sidebar-section-title">Communication</div>
            <a href="{{ route('admin.messaging.messages') }}" class="nav-link {{ request()->routeIs('admin.messaging*') ? 'active' : 'text-white-50' }} d-flex align-items-center gap-3 px-3 py-2 rounded-3 small">
                <i class="bi bi-send"></i> <span>Messaging</span>
                <span class="nav-count ms-auto">{{ $sidebarCounts['messaging'] }}</span>
            </a>
            <a href="{{ route('admin.notifications.notifications') }}" class="nav-link {{ request()->routeIs('admin.notifications*') ? 'active' : 'text-white-50' }} d-flex align-items-center gap-3 px-3 py-2 rounded-3 small">
                <i class="bi bi-bell"></i> <span>Notifications</span>
                <span class="nav-count ms-auto">{{ $sidebarCounts['notifications'] }}</span>
            </a>
        </div>

        <!-- Trust & Safety -->
        <div class="mb-4">
            <div class="sidebar-section-title">Trust & Safety</div>
            <a href="{{ route('admin.moderation.reports') }}" class="nav-link {{ request()->routeIs('admin.moderation*') ? 'active' : 'text-white-50' }} d-flex align-items-center gap-3 px-3 py-2 rounded-3 small">
                <i class="bi bi-shield"></i> <span>Moderation</span>
                <span class="nav-count ms-auto">{{ $sidebarCounts['moderation'] }}</span>
            </a>
            <a href="{{ route('admin.activity.activity_logs') }}" class="nav-link {{ request()->routeIs('admin.activity*') ? 'active' : 'text-white-50' }} d-flex align-items-center gap-3 px-3 py-2 rounded-3 small">
                <i class="bi bi-activity"></i> <span>Activity Logs</span>
            </a>
        </div>

        <!-- Monetization -->
        <div class="mb-4">
            <div class="sidebar-section-title">Monetization</div>
            <a href="{{ route('admin.contests.contests') }}" class="nav-link {{ request()->routeIs('admin.contests*') ? 'active' : 'text-white-50' }} d-flex align-items-center gap-3 px-3 py-2 rounded-3 small">
                <i class="bi bi-trophy"></i> <span>Contests</span>
                <span class="nav-count ms-auto">{{ $sidebarCounts['contests'] }}</span>
            </a>
            <a href="{{ route('admin.earnings.transactions') }}" class="nav-link {{ request()->routeIs('admin.earnings*') ? 'active' : 'text-white-50' }} d-flex align-items-center gap-3 px-3 py-2 rounded-3 small">
                <i class="bi bi-wallet2"></i> <span>Earnings</span>
                <span class="nav-count ms-auto">{{ $sidebarCounts['earnings'] }}</span>
            </a>
        </div>

        <!-- System -->
        <div class="mb-2">
            <div class="sidebar-section-title">System</div>
            <a href="{{ route('admin.settings.configs') }}" class="nav-link {{ request()->routeIs('admin.settings*') ? 'active' : 'text-white-50' }} d-flex align-items-center gap-3 px-3 py-2 rounded-3 small">
                <i class="bi bi-gear"></i> <span>App Settings</span>
            </a>
        </div>
    </nav>
</aside>

<style>
    .admin-sidebar {
        width: 80%;
        min-height: 100vh;
        background: var(--sb-sidebar-bg);
        border-right: 1px solid var(--sb-sidebar-border);
        box-shadow: inset -1px 0 0 rgba(255, 255, 255, 0.02);
        overflow: hidden;
    }
    
    @media (min-width: 768px) {
        .admin-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--admin-sidebar-width);
            min-width: var(--admin-sidebar-width);
            max-width: var(--admin-sidebar-width);
            flex: 0 0 var(--admin-sidebar-width);
            height: 100vh;
            z-index: 40;
        }
    }

    @media (min-width: 1400px) {
        .admin-sidebar {
            width: var(--admin-sidebar-width-lg);
            min-width: var(--admin-sidebar-width-lg);
            max-width: var(--admin-sidebar-width-lg);
            flex-basis: var(--admin-sidebar-width-lg);
        }
    }

    .sidebar-brand {
        min-height: var(--admin-header-height);
        padding: 33px 20px;
        display: flex;
        align-items: center;
        gap: 1rem;
        border-bottom: 1px solid var(--sb-sidebar-border);
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
        /* min-height: 30px; */
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
