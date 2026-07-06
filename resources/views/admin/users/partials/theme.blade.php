@once
<style>
    :root {
        --sb-bg-dark-soft: rgba(0,0,0,0.25);
        --sb-bg-white-05: rgba(255,255,255,0.04);
        --sb-border-white-05: rgba(255,255,255,0.06);
        --sb-border-white-10: rgba(255,255,255,0.1);
        --sb-border-primary-soft: rgba(255, 85, 122, 0.2);
        --sb-bg-primary-soft: rgba(255, 85, 122, 0.12);
        --sb-bg-primary-soft-opaque: rgba(255, 85, 122, 0.05);
        --sb-bg-secondary-soft: rgba(255, 255, 255, 0.08);
        --sb-text-emphasis: #ffffff;
        --sb-text-main: #f5eff3;
        --sb-text-muted: #a69cab;
        --sb-muted: #8b8390;
        --sb-table-hover: rgba(255, 255, 255, 0.03);
        --sb-input-focus-bg: rgba(0,0,0,0.4);
        --sb-glass-bg: rgba(31, 26, 36, 0.9);
        --sb-glass-border: rgba(255, 255, 255, 0.1);
    }

    body.theme-light {
        --sb-bg-dark-soft: rgba(0,0,0,0.03);
        --sb-bg-white-05: rgba(0,0,0,0.02);
        --sb-border-white-05: rgba(0,0,0,0.05);
        --sb-border-white-10: rgba(0,0,0,0.1);
        --sb-border-primary-soft: rgba(255, 85, 122, 0.15);
        --sb-bg-primary-soft: rgba(255, 85, 122, 0.1);
        --sb-bg-primary-soft-opaque: rgba(255, 85, 122, 0.03);
        --sb-bg-secondary-soft: rgba(0, 0, 0, 0.04);
        --sb-text-emphasis: #1a1d20;
        --sb-text-main: #212529;
        --sb-text-muted: rgba(0, 0, 0, 0.55);
        --sb-muted: rgba(0, 0, 0, 0.4);
        --sb-table-hover: rgba(255, 120, 158, 0.05);
        --sb-input-focus-bg: rgba(255,255,255,0.9);
        --sb-glass-bg: rgba(255, 248, 246, 0.92);
        --sb-glass-border: rgba(92, 62, 73, 0.1);
    }

    .users-page { color: var(--sb-text-main); font-weight: 500; }
    .users-page .text-muted { color: var(--sb-text-muted) !important; }
    .glass { background: var(--sb-glass-bg) !important; border-color: var(--sb-glass-border) !important; backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); }
    .bg-dark-soft { background: var(--sb-bg-dark-soft) !important; color: var(--sb-text-emphasis) !important; }
    .bg-white-05 { background: var(--sb-bg-white-05) !important; }
    .border-white-05 { border-color: var(--sb-border-white-05) !important; }
    .border-white-10 { border-color: var(--sb-border-white-10) !important; }
    .border-primary-soft { border-color: var(--sb-border-primary-soft) !important; }
    .bg-primary-soft { background: var(--sb-bg-primary-soft) !important; }
    .bg-primary-soft-opaque { background: var(--sb-bg-primary-soft-opaque) !important; }
    .bg-info-soft { background: rgba(92, 148, 255, 0.12) !important; }
    .bg-warning-soft { background: rgba(242, 168, 29, 0.12) !important; }
    .bg-danger-soft { background: rgba(255, 80, 95, 0.12) !important; }
    .bg-success-soft { background: rgba(50, 211, 107, 0.12) !important; }
    .bg-secondary-soft { background: var(--sb-bg-secondary-soft) !important; }
    .text-emphasis-dynamic { color: var(--sb-text-emphasis) !important; }
    .extra-small { font-size: 0.75rem; }
    .fw-extrabold { font-weight: 800; }

    .table { color: inherit !important; background-color: transparent !important; border-collapse: separate; border-spacing: 0; }
    .table > :not(caption) > * > * { background-color: transparent !important; color: inherit !important; box-shadow: none !important; border-bottom-color: var(--sb-border-white-05) !important; }
    .table thead th { background-color: var(--sb-bg-white-05) !important; border-bottom: 1px solid var(--sb-border-white-10) !important; color: var(--sb-text-muted); }
    .table-hover tbody tr:hover td { background-color: var(--sb-table-hover) !important; }

    .dropdown-menu.glass { background: var(--sb-glass-bg) !important; border: 1px solid var(--sb-border-white-10) !important; }
    .dropdown-item { color: var(--sb-text-main) !important; }
    .dropdown-item:hover { background: var(--sb-bg-white-05) !important; color: var(--sb-text-emphasis) !important; }

    .btn-outline-dynamic {
        border-color: var(--sb-border-white-10) !important;
        color: var(--sb-text-emphasis) !important;
    }
    .btn-outline-dynamic:hover {
        background: var(--sb-bg-white-05);
        color: var(--sb-text-emphasis) !important;
    }

    .btn-icon {
        width: 32px;
        height: 32px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        color: var(--sb-muted);
    }

    .avatar-fallback,
    .user-avatar {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        object-fit: cover;
    }

    .avatar-fallback {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        background: linear-gradient(135deg, #ff557a, #ff8a57);
        font-weight: 800;
    }

    .dot-indicator { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
    .group:hover .group-hover-opacity-100 { opacity: 1 !important; }

    .form-control:focus,
    .form-select:focus {
        background: var(--sb-input-focus-bg) !important;
        color: var(--sb-text-emphasis);
        box-shadow: 0 0 0 2px rgba(255, 85, 122, 0.2);
        border-color: rgba(255, 85, 122, 0.3) !important;
    }

    .page-link {
        color: var(--sb-muted);
        padding: 0.4rem 0.8rem;
    }

    .detail-tile {
        min-height: 104px;
    }

    .confirm-modal .modal-content {
        background: var(--sb-glass-bg);
        color: var(--sb-text-main);
        border: 1px solid var(--sb-border-white-10);
        border-radius: 24px;
    }
</style>
@endonce
