<header class="admin-header sticky-top">
    <div class="container-fluid px-2 px-md-2 px-xl-3 h-100">
        <div class="d-flex align-items-center gap-3 gap-lg-4 h-100">
            <button class="btn btn-link text-white-50 d-md-none p-0 me-1" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar">
                <i class="bi bi-list fs-3"></i>
            </button>

            <div class="header-search flex-grow-1" data-admin-header-search data-search-endpoint="{{ Route::has('admin.search') ? route('admin.search') : '' }}">
                <div class="header-search-shell">
                    <i class="bi bi-search"></i>
                    <input type="search" name="q" value="" placeholder="Search users, posts, memories, contests..." aria-label="Search" autocomplete="off" data-search-input />
                    <button class="header-search-clear" type="button" data-search-clear aria-label="Clear search">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>

                <div class="header-search-popover shadow-lg d-none" data-search-popover>
                    <div class="header-search-popover-head">
                        <div class="header-search-scopes" data-search-scopes>
                            @foreach ([
                            'all' => 'All',
                            'users' => 'Users',
                            'posts' => 'Posts',
                            'memories' => 'Memories',
                            'contests' => 'Contests',
                            'messages' => 'Messages',
                            ] as $scopeKey => $scopeLabel)
                            <button class="search-scope-chip {{ $scopeKey === 'all' ? 'active' : '' }}" type="button" data-search-scope="{{ $scopeKey }}">{{ $scopeLabel }}</button>
                            @endforeach
                        </div>
                        <div class="header-search-meta" data-search-meta>Type at least 2 characters</div>
                    </div>

                    <div class="header-search-results" data-search-results>
                        <div class="header-search-empty">
                            <i class="bi bi-command"></i>
                            <div>
                                <div class="header-search-empty-title">Search across admin modules</div>
                                <div class="header-search-empty-copy">Users, posts, memories, contests, and messages will appear here as you type.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2 gap-lg-3 ms-auto">
                <button class="header-icon-btn" type="button" data-theme-toggle title="Switch theme" aria-label="Switch theme">
                    <i class="bi bi-sun"></i>
                </button>

                <div class="dropdown">
                    <button class="header-icon-btn position-relative" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
                        <i class="bi bi-bell"></i>
                        @if (($headerNotificationCount ?? 0) > 0)
                        <span class="notification-dot"></span>
                        @endif
                    </button>
                    <div class="dropdown-menu dropdown-menu-end shadow border-0 mt-3 header-dropdown notifications-dropdown">
                        <div class="dropdown-head">Notifications</div>
                        <div class="dropdown-divider"></div>

                        @forelse (($headerNotifications ?? collect()) as $notificationItem)
                        <a class="notification-item text-decoration-none" href="{{ $notificationItem['route'] }}">
                            <span class="notification-bullet {{ ($notificationItem['kind'] ?? 'ops') === 'personal' ? 'personal' : '' }}"></span>
                            <span class="notification-copy">
                                <span class="notification-title">{{ $notificationItem['title'] }}</span>
                                <span class="notification-time">{{ $notificationItem['time'] }}</span>
                            </span>
                        </a>
                        @empty
                        <div class="px-3 py-3 text-muted small">No alerts right now.</div>
                        @endforelse
                        <div class="dropdown-divider"></div>
                        <a class="notification-item text-decoration-none" href="{{ Route::has('admin.notifications.notifications') ? route('admin.notifications.notifications') : '#' }}">
                            <span class="notification-bullet personal"></span>
                            <span class="notification-copy">
                                <span class="notification-title">Open notification center</span>
                                <span class="notification-time">View all in-app delivery records</span>
                            </span>
                        </a>
                    </div>
                </div>

                <div class="dropdown">
                    <button class="header-profile profile-trigger d-flex align-items-center gap-3" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <img
                            src="{{ Auth::user()->avatar_url ? asset(Auth::user()->avatar_url) : 'https://i.pravatar.cc/80?img=5' }}"
                            alt="{{ Auth::user()->full_name ?? Auth::user()->username ?? 'Admin' }}"
                            class="header-avatar">
                        <div class="d-none d-sm-block text-start">
                            <div class="header-name">{{ Auth::user()->full_name ?? Auth::user()->username ?? 'Admin' }}</div>
                            <div class="header-role">Admin</div>
                        </div>
                    </button>

                    <div class="dropdown-menu dropdown-menu-end shadow border-0 mt-3 profile-dropdown-menu">
                        <div class="profile-card-top">
                            <div class="profile-name">{{ Auth::user()->full_name ?? Auth::user()->username ?? 'Admin' }}</div>
                            <div class="profile-email">{{ Auth::user()->email ?? '' }}</div>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a class="profile-menu-link" href="{{ Route::has('admin.account.profile') ? route('admin.account.profile') : '#' }}">
                            <i class="bi bi-person"></i>
                            <span>My profile</span>
                        </a>
                        <a class="profile-menu-link" href="{{ Route::has('admin.account.settings') ? route('admin.account.settings') : '#' }}">
                            <i class="bi bi-sliders"></i>
                            <span>Settings</span>
                        </a>
                        <a class="profile-menu-link" href="{{ Route::has('admin.settings.configs') ? route('admin.settings.configs') : (Route::has('admin.settings') ? route('admin.settings') : '#') }}">
                            <i class="bi bi-gear"></i>
                            <span>App settings</span>
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="profile-menu-link logout-link" href="{{ Route::has('admin.logout') ? route('admin.logout') : '#' }}"
                            onclick="event.preventDefault(); if(document.getElementById('logout-form')) document.getElementById('logout-form').submit();">
                            <i class="bi bi-box-arrow-right"></i>
                            <span>Logout</span>
                        </a>
                        <form id="logout-form" action="{{ Route::has('admin.logout') ? route('admin.logout') : '#' }}" method="POST" class="d-none">
                            @csrf
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<style>
    .admin-header {
        background: var(--sb-header-bg);
        border-bottom: 1px solid var(--sb-border);
        backdrop-filter: blur(16px);
        z-index: 30;
        height: var(--admin-header-height);
    }

    .header-search {
        position: relative;
        max-width: 540px;
        min-width: 280px;
    }

    .header-search-shell {
        position: relative;
    }

    .header-search-shell>i {
        position: absolute;
        left: 1.15rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--sb-muted);
        font-size: 1rem;
    }

    .header-search input {
        width: 100%;
        height: 45px;
        border-radius: 15px;
        border: 1px solid var(--sb-border);
        background: var(--sb-header-input-bg);
        color: var(--sb-text);
        padding: 0 1rem 0 3rem;
        font-size: 0.98rem;
        outline: none;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.02);
    }

    .header-search input::placeholder {
        color: var(--sb-muted);
    }

    .header-search-clear {
        position: absolute;
        top: 50%;
        right: 0.8rem;
        transform: translateY(-50%);
        width: 32px;
        height: 32px;
        border: 0;
        border-radius: 10px;
        background: transparent;
        color: var(--sb-muted);
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .header-search-clear:hover {
        background: rgba(255, 255, 255, 0.05);
        color: var(--sb-text);
    }

    .header-search-popover {
        position: absolute;
        top: calc(100% + 0.75rem);
        left: 0;
        right: 0;
        background: var(--sb-dropdown-bg);
        border: 1px solid var(--sb-border);
        border-radius: 20px;
        overflow: hidden;
        z-index: 60;
    }

    .header-search-popover-head {
        padding: 1rem 1rem 0.75rem;
        border-bottom: 1px solid var(--sb-border);
        background: rgba(255, 255, 255, 0.02);
    }

    .header-search-scopes {
        display: flex;
        gap: 0.45rem;
        flex-wrap: wrap;
    }

    .search-scope-chip {
        border: 1px solid var(--sb-border);
        background: rgba(255, 255, 255, 0.03);
        color: var(--sb-muted);
        border-radius: 999px;
        padding: 0.35rem 0.8rem;
        font-size: 0.78rem;
        font-weight: 700;
    }

    .search-scope-chip.active,
    .search-scope-chip:hover {
        background: rgba(255, 85, 122, 0.12);
        border-color: rgba(255, 85, 122, 0.2);
        color: var(--sb-text);
    }

    .header-search-meta {
        margin-top: 0.7rem;
        color: var(--sb-muted);
        font-size: 0.8rem;
        font-weight: 600;
    }

    .header-search-results {
        max-height: 440px;
        overflow-y: auto;
        padding: 0.5rem;
    }

    .header-search-group+.header-search-group {
        margin-top: 0.25rem;
    }

    .header-search-group-label {
        display: flex;
        align-items: center;
        gap: 0.55rem;
        color: var(--sb-muted);
        font-size: 0.78rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        padding: 0.75rem 0.75rem 0.35rem;
    }

    .header-search-item {
        display: flex;
        align-items: flex-start;
        gap: 0.85rem;
        padding: 0.8rem 0.85rem;
        border-radius: 14px;
        color: var(--sb-dropdown-item);
        text-decoration: none;
        transition: background 0.18s ease;
    }

    .header-search-item:hover,
    .header-search-item.active {
        background: rgba(255, 255, 255, 0.05);
    }

    .header-search-item-icon {
        width: 2.2rem;
        height: 2.2rem;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(255, 85, 122, 0.12);
        color: #ff6a8b;
        flex-shrink: 0;
    }

    .header-search-item-copy {
        min-width: 0;
        flex: 1;
    }

    .header-search-item-title {
        color: var(--sb-dropdown-item);
        font-size: 0.9rem;
        font-weight: 700;
        line-height: 1.35;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .header-search-item-subtitle {
        color: var(--sb-muted);
        font-size: 0.8rem;
        margin-top: 0.15rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .header-search-empty {
        display: flex;
        align-items: flex-start;
        gap: 0.9rem;
        padding: 1rem;
        color: var(--sb-muted);
    }

    .header-search-empty i {
        font-size: 1.1rem;
        line-height: 1;
        margin-top: 0.1rem;
    }

    .header-search-empty-title {
        color: var(--sb-dropdown-item);
        font-size: 0.92rem;
        font-weight: 700;
    }

    .header-search-empty-copy {
        font-size: 0.82rem;
        margin-top: 0.2rem;
    }

    .header-icon-btn {
        width: 40px;
        height: 40px;
        border: 0;
        border-radius: 14px;
        background: transparent;
        color: var(--sb-header-icon);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        position: relative;
        transition: background 0.2s ease, color 0.2s ease;
    }

    .header-icon-btn:hover,
    .profile-trigger:hover {
        background: rgba(255, 255, 255, 0.05);
    }

    .header-icon-btn i {
        font-size: 1.3rem;
    }

    .notification-dot {
        position: absolute;
        top: 0.22rem;
        right: 0.3rem;
        width: 0.7rem;
        height: 0.7rem;
        border-radius: 50%;
        background: #ff5b80;
        box-shadow: 0 0 0 3px var(--sb-notification-shadow);
    }

    .notification-bullet.personal {
        background: #6ca8ff;
    }

    .header-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid rgba(255, 85, 122, 0.35);
        flex-shrink: 0;
    }

    .profile-trigger {
        padding: 0;
        border: 0;
        background: transparent;
        color: var(--sb-text);
        border-radius: 16px;
    }

    .header-name {
        font-size: 0.98rem;
        font-weight: 800;
        line-height: 1.1;
        color: var(--sb-text);
    }

    .header-role {
        color: var(--sb-muted);
        font-size: 0.9rem;
        line-height: 1.1;
    }

    .header-dropdown,
    .profile-dropdown-menu {
        background: var(--sb-dropdown-bg);
        border-radius: 18px;
        border: 1px solid var(--sb-border);
        min-width: 230px;
        overflow: hidden;
        padding: 0;
    }

    .notifications-dropdown {
        min-width: 300px;
    }

    .dropdown-head,
    .profile-card-top {
        padding: 0.5rem 1.1rem;
    }

    .dropdown-head {
        font-size: 0.95rem;
        font-weight: 800;
        color: var(--sb-dropdown-item);
    }

    .header-dropdown .dropdown-divider,
    .profile-dropdown-menu .dropdown-divider {
        margin: 0;
        border-color: var(--sb-border);
    }

    .notification-item {
        display: flex;
        align-items: flex-start;
        gap: 1rem;
        padding: 0.5rem 1.1rem;
        color: var(--sb-dropdown-item);
    }

    .notification-item:hover,
    .profile-menu-link:hover {
        background: rgba(255, 255, 255, 0.05);
    }

    .notification-bullet {
        width: 0.8rem;
        height: 0.8rem;
        border-radius: 50%;
        background: #ff5b80;
        margin-top: 0.45rem;
        flex-shrink: 0;
    }

    .notification-copy {
        display: flex;
        flex-direction: column;
        gap: 0.15rem;
    }

    .notification-title {
        color: var(--sb-dropdown-item);
        font-size: 0.92rem;
        font-weight: 700;
        line-height: 1.35;
    }

    .notification-time {
        color: var(--sb-muted);
        font-size: 0.82rem;
    }

    .profile-name {
        color: var(--sb-dropdown-item);
        font-size: 0.95rem;
        font-weight: 800;
        line-height: 1.2;
    }

    .profile-email {
        color: var(--sb-muted);
        font-size: 0.84rem;
        font-weight: 700;
        margin-top: 0.25rem;
    }

    .profile-menu-link {
        display: flex;
        align-items: center;
        gap: 0.85rem;
        padding: 0.95rem 1.1rem;
        color: var(--sb-dropdown-item);
        text-decoration: none;
        font-size: 0.95rem;
        font-weight: 700;
    }

    .profile-menu-link i {
        font-size: 1rem;
    }

    .logout-link {
        color: #ef4c4b;
    }

    @media (max-width: 767.98px) {
        .admin-header {
            height: 76px;
        }

        .header-search input {
            height: 46px;
            font-size: 0.94rem;
        }

        .notifications-dropdown {
            min-width: 320px;
        }

        .header-search-popover {
            left: -0.25rem;
            right: -0.25rem;
        }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchRoot = document.querySelector('[data-admin-header-search]');
        if (!searchRoot) {
            return;
        }

        const endpoint = searchRoot.dataset.searchEndpoint;
        const input = searchRoot.querySelector('[data-search-input]');
        const clearButton = searchRoot.querySelector('[data-search-clear]');
        const popover = searchRoot.querySelector('[data-search-popover]');
        const results = searchRoot.querySelector('[data-search-results]');
        const meta = searchRoot.querySelector('[data-search-meta]');
        const scopeButtons = Array.from(searchRoot.querySelectorAll('[data-search-scope]'));

        let activeScope = 'all';
        let debounceTimer = null;
        let activeIndex = -1;
        let currentAbortController = null;

        const emptyState = (title, copy, icon = 'bi-search') => `
        <div class="header-search-empty">
            <i class="bi ${icon}"></i>
            <div>
                <div class="header-search-empty-title">${title}</div>
                <div class="header-search-empty-copy">${copy}</div>
            </div>
        </div>
    `;

        const showPopover = () => popover.classList.remove('d-none');
        const hidePopover = () => {
            popover.classList.add('d-none');
            activeIndex = -1;
            setActiveItem();
        };

        const setActiveScope = (scope) => {
            activeScope = scope;
            scopeButtons.forEach((button) => {
                button.classList.toggle('active', button.dataset.searchScope === scope);
            });
        };

        const getItems = () => Array.from(results.querySelectorAll('.header-search-item'));

        const setActiveItem = () => {
            getItems().forEach((item, index) => {
                item.classList.toggle('active', index === activeIndex);
            });
        };

        const renderGroups = (payload) => {
            if (!payload.groups.length) {
                results.innerHTML = emptyState('No matching results', 'Try another keyword or switch the search scope.', 'bi-search-heart');
                meta.textContent = payload.summary.message || 'No matching results';
                return;
            }

            results.innerHTML = payload.groups.map((group) => `
            <div class="header-search-group">
                <div class="header-search-group-label">
                    <i class="bi ${group.icon}"></i>
                    <span>${group.label}</span>
                </div>
                ${group.items.map((item) => `
                    <a href="${item.url}" class="header-search-item">
                        <span class="header-search-item-icon"><i class="bi ${item.icon}"></i></span>
                        <span class="header-search-item-copy">
                            <span class="header-search-item-title">${item.title}</span>
                            <span class="header-search-item-subtitle">${item.subtitle || ''}</span>
                        </span>
                    </a>
                `).join('')}
            </div>
        `).join('');

            meta.textContent = `${payload.summary.total_results} result${payload.summary.total_results === 1 ? '' : 's'} in ${activeScope === 'all' ? 'all modules' : activeScope}.`;
            activeIndex = -1;
            setActiveItem();
        };

        const runSearch = () => {
            const query = input.value.trim();

            if (currentAbortController) {
                currentAbortController.abort();
            }

            showPopover();

            if (query.length < 2) {
                meta.textContent = 'Type at least 2 characters';
                results.innerHTML = emptyState('Search across admin modules', 'Users, posts, memories, contests, and messages will appear here as you type.', 'bi-command');
                return;
            }

            meta.textContent = 'Searching...';
            results.innerHTML = emptyState('Searching', 'Pulling live admin results for your query.', 'bi-arrow-repeat');

            currentAbortController = new AbortController();
            const url = `${endpoint}?q=${encodeURIComponent(query)}&scope=${encodeURIComponent(activeScope)}`;

            fetch(url, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    signal: currentAbortController.signal,
                })
                .then((response) => response.json())
                .then((payload) => {
                    renderGroups(payload);
                })
                .catch((error) => {
                    if (error.name === 'AbortError') {
                        return;
                    }

                    meta.textContent = 'Search unavailable';
                    results.innerHTML = emptyState('Search unavailable', 'The live search request could not be completed right now.', 'bi-exclamation-circle');
                });
        };

        input.addEventListener('focus', function() {
            showPopover();
            if (input.value.trim().length < 2) {
                results.innerHTML = emptyState('Search across admin modules', 'Users, posts, memories, contests, and messages will appear here as you type.', 'bi-command');
                meta.textContent = 'Type at least 2 characters';
            }
        });

        input.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(runSearch, 220);
        });

        input.addEventListener('keydown', function(event) {
            const items = getItems();

            if (event.key === 'ArrowDown' && items.length) {
                event.preventDefault();
                activeIndex = (activeIndex + 1) % items.length;
                setActiveItem();
                return;
            }

            if (event.key === 'ArrowUp' && items.length) {
                event.preventDefault();
                activeIndex = activeIndex <= 0 ? items.length - 1 : activeIndex - 1;
                setActiveItem();
                return;
            }

            if (event.key === 'Enter' && activeIndex >= 0 && items[activeIndex]) {
                event.preventDefault();
                window.location.href = items[activeIndex].getAttribute('href');
                return;
            }

            if (event.key === 'Escape') {
                hidePopover();
                input.blur();
            }
        });

        clearButton.addEventListener('click', function() {
            input.value = '';
            input.focus();
            meta.textContent = 'Type at least 2 characters';
            results.innerHTML = emptyState('Search across admin modules', 'Users, posts, memories, contests, and messages will appear here as you type.', 'bi-command');
        });

        scopeButtons.forEach((button) => {
            button.addEventListener('click', function() {
                setActiveScope(button.dataset.searchScope);
                runSearch();
            });
        });

        document.addEventListener('click', function(event) {
            if (!searchRoot.contains(event.target)) {
                hidePopover();
            }
        });

        setActiveScope('all');
    });
</script>