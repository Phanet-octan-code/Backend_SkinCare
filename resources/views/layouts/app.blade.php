<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'SkinCare POS') — Luxury Retail Management</title>

    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3 & Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        :root {
            --primary: #71856B;
            --primary-hover: #596D54;
            --primary-subtle: #EBF0E9;
            --bg-app: #F7F8F5;
            --surface: #FFFFFF;
            --text-main: #252B25;
            --text-muted: #777F76;
            --border-color: #E6E9E2;
            --sidebar-width: 255px;
            --sidebar-collapsed-width: 68px;
            --topbar-height: 60px;
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 14px;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-app);
            color: var(--text-main);
            margin: 0;
            overflow-x: hidden;
            font-size: 0.88rem;
        }

        /* ─── PRINT OPTIMIZATIONS ─── */
        @media print {
            #sidebar, #topbar, .d-print-none, .btn, .dropdown, .pagination, .toast-container, .alert {
                display: none !important;
            }
            #main-content {
                margin-left: 0 !important;
                padding: 10px !important;
                width: 100% !important;
            }
            body {
                background: #ffffff !important;
                color: #000000 !important;
            }
            .card-skincare {
                border: 1px solid #ddd !important;
                box-shadow: none !important;
                break-inside: avoid;
            }
        }

        /* ─── SIDEBAR STYLES ─── */
        #sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            background-color: var(--surface);
            border-right: 1px solid var(--border-color);
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1040;
            transition: all 0.22s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
        }

        #sidebar.collapsed {
            width: var(--sidebar-collapsed-width);
        }

        #sidebar.collapsed .sidebar-brand-text,
        #sidebar.collapsed .sidebar-section-title,
        #sidebar.collapsed .nav-label,
        #sidebar.collapsed .sidebar-sub-menu,
        #sidebar.collapsed .sidebar-user-info {
            display: none !important;
        }

        #sidebar.collapsed .sidebar-link {
            justify-content: center;
            padding: 10px 0;
        }

        #sidebar.collapsed .sidebar-link i {
            margin: 0;
            font-size: 1.25rem;
        }

        .sidebar-brand {
            height: var(--topbar-height);
            padding: 0 18px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid var(--border-color);
            text-decoration: none;
            color: var(--text-main);
            flex-shrink: 0;
        }

        .brand-icon-box {
            width: 36px;
            height: 36px;
            border-radius: 9px;
            background-color: var(--primary-subtle);
            color: var(--primary);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .sidebar-scrollable-container {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 10px 8px;
        }

        /* Custom subtle scrollbar */
        .sidebar-scrollable-container::-webkit-scrollbar {
            width: 4px;
        }
        .sidebar-scrollable-container::-webkit-scrollbar-thumb {
            background-color: #E2E6DF;
            border-radius: 4px;
        }

        .sidebar-section-title {
            font-size: 0.67rem;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: var(--text-muted);
            font-weight: 700;
            padding: 14px 12px 6px;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar-menu-item {
            margin-bottom: 3px;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            border-radius: 8px;
            text-decoration: none;
            color: var(--text-main);
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.15s ease;
        }

        .sidebar-link i {
            font-size: 1.05rem;
            color: var(--text-muted);
            transition: color 0.15s ease;
            width: 22px;
            text-align: center;
            flex-shrink: 0;
        }

        .sidebar-link:hover {
            background-color: var(--primary-subtle);
            color: var(--primary-hover);
        }

        .sidebar-link:hover i {
            color: var(--primary);
        }

        .sidebar-link.active {
            background-color: var(--primary);
            color: #FFFFFF;
            font-weight: 600;
        }

        .sidebar-link.active i {
            color: #FFFFFF;
        }

        .sidebar-link.active-parent {
            background-color: #F0F3ED;
            color: var(--primary);
            font-weight: 600;
        }

        .sidebar-sub-link {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 10px;
            border-radius: 6px;
            text-decoration: none;
            color: var(--text-muted);
            font-size: 0.8rem;
            font-weight: 500;
            margin-bottom: 2px;
            transition: all 0.15s ease;
        }

        .sidebar-sub-link:hover, .sidebar-sub-link.active {
            color: var(--primary);
            background-color: var(--primary-subtle);
            font-weight: 600;
        }

        /* ─── TOPBAR STYLES ─── */
        #topbar {
            margin-left: var(--sidebar-width);
            height: var(--topbar-height);
            background-color: var(--surface);
            border-bottom: 1px solid var(--border-color);
            padding: 0 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 1030;
            transition: margin-left 0.22s cubic-bezier(0.4, 0, 0.2, 1);
        }

        #topbar.expanded {
            margin-left: var(--sidebar-collapsed-width);
        }

        /* ─── MAIN WORKSPACE STYLES ─── */
        #main-content {
            margin-left: var(--sidebar-width);
            padding: 24px 28px;
            min-height: calc(100vh - var(--topbar-height));
            transition: margin-left 0.22s cubic-bezier(0.4, 0, 0.2, 1);
        }

        #main-content.expanded {
            margin-left: var(--sidebar-collapsed-width);
        }

        /* ─── CARD & UI SYSTEM ─── */
        .card-skincare {
            background-color: var(--surface);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            box-shadow: 0 1px 3px rgba(37, 43, 37, 0.03);
            margin-bottom: 20px;
        }

        .card-header-skincare {
            padding: 14px 18px;
            background-color: transparent;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .btn-skincare-primary {
            background-color: var(--primary);
            border-color: var(--primary);
            color: #FFFFFF;
            font-weight: 600;
            border-radius: 8px;
            padding: 7px 14px;
            font-size: 0.85rem;
            transition: all 0.15s ease;
        }

        .btn-skincare-primary:hover, .btn-skincare-primary:focus {
            background-color: var(--primary-hover);
            border-color: var(--primary-hover);
            color: #FFFFFF;
        }

        .btn-skincare-outline {
            background-color: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-main);
            font-weight: 500;
            border-radius: 8px;
            padding: 6px 12px;
            font-size: 0.85rem;
            transition: all 0.15s ease;
        }

        .btn-skincare-outline:hover {
            background-color: #F2F4EF;
            border-color: #DDE2D8;
            color: var(--text-main);
        }

        .avatar-circle {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background-color: var(--primary-subtle);
            color: var(--primary);
            font-weight: 700;
            font-size: 0.8rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .badge-role-pill {
            font-size: 0.68rem;
            padding: 2px 7px;
            border-radius: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-admin { background-color: #252B25; color: #FFFFFF; }
        .badge-manager { background-color: #596D54; color: #FFFFFF; }
        .badge-cashier { background-color: #71856B; color: #FFFFFF; }
        .badge-inventory { background-color: #8C7355; color: #FFFFFF; }
        .badge-hr { background-color: #4C6E87; color: #FFFFFF; }

        /* Mobile Backdrop Overlay */
        .sidebar-backdrop {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background-color: rgba(37, 43, 37, 0.4);
            z-index: 1035;
        }

        @media (max-width: 991.98px) {
            #sidebar {
                margin-left: calc(-1 * var(--sidebar-width));
            }
            #sidebar.show-mobile {
                margin-left: 0 !important;
                width: var(--sidebar-width) !important;
            }
            .sidebar-backdrop.show {
                display: block;
            }
            #topbar, #main-content {
                margin-left: 0 !important;
            }
        }

        /* ─── STANDARDIZED TABLE STYLES ─── */
        .table-skincare {
            width: 100%;
            margin-bottom: 0;
            font-size: 0.88rem;
            vertical-align: middle;
        }

        .table-skincare thead th {
            background-color: #F8FAF7;
            color: var(--text-muted);
            font-size: 0.74rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-color);
            white-space: nowrap;
        }

        .table-skincare tbody td {
            padding: 12px 16px;
            border-bottom: 1px solid #EEF1EB;
            color: var(--text-main);
        }

        .table-skincare tbody tr:last-child td {
            border-bottom: none;
        }

        .table-skincare tbody tr:hover {
            background-color: #F9FAF8;
        }

        /* ─── STANDARDIZED PAGINATION STYLES ─── */
        .pagination {
            margin-bottom: 0;
            gap: 4px;
        }

        .page-link {
            color: var(--text-main);
            border: 1px solid var(--border-color);
            border-radius: 6px !important;
            font-size: 0.82rem;
            font-weight: 500;
            padding: 5px 11px;
            background-color: var(--surface);
            transition: all 0.15s ease;
        }

        .page-link:hover {
            background-color: var(--primary-subtle);
            color: var(--primary-hover);
            border-color: var(--primary);
        }

        .page-item.active .page-link {
            background-color: var(--primary);
            border-color: var(--primary);
            color: #FFFFFF;
        }

        .page-item.disabled .page-link {
            color: #ADB4A9;
            background-color: #F8F9F7;
            border-color: var(--border-color);
        }

        /* Form control focus states */
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(113, 133, 107, 0.18);
        }
    </style>
    @stack('styles')
</head>
<body>
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <!-- Collapsible Role-Based Sidebar Navigation -->
    @include('partials.sidebar')

    <!-- Compact Topbar with Notifications, Language, and Profile -->
    @include('partials.topbar')

    <!-- Toast Notification Container & JS Helper -->
    <x-toast />

    <!-- Main Content Workspace -->
    <main id="main-content">
        <!-- Toast & Alert Banners -->
        @if (session('status'))
            <div class="alert alert-success d-flex align-items-center py-2 px-3 mb-4 small alert-dismissible fade show" role="alert" style="border-radius: 8px;">
                <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                <div>{{ session('status') }}</div>
                <button type="button" class="btn-close py-2" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if (session('success'))
            <div class="alert alert-success d-flex align-items-center py-2 px-3 mb-4 small alert-dismissible fade show" role="alert" style="border-radius: 8px;">
                <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                <div>{{ session('success') }}</div>
                <button type="button" class="btn-close py-2" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger d-flex align-items-center py-2 px-3 mb-4 small alert-dismissible fade show" role="alert" style="border-radius: 8px;">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                <div>{{ session('error') }}</div>
                <button type="button" class="btn-close py-2" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger py-2 px-3 mb-4 small alert-dismissible fade show" role="alert" style="border-radius: 8px;">
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close py-2" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @yield('content')
    </main>

    <!-- Bootstrap 5 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Initialize tooltips
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

        // Sidebar Collapse & Mobile Drawer Logic
        const toggleBtn = document.getElementById('sidebarToggleBtn');
        const sidebar = document.getElementById('sidebar');
        const topbar = document.getElementById('topbar');
        const mainContent = document.getElementById('main-content');
        const backdrop = document.getElementById('sidebarBackdrop');

        function toggleSidebar() {
            if (window.innerWidth < 992) {
                sidebar.classList.toggle('show-mobile');
                backdrop.classList.toggle('show');
            } else {
                sidebar.classList.toggle('collapsed');
                topbar.classList.toggle('expanded');
                mainContent.classList.toggle('expanded');
            }
        }

        if (toggleBtn) {
            toggleBtn.addEventListener('click', toggleSidebar);
        }

        if (backdrop) {
            backdrop.addEventListener('click', function () {
                sidebar.classList.remove('show-mobile');
                backdrop.classList.remove('show');
            });
        }

        // Language Switcher Helper
        function setAppLanguage(lang, label) {
            document.getElementById('currentLangLabel').innerText = lang.toUpperCase();
            localStorage.setItem('pos_lang', lang);
        }

        // Keyboard Shortcut: Ctrl + K focuses global search
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                const searchInput = document.querySelector('input[name="search"]');
                if (searchInput) searchInput.focus();
            }
        });
    </script>
    @stack('scripts')
</body>
</html>
