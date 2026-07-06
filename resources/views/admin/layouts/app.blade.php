<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - StyleBite</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            /* Map Bootstrap defaults to Theme colors */
            --bs-primary: #ff557a;
            --bs-primary-rgb: 255, 85, 122;

            --sb-bg: #151219;
            --sb-panel: #1f1a24;
            --sb-panel-2: #241d29;
            --sb-border: rgba(255, 255, 255, 0.08);
            --sb-text: #f5eff3;
            --sb-muted: #a69cab;
            --sb-shell-bg:
                radial-gradient(circle at top left, rgba(110, 22, 48, 0.55) 0%, rgba(21, 18, 25, 0) 34%),
                radial-gradient(circle at bottom right, rgba(117, 56, 26, 0.32) 0%, rgba(21, 18, 25, 0) 32%),
                linear-gradient(180deg, #17131b 0%, #150f16 100%);
            --sb-content-bg:
                radial-gradient(circle at 20% 10%, rgba(255, 85, 122, 0.10) 0%, transparent 30%),
                radial-gradient(circle at 85% 65%, rgba(255, 138, 87, 0.10) 0%, transparent 28%),
                linear-gradient(180deg, #1a141d 0%, #150f17 100%);
            --sb-header-bg: rgba(31, 26, 36, 0.96);
            --sb-sidebar-bg: linear-gradient(180deg, #121019 0%, #111018 100%);
            --sb-sidebar-border: rgba(255, 255, 255, 0.07);
            --sb-header-input-bg: rgba(24, 19, 27, 0.95);
            --sb-header-icon: #f0e7ec;
            --sb-glass-bg: rgba(35, 28, 38, 0.88);
            --sb-dropdown-bg: #16151d;
            --sb-dropdown-item: #f3edf1;
            --sb-notification-shadow: rgba(31, 26, 36, 0.95);
            --sb-pink: #ff557a;
            --sb-orange: #ff8a57;
            --sb-yellow: #f2a81d;
            --sb-blue: #5c94ff;
            --sb-green: #32d36b;
            --admin-sidebar-width: 255px;
            --admin-sidebar-width-lg: 270px;
            
            /* Custom variables for BlocksPage.blade.php and similar components */
            --sb-bg-input-soft: rgba(0,0,0,0.2);
            --sb-bg-transparent-05: rgba(255,255,255,0.05);
            --sb-border-transparent-05: rgba(255,255,255,0.05);
            --sb-border-transparent-10: rgba(255,255,255,0.1);
            --sb-border-primary-soft-alpha: rgba(255, 85, 122, 0.2);
            --sb-bg-primary-soft-alpha-opaque: rgba(255, 85, 122, 0.05);
            
            --sb-form-focus-bg: rgba(0,0,0,0.3);
            --sb-form-focus-color: white;
            --sb-form-focus-shadow: 0 0 0 2px rgba(255, 85, 122, 0.2);
            --sb-form-focus-border: rgba(255, 85, 122, 0.3);

            --admin-header-height: 65px;
            --admin-search-height: 35px;
            --admin-shell-gap: 25px;
            --admin-content-max: 1460px;
            --admin-card-radius: 24px;
        }
        
        /* Global Component Overrides */
        .badge {
            font-weight: 600;
            padding: 0.4em 0.8em;
            border-radius: 6px;
        }
        /* Replace Blue with Sidebar Pink */
        .badge.bg-info-subtle { background: rgba(255, 85, 122, 0.15) !important; color: var(--sb-pink) !important; border: 1px solid rgba(255, 85, 122, 0.2); }
        .badge.bg-success-subtle { background: rgba(50, 211, 107, 0.15) !important; color: var(--sb-green) !important; border: 1px solid rgba(50, 211, 107, 0.2); }
        .badge.bg-warning-subtle { background: rgba(242, 168, 29, 0.15) !important; color: var(--sb-yellow) !important; border: 1px solid rgba(242, 168, 29, 0.2); }
        .text-primary { color: var(--sb-pink) !important; }
        
        /* Primary Action Gradient (Buttons & Pagination) */
        .btn-primary {
            background: linear-gradient(135deg, #ff537e 0%, #ff875d 100%) !important;
            border: none !important;
            color: white !important;
            box-shadow: 0 4px 12px rgba(255, 85, 122, 0.25);
        }
        .btn-primary:hover {
            filter: brightness(1.08);
        }

        /* Modal Theming */
        .modal-content { background: var(--sb-panel) !important; color: var(--sb-text) !important; border: 1px solid var(--sb-border) !important; }
        .modal-header { border-bottom: 1px solid var(--sb-border) !important; }
        .btn-close { filter: var(--sb-header-icon-filter, invert(1)); }

        body {
            background: var(--sb-shell-bg);
            color: var(--sb-text);
            font-family: 'Manrope', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            transition: background 0.25s ease, color 0.25s ease;
        }

        .bg-primary-gradient {
            background: linear-gradient(135deg, var(--sb-pink) 0%, var(--sb-orange) 100%) !important;
        }

        .hover-bg-white-10:hover { background-color: rgba(255, 255, 255, 0.06); }

        .main-wrapper {
            display: block;
            min-height: 100vh;
        }

        .content-area {
            min-width: 0;
            min-height: 100vh;
            background: var(--sb-content-bg);
        }

        @media (min-width: 768px) {
            .content-area {
                margin-left: var(--admin-sidebar-width);
            }
        }

        @media (min-width: 1400px) {
            .content-area {
                margin-left: var(--admin-sidebar-width-lg);
            }
        }

        .glass {
            background: var(--sb-glass-bg);
            backdrop-filter: blur(18px);
            border: 1px solid var(--sb-border);
            overflow: hidden;
        }

        .bg-gradient-primary-soft {
            background: linear-gradient(135deg, rgba(255, 85, 122, 0.14) 0%, rgba(255, 138, 87, 0.14) 100%);
        }

        .hover-shadow-elev-md {
            transition: all 0.3s ease;
        }

        .hover-shadow-elev-md:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }

        .scrollbar-hidden::-webkit-scrollbar { display: none; }
        .scrollbar-hidden { -ms-overflow-style: none; scrollbar-width: none; }
        .text-muted { color: var(--sb-muted) !important; }
        main.container-fluid { max-width: none !important; }

        .admin-main {
            max-width: var(--admin-content-max) !important;
            margin: 0 auto;
            padding-top: var(--admin-shell-gap) !important;
            padding-bottom: var(--admin-shell-gap) !important;
            width: 100%;
        }

        body.theme-light {
            --sb-bg: #f8f4f2;
            --sb-panel: #fff9f7;
            --sb-panel-2: #fff3ef;
            --sb-border: rgba(86, 56, 66, 0.10);
            --sb-text: #26212c;
            --sb-muted: #6f6a78;
            --sb-shell-bg:
                radial-gradient(circle at 8% 18%, rgba(255, 146, 173, 0.20) 0%, rgba(255, 246, 243, 0) 32%),
                radial-gradient(circle at 88% 22%, rgba(255, 196, 158, 0.20) 0%, rgba(255, 246, 243, 0) 34%),
                linear-gradient(180deg, #fff8f6 0%, #fdeee7 100%);
            --sb-content-bg:
                radial-gradient(circle at 18% 16%, rgba(255, 120, 158, 0.10) 0%, transparent 25%),
                radial-gradient(circle at 86% 18%, rgba(255, 180, 122, 0.12) 0%, transparent 28%),
                linear-gradient(180deg, #fff9f6 0%, #fef1eb 100%);
            --sb-header-bg: rgba(255, 249, 246, 0.94);
            --sb-sidebar-bg: linear-gradient(180deg, #fffdfc 0%, #fff8f5 100%);
            --sb-sidebar-border: rgba(95, 72, 81, 0.10);
            --sb-header-input-bg: rgba(255, 252, 250, 0.98);
            --sb-header-icon: #2d2631;
            --sb-glass-bg: rgba(255, 248, 246, 0.88);
            --sb-dropdown-bg: #fff9f6;
            --sb-dropdown-item: #26212c;
            --sb-notification-shadow: rgba(255, 249, 246, 0.98);

            /* Light theme overrides for custom variables */
            --sb-bg-input-soft: rgba(0,0,0,0.03);
            --sb-bg-transparent-05: rgba(0,0,0,0.02);
            --sb-border-transparent-05: rgba(0,0,0,0.05);
            --sb-border-transparent-10: rgba(0,0,0,0.1);
            --sb-border-primary-soft-alpha: rgba(255, 85, 122, 0.1);
            --sb-bg-primary-soft-alpha-opaque: rgba(255, 85, 122, 0.03);

            --sb-form-focus-bg: rgba(255,255,255,0.9);
            --sb-form-focus-color: black;
            --sb-form-focus-shadow: 0 0 0 2px rgba(255, 85, 122, 0.1); /* Adjusted for light theme */
            --sb-form-focus-border: rgba(255, 85, 122, 0.15); /* Adjusted for light theme */
            
            --sb-header-icon-filter: none;
            --bs-pagination-bg: #fffdfc;
            --bs-pagination-color: #6f6a78;
            --bs-pagination-border-color: rgba(95, 72, 81, 0.10);
            --bs-pagination-hover-bg: rgba(255, 120, 158, 0.08);
            --bs-pagination-active-bg: var(--sb-pink);
            --bs-pagination-disabled-bg: #fffdfc;
        }

        body.theme-light .users-page,
        body.theme-light .media-page,
        body.theme-light .posts-page,
        body.theme-light .memories-page,
        body.theme-light .messaging-page,
        body.theme-light .notifications-page,
        body.theme-light .admin-page,
        body.theme-light .create-reset-page,
        body.theme-light .password-reset-show-page,
        body.theme-light .admin-dashboard {
            color: var(--sb-text);
        }

        body.theme-light .users-toolbar,
        body.theme-light .users-table-card,
        body.theme-light .memories-toolbar,
        body.theme-light .memories-table-card,
        body.theme-light .messaging-toolbar,
        body.theme-light .messaging-table-card,
        body.theme-light .notifications-toolbar,
        body.theme-light .notifications-table-card,
        body.theme-light .media-toolbar,
        body.theme-light .media-table-card,
        body.theme-light .create-card,
        body.theme-light .detail-card,
        body.theme-light .list-shell,
        body.theme-light .chart-shell,
        body.theme-light .metric-card,
        body.theme-light .action-card,
        body.theme-light .page-shell,
        body.theme-light .inner-card {
            background: rgba(255, 248, 246, 0.88) !important;
            border-color: rgba(92, 62, 73, 0.10) !important;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.55), 0 18px 42px rgba(231, 184, 190, 0.12);
        }

        body.theme-light .admin-dashboard .glass {
            background: rgba(255, 248, 246, 0.88) !important;
            border-color: rgba(92, 62, 73, 0.10) !important;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.55), 0 18px 42px rgba(231, 184, 190, 0.12) !important;
        }

        body.theme-light .users-tabs,
        body.theme-light .media-tabs,
        body.theme-light .memories-tabs,
        body.theme-light .messaging-tabs,
        body.theme-light .notifications-tabs,
        body.theme-light .header-search input,
        body.theme-light .toolbar-search .form-control,
        body.theme-light .toolbar-select,
        body.theme-light .media-toolbar .form-control,
        body.theme-light .media-toolbar .input-group-text,
        body.theme-light .toolbar-btn,
        body.theme-light .dark-control {
            background: rgba(255, 252, 249, 0.96) !important;
            color: #302a34 !important;
            border-color: rgba(95, 72, 81, 0.10) !important;
        }

        body.theme-light .users-table th,
        body.theme-light .memories-table th,
        body.theme-light .messaging-table th,
        body.theme-light .messaging-table td,
        body.theme-light .notifications-table th,
        body.theme-light .notifications-table td,
        body.theme-light .memories-table td,
        body.theme-light .users-table td,
        body.theme-light .users-title,
        body.theme-light .media-page .fw-medium,
        body.theme-light .show-title,
        body.theme-light .create-title,
        body.theme-light .dashboard-title,
        body.theme-light .panel-title,
        body.theme-light .recent-title,
        body.theme-light .memories-title,
        body.theme-light .messaging-title,
        body.theme-light .notifications-title,
        body.theme-light .message-bubble.received,
        body.theme-light .notification-item .title,
        body.theme-light .memories-breadcrumb-active,
        body.theme-light .detail-value,
        body.theme-light .header-name {
            color: #26212c !important;
        }

        body.theme-light .users-subtitle,
        body.theme-light .show-label,
        body.theme-light .detail-title,
        body.theme-light .media-breadcrumb,
        body.theme-light .header-role,
        body.theme-light .dashboard-subtitle,
        body.theme-light .panel-subtitle,
        body.theme-light .memories-page .text-muted,
        body.theme-light .messaging-page .text-muted,
        body.theme-light .notifications-page .text-muted,
        body.theme-light .notification-item .time,
        body.theme-light .conversation-item .last-message,
        body.theme-light .users-breadcrumb,
        body.theme-light .page-subtitle {
            color: #6f6a78 !important;
        }

        body.theme-light .table-dropdown-menu,
        body.theme-light .toolbar-dropdown-menu,
        body.theme-light .header-dropdown,
        body.theme-light .profile-dropdown-menu {
            background: var(--sb-dropdown-bg) !important;
            border-color: rgba(92, 62, 73, 0.10) !important;
            box-shadow: 0 18px 40px rgba(231, 184, 190, 0.16) !important;
        }

        body.theme-light .users-page .users-tabs,
        body.theme-light .memories-page .memories-tabs,
        body.theme-light .messaging-page .messaging-tabs,
        body.theme-light .notifications-page .notifications-tabs,
        body.theme-light .media-page .media-tabs {
            background: rgba(255, 245, 242, 0.95) !important;
            border: 1px solid rgba(92, 62, 73, 0.08);
        }

        body.theme-light .users-page .users-tab:not(.active),
        body.theme-light .media-page .nav-link:not(.active),
        body.theme-light .memories-page .tab-item:not(.active),
        body.theme-light .messaging-page .tab-item:not(.active),
        body.theme-light .notifications-page .tab-item:not(.active),
        body.theme-light .users-page .table-action-link,
        body.theme-light .users-page .table-dots-btn,
        body.theme-light .users-page .table-footer,
        body.theme-light .users-page .page-nav,
        body.theme-light .users-page .page-chip:not(.active),
        body.theme-light .admin-dashboard .panel-link,
        body.theme-light .admin-dashboard .chart-link,
        body.theme-light .admin-dashboard .dashboard-date,
        body.theme-light .admin-dashboard .dashboard-breadcrumb,
        body.theme-light .admin-dashboard .breadcrumb-home {
            color: #6f6a78 !important;
        }

        body.theme-light .users-page .users-table tbody tr:hover,
        body.theme-light .memories-page .memories-table tbody tr:hover,
        body.theme-light .messaging-page .messaging-table tbody tr:hover,
        body.theme-light .messaging-page .conversation-item:hover,
        body.theme-light .notifications-page .notification-item:hover,
        body.theme-light .admin-dashboard .bottom-list-row:hover {
            background: rgba(255, 120, 158, 0.05) !important;
        }

        body.theme-light .users-page .users-tab:hover,
        body.theme-light .users-page .toolbar-btn:hover,
        body.theme-light .users-page .toolbar-select:hover,
        body.theme-light .users-page .page-nav:hover,
        body.theme-light .users-page .table-dots-btn:hover,
        body.theme-light .users-page .table-action-link:hover,
        body.theme-light .header-icon-btn:hover,
        body.theme-light .profile-trigger:hover {
            background: rgba(255, 120, 158, 0.08) !important;
            color: #26212c !important;
            border-color: rgba(255, 120, 158, 0.18) !important;
        }

        body.theme-light .users-page .table-tag {
            background: rgba(255, 113, 151, 0.12) !important;
            color: #cf4d76 !important;
        }

        body.theme-light .users-page .table-link {
            color: #e15d7c !important;
        }

        body.theme-light .users-page .table-pill.pill-user,
        body.theme-light .memories-page .badge,
        body.theme-light .messaging-page .badge,
        body.theme-light .notifications-page .badge,
        body.theme-light .users-page .table-pill.pill-apple,
        body.theme-light .users-page .table-pill.pill-muted,
        body.theme-light .admin-dashboard .pill-status.role.user,
        body.theme-light .admin-dashboard .pill-status.role.creator,
        body.theme-light .admin-dashboard .pill-status.report-status.rejected,
        body.theme-light .admin-dashboard .pill-status.report-status.under-review {
            background: rgba(108, 105, 116, 0.10) !important;
            color: #716a78 !important;
            border-color: rgba(108, 105, 116, 0.12) !important;
        }

        body.theme-light .users-page .bool-off {
            background: rgba(108, 105, 116, 0.10) !important;
            color: #716a78 !important;
        }

        body.theme-light .users-page .sort-arrow,
        body.theme-light .admin-dashboard .chart-link {
            color: #ff607f !important;
        }

        body.theme-light .admin-dashboard .action-icon.high {
            background: rgba(255, 115, 148, 0.12) !important;
            border-color: rgba(255, 115, 148, 0.20) !important;
            color: #ef557f !important;
        }

        body.theme-light .admin-dashboard .action-icon.med,
        body.theme-light .admin-dashboard .stat-icon.warning {
            background: rgba(242, 168, 29, 0.12) !important;
            border-color: rgba(242, 168, 29, 0.20) !important;
            color: #d48a00 !important;
        }

        body.theme-light .admin-dashboard .action-icon.low,
        body.theme-light .admin-dashboard .stat-icon.info,
        body.theme-light .admin-dashboard .pill-status.role.moderator {
            background: rgba(255, 85, 122, 0.12) !important;
            border-color: rgba(255, 85, 122, 0.20) !important;
            color: var(--sb-pink) !important;
        }

        body.theme-light .admin-dashboard .stat-icon.primary,
        body.theme-light .admin-dashboard .timeline-icon {
            background: rgba(255, 93, 130, 0.12) !important;
            color: #ef557f !important;
        }

        body.theme-light .admin-dashboard .stat-icon.success,
        body.theme-light .admin-dashboard .pill-status.report-status.resolved {
            background: rgba(50, 211, 107, 0.12) !important;
            color: #29b65e !important;
            border-color: rgba(50, 211, 107, 0.20) !important;
        }

        body.theme-light .admin-dashboard .pill-status.report-status.open {
            background: rgba(242, 168, 29, 0.12) !important;
            color: #d48a00 !important;
            border-color: rgba(242, 168, 29, 0.20) !important;
        }

        body.theme-light .admin-dashboard .pill-status.role.admin,
        body.theme-light .users-page .table-pill.pill-admin {
            background: rgba(255, 115, 148, 0.12) !important;
            color: #ef557f !important;
            border-color: rgba(255, 115, 148, 0.18) !important;
        }

        body.theme-light .admin-dashboard .report-icon {
            background: rgba(242, 168, 29, 0.12) !important;
            color: #d48a00 !important;
        }

        body.theme-light .admin-dashboard .activity-list::-webkit-scrollbar-thumb {
            background: rgba(108, 105, 116, 0.20);
        }

        body.theme-light .admin-dashboard .text-white,
        body.theme-light .admin-dashboard .dashboard-breadcrumb .fw-bold,
        body.theme-light .admin-dashboard .small.text-truncate,
        body.theme-light .admin-dashboard .display-count,
        body.theme-light .admin-dashboard .section-label,
        body.theme-light .admin-dashboard .section-label.muted,
        body.theme-light .admin-dashboard .stat-value,
        body.theme-light .admin-dashboard .panel-title,
        body.theme-light .admin-dashboard .recent-title {
            color: #26212c !important;
        }

        body.theme-light .admin-dashboard .metric-card .text-muted,
        body.theme-light .admin-dashboard .action-card .text-muted,
        body.theme-light .admin-dashboard .chart-shell .text-muted,
        body.theme-light .admin-dashboard .list-shell .text-muted,
        body.theme-light .admin-dashboard .report-row .text-muted,
        body.theme-light .admin-dashboard .dashboard-date,
        body.theme-light .admin-dashboard .dashboard-breadcrumb,
        body.theme-light .admin-dashboard .panel-subtitle,
        body.theme-light .admin-dashboard .report-excerpt,
        body.theme-light .admin-dashboard .extra-small {
            color: #6f6a78 !important;
        }

        body.theme-light .admin-dashboard .action-card:hover,
        body.theme-light .admin-dashboard .stat-card:hover,
        body.theme-light .admin-dashboard .chart-card:hover {
            border-color: rgba(255, 120, 158, 0.18) !important;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.55), 0 20px 40px rgba(231, 184, 190, 0.18) !important;
        }

        body.theme-light .admin-dashboard .panel-link:hover {
            color: #e15d7c !important;
        }

        body.theme-light .admin-dashboard .list-shell,
        body.theme-light .users-page .users-toolbar,
        body.theme-light .users-page .users-table-card,
        body.theme-light .memories-page .memories-toolbar,
        body.theme-light .memories-page .memories-table-card,
        body.theme-light .messaging-page .messaging-toolbar,
        body.theme-light .messaging-page .messaging-table-card,
        body.theme-light .notifications-page .notifications-toolbar,
        body.theme-light .notifications-page .notifications-table-card {
            background: rgba(255, 248, 246, 0.92) !important;
        }

        body.theme-light .users-page .users-table th,
        body.theme-light .users-page .users-table td,
        body.theme-light .memories-page .memories-table th,
        body.theme-light .memories-page .memories-table td,
        body.theme-light .messaging-page .messaging-table th,
        body.theme-light .messaging-page .messaging-table td,
        body.theme-light .notifications-page .notifications-table th,
        body.theme-light .notifications-page .notifications-table td,
        body.theme-light .messaging-page .message-bubble.received,
        body.theme-light .notifications-page .notification-item {
            border-color: rgba(92, 62, 73, 0.08) !important;
        }

        body.theme-light .users-page .toolbar-dropdown-menu .dropdown-item,
        body.theme-light .users-page .table-dropdown-menu .dropdown-item,
        body.theme-light .header-dropdown .dropdown-head,
        body.theme-light .header-dropdown .notification-title,
        body.theme-light .profile-dropdown-menu .profile-name,
        body.theme-light .profile-dropdown-menu .profile-menu-link {
            color: var(--sb-dropdown-item) !important;
        }

        body.theme-light .users-page .toolbar-dropdown-menu .dropdown-item:hover,
        body.theme-light .users-page .table-dropdown-menu .dropdown-item:hover,
        body.theme-light .header-dropdown .notification-item:hover,
        body.theme-light .profile-dropdown-menu .profile-menu-link:hover {
            background: rgba(255, 120, 158, 0.08) !important;
            color: #26212c !important;
        }

        body.theme-light .users-page .toolbar-dropdown-menu .dropdown-item.active,
        body.theme-light .users-page .toolbar-dropdown-menu .dropdown-item:active {
            background: rgba(255, 113, 151, 0.14) !important;
            color: #cf4d76 !important;
        }

        body.theme-light .header-dropdown .dropdown-divider,
        body.theme-light .profile-dropdown-menu .dropdown-divider,
        body.theme-light .users-page .table-dropdown-menu .dropdown-divider {
            border-color: rgba(92, 62, 73, 0.08) !important;
        }

        body.theme-light .header-dropdown .notification-time,
        body.theme-light .profile-dropdown-menu .profile-email {
            color: #6f6a78 !important;
        }

        body.theme-light .profile-dropdown-menu .logout-link,
        body.theme-light .users-page .table-dropdown-menu .text-danger {
            color: #ef4c4b !important;
        }

        body.theme-light .notification-dot {
            box-shadow: 0 0 0 3px var(--sb-notification-shadow);
        }

        body.theme-light #sidebar .nav-link:not(.active),
        body.theme-light .sidebar-section-title,
        body.theme-light .nav-count,
        body.theme-light .sidebar-brand small {
            color: #6f6a78 !important;
        }

        body.theme-light .table-check {
            border-color: #ff607f;
        }

        /* --- Custom Bootstrap Variable Overrides for Theme --- */
        /* Default (Dark Theme) Bootstrap variable overrides */
        body.theme-dark {
            --bs-body-bg: var(--sb-bg);
            --bs-body-color: var(--sb-text);
            --bs-border-color: var(--sb-border);
            --bs-secondary-bg: var(--sb-panel-2);
            --bs-secondary-color: var(--sb-muted);
            --bs-light-bg-subtle: var(--sb-panel-2); /* For bg-light-subtle */
            --bs-dark-bg-subtle: var(--sb-panel-2); /* For bg-dark-subtle */
            --bs-info-bg-subtle: rgba(255, 85, 122, 0.12);
            --bs-info-text-emphasis: var(--sb-pink);
            --bs-secondary-bg-subtle: var(--sb-panel-2); /* For bg-secondary-subtle */
            --bs-secondary-text-emphasis: var(--sb-muted); /* For text-secondary */

            --bs-modal-bg: var(--sb-panel);
            --bs-modal-color: var(--sb-text);
            --bs-modal-border-color: var(--sb-border);

            /* Form controls */
            --bs-form-control-bg: var(--sb-bg-input-soft);
            --bs-form-control-color: var(--sb-text);
            --bs-form-control-border-color: var(--sb-border);
            --bs-form-control-focus-bg: var(--sb-form-focus-bg);
            --bs-form-control-focus-color: var(--sb-form-focus-color);
            --bs-form-control-focus-border-color: var(--sb-form-focus-border);
            --bs-form-control-focus-shadow: var(--sb-form-focus-shadow);

            /* Table */
            --bs-table-bg: transparent; /* Tables inside glass-card will inherit glass-card bg */
            --bs-table-color: var(--sb-text);
            --bs-table-hover-bg: rgba(255, 255, 255, 0.05); /* Light hover for dark table */
            --bs-table-border-color: var(--sb-border);
            --bs-table-striped-bg: rgba(255, 255, 255, 0.02);
            --bs-table-active-bg: rgba(255, 255, 255, 0.08);

            /* Pagination */
            --bs-pagination-color: var(--sb-muted);
            --bs-pagination-bg: var(--sb-panel-2);
            --bs-pagination-border-color: var(--sb-border);
            --bs-pagination-hover-color: var(--sb-text);
            --bs-pagination-hover-bg: rgba(255, 255, 255, 0.08);
            --bs-pagination-hover-border-color: var(--sb-border);
            --bs-pagination-active-color: var(--sb-text);
            --bs-pagination-active-bg: var(--sb-pink);
            --bs-pagination-active-border-color: var(--sb-pink);
            --bs-pagination-disabled-color: var(--sb-muted);
            --bs-pagination-disabled-bg: var(--sb-panel-2);
            --bs-pagination-disabled-border-color: var(--sb-border);

            /* Heading and Base Text defaults */
            --bs-heading-color: var(--sb-text);
        }

        /* General Bootstrap component styling for both themes, if not covered by variables */
        .form-control, .form-select {
            background-color: var(--bs-form-control-bg);
            color: var(--bs-form-control-color);
            border-color: var(--bs-form-control-border-color);
        }
        .form-control:focus, .form-select:focus {
            background-color: var(--bs-form-control-focus-bg);
            color: var(--bs-form-control-focus-color);
            border-color: var(--bs-form-control-focus-border-color);
            box-shadow: var(--bs-form-control-focus-shadow);
        }

        /* Pagination links */
        .page-link {
            color: var(--bs-pagination-color);
            background-color: var(--bs-pagination-bg);
            border-color: var(--bs-pagination-border-color);
        }
        .page-item.active .page-link {
            background: linear-gradient(135deg, #ff537e 0%, #ff875d 100%) !important;
            border-color: transparent !important;
            color: white !important;
        }
        @media (max-width: 767.98px) {
            .content-area {
                width: 100%;
            }

            .admin-main {
                max-width: 100% !important;
                padding-top: 1rem !important;
                padding-bottom: 1rem !important;
            }
        }
    </style>
</head>
<body class="theme-dark">
    <div class="main-wrapper">
        @include('admin.partials.sidebar')

        <div class="content-area d-flex flex-column">
            @include('admin.partials.header')
            <main class="admin-main px-1 px-md-2 px-lg-3 px-xl-3">
                @yield('content')
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        (function () {
            const body = document.body;
            const storedTheme = localStorage.getItem('stylebite-admin-theme') || 'dark';

            body.classList.remove('theme-dark', 'theme-light');
            body.classList.add(storedTheme === 'light' ? 'theme-light' : 'theme-dark');

            const syncThemeButtons = () => {
                const isLight = body.classList.contains('theme-light');

                document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
                    const icon = button.querySelector('i');
                    if (!icon) return;

                    icon.className = isLight ? 'bi bi-moon-stars' : 'bi bi-sun';
                    button.setAttribute('title', isLight ? 'Switch to dark mode' : 'Switch to light mode');
                    button.setAttribute('aria-label', isLight ? 'Switch to dark mode' : 'Switch to light mode');
                });
            };

            syncThemeButtons();

            document.addEventListener('click', function (event) {
                const toggle = event.target.closest('[data-theme-toggle]');
                if (!toggle) return;

                const nextTheme = body.classList.contains('theme-light') ? 'dark' : 'light';
                body.classList.remove('theme-dark', 'theme-light');
                body.classList.add(nextTheme === 'light' ? 'theme-light' : 'theme-dark');
                localStorage.setItem('stylebite-admin-theme', nextTheme);
                syncThemeButtons();
                document.dispatchEvent(new CustomEvent('stylebite-theme-change', {
                    detail: { theme: nextTheme }
                }));
            });
        })();

        (function () {
            const forms = document.querySelectorAll('form[method="GET"]');

            forms.forEach((form) => {
                const queryInput = form.querySelector('input[name="q"], input[type="search"][name="q"]');
                if (!queryInput) return;

                let timer = null;
                const minChars = parseInt(queryInput.dataset.liveSearchMin || '0', 10);

                queryInput.addEventListener('input', function () {
                    const value = queryInput.value.trim();

                    clearTimeout(timer);
                    timer = setTimeout(() => {
                        if (value.length === 0 || value.length >= minChars) {
                            form.requestSubmit();
                        }
                    }, 350);
                });
            });
        })();
    </script>
</body>
</html>
