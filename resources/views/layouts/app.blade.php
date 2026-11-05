<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Élixir Botanique') — Clinical Skincare & Dispensary</title>

    <!-- Google Fonts: Plus Jakarta Sans (English), Battambang & Noto Sans Khmer (Khmer), Noto Sans SC (Chinese), Playfair Display (Serif Brand) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Battambang:wght@300;400;700;900&family=Noto+Sans+Khmer:wght@300;400;500;600;700&family=Noto+Sans+SC:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,400&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

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
            --font-serif: 'Playfair Display', Georgia, Cambria, serif;
            --font-sans: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
        }

        .font-serif {
            font-family: var(--font-serif) !important;
        }

        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            background-color: var(--bg-app);
            color: var(--text-main);
            margin: 0;
            overflow-x: hidden;
            font-size: 0.88rem;
        }

        html[lang="km"] body, .lang-km {
            font-family: 'Battambang', 'Noto Sans Khmer', 'Kantumruy Pro', sans-serif !important;
            line-height: 1.68;
        }

        html[lang="zh"] body, .lang-zh {
            font-family: 'Noto Sans SC', 'PingFang SC', 'Microsoft YaHei', sans-serif !important;
            line-height: 1.5;
        }

        html[lang="en"] body, .lang-en {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif !important;
            line-height: 1.5;
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
            border-bottom: 1px solid var(--border-color);
            text-decoration: none;
            color: var(--text-main);
            flex-shrink: 0;
            user-select: none;
        }

        #sidebar.collapsed .sidebar-brand {
            padding: 0 !important;
            justify-content: center !important;
        }

        .sidebar-brand-text {
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .sidebar-brand-title {
            font-family: var(--font-serif) !important;
            font-size: 1.18rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            line-height: 1.12;
            color: var(--text-main);
            white-space: nowrap;
        }

        .sidebar-brand-tagline {
            font-size: 0.58rem;
            font-weight: 600;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-top: 3px;
            white-space: nowrap;
        }

        .sidebar-brand-collapsed {
            display: none;
            font-family: var(--font-serif) !important;
            font-size: 1.25rem;
            font-weight: 700;
            line-height: 1;
            letter-spacing: 0.04em;
            color: var(--text-main);
            text-align: center;
        }

        #sidebar.collapsed .sidebar-brand-collapsed {
            display: block !important;
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
            padding: clamp(10px, 2.5vw, 14px) clamp(12px, 3vw, 18px);
            background-color: transparent;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
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

        @media (max-width: 767.98px) {
            #topbar {
                padding: 0 12px !important;
                height: 54px !important;
            }
            #main-content {
                padding: 14px 12px !important;
            }
            .card-skincare {
                margin-bottom: 14px !important;
                border-radius: 8px !important;
            }
            .card-header-skincare {
                padding: 10px 12px !important;
                flex-wrap: wrap !important;
                gap: 8px !important;
            }
            .table-skincare thead th {
                padding: 8px 10px !important;
                font-size: 0.7rem !important;
            }
            .table-skincare tbody td {
                padding: 8px 10px !important;
                font-size: 0.82rem !important;
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

        /* ─── TOP PROGRESS BAR ─── */
        #pos-progress {
            position: fixed;
            top: 0;
            left: 0;
            width: 0%;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), #9DBF96);
            z-index: 9999;
            transition: width 0.25s ease, opacity 0.3s ease;
            opacity: 0;
            border-radius: 0 2px 2px 0;
            box-shadow: 0 0 8px rgba(113, 133, 107, 0.5);
        }
        #pos-progress.active {
            opacity: 1;
        }

        /* Soft content transition on AJAX page swap */
        .pos-page-transition {
            opacity: 0.6;
            transition: opacity 0.15s ease;
        }
    </style>
    @stack('styles')
</head>
<body>
    <!-- Top Progress Bar -->
    <div id="pos-progress"></div>
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

        // Auto-close sidebar on mobile navigation click
        document.querySelectorAll('#sidebar .sidebar-link, #sidebar .sidebar-sub-link').forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth < 992) {
                    sidebar.classList.remove('show-mobile');
                    backdrop.classList.remove('show');
                }
            });
        });

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

    {{-- ═══════════════════════════════════════════════════════════════
         UNIVERSAL FAST TURBO NAVIGATION & AJAX CRUD ENGINE
         • Instant Turbo soft-navigation on page links & hover prefetching
         • No white-screen flash, seamless opacity transitions
         • Intercepts modal & inline CRUD forms for instant AJAX response
         • In-place DOM update, active sidebar tracking, clean toasts
    ═══════════════════════════════════════════════════════════════ --}}
    <script>
    (function () {
        'use strict';

        /* ── Progress bar helpers ── */
        const bar = document.getElementById('pos-progress');
        let barTimer;
        function progressStart() {
            if (!bar) return;
            clearTimeout(barTimer);
            bar.style.width = '0%';
            bar.style.background = '';
            bar.classList.add('active');
            setTimeout(() => { if (bar) bar.style.width = '70%'; }, 20);
        }
        function progressDone() {
            if (!bar) return;
            bar.style.width = '100%';
            barTimer = setTimeout(() => {
                if (bar) {
                    bar.classList.remove('active');
                    bar.style.width = '0%';
                }
            }, 300);
        }
        function progressFail() {
            if (!bar) return;
            bar.style.background = '#E07070';
            bar.style.width = '100%';
            barTimer = setTimeout(() => {
                if (bar) {
                    bar.classList.remove('active');
                    bar.style.width = '0%';
                    bar.style.background = '';
                }
            }, 500);
        }

        /* ── Toast helper (delegates to SkinCareToast if available) ── */
        function toast(type, msg) {
            if (window.SkinCareToast && window.SkinCareToast[type]) {
                window.SkinCareToast[type](msg);
                return;
            }
            const el = document.createElement('div');
            el.className = 'toast align-items-center border-0 text-white show m-2';
            el.style.cssText = 'position:fixed;top:18px;right:18px;z-index:10000;min-width:280px;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,0.15);background:' + (type === 'success' ? '#71856B' : '#D9534F');
            el.innerHTML = '<div class="d-flex"><div class="toast-body">' + msg + '</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
            document.body.appendChild(el);
            setTimeout(() => el.remove(), 4000);
        }

        /* ── Spin button helper ── */
        function spinBtn(btn, spinning) {
            if (!btn) return;
            if (spinning) {
                btn.dataset.posOrigHtml = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Processing…';
            } else {
                btn.disabled = false;
                btn.innerHTML = btn.dataset.posOrigHtml || btn.innerHTML;
            }
        }

        /* ── Clear existing field errors inside a form ── */
        function clearErrors(form) {
            form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
            form.querySelectorAll('.pos-field-error').forEach(el => el.remove());
        }

        /* ── Show inline 422 field errors ── */
        function showErrors(form, errors) {
            Object.entries(errors).forEach(([field, msgs]) => {
                const safeName = field.replace(/\.([0-9]+)\./g, '[$1].').replace(/\.([0-9]+)$/, '[$1]');
                const input = form.querySelector('[name="' + field + '"], [name="' + safeName + '"]');
                if (input) {
                    input.classList.add('is-invalid');
                    const errEl = document.createElement('div');
                    errEl.className = 'invalid-feedback pos-field-error d-block';
                    errEl.textContent = Array.isArray(msgs) ? msgs[0] : msgs;
                    input.parentNode.insertBefore(errEl, input.nextSibling);
                }
            });
        }

        /* ── Modal cleanup ── */
        function cleanupModals() {
            document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('overflow');
            document.body.style.removeProperty('padding-right');
        }

        /* ── Run scripts dynamically from fetched HTML ── */
        function executeScripts(container) {
            if (!container) return;
            const scripts = container.querySelectorAll('script');
            scripts.forEach(oldScript => {
                const newScript = document.createElement('script');
                Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
                newScript.textContent = oldScript.textContent;
                oldScript.parentNode.replaceChild(newScript, oldScript);
            });
        }

        /* ── Update active sidebar link ── */
        function updateSidebarActive(newPath) {
            document.querySelectorAll('#sidebar .sidebar-link, #sidebar .sidebar-sub-link').forEach(link => {
                try {
                    const linkUrl = new URL(link.href, window.location.origin);
                    const isExact = (linkUrl.pathname === newPath);
                    if (link.classList.contains('sidebar-link')) {
                        link.classList.toggle('active', isExact);
                    } else if (link.classList.contains('sidebar-sub-link')) {
                        link.classList.toggle('active', isExact);
                    }
                } catch (e) {}
            });
        }

        /* ── Prefetch cache map ── */
        const prefetchCache = new Map();

        function prefetchPage(url) {
            if (prefetchCache.has(url)) return;
            const fetchPromise = fetch(url, {
                headers: { 'X-Requested-With': 'turbo-fetch', 'Cache-Control': 'max-age=60' }
            }).then(r => r.ok ? r.text() : null).catch(() => null);
            prefetchCache.set(url, fetchPromise);
            setTimeout(() => prefetchCache.delete(url), 30000);
        }

        /* ── Soft page content refresh (no full reload, no white flash) ── */
        function softRefresh(targetUrl = window.location.href, pushHistory = false) {
            progressStart();
            const mainEl = document.getElementById('main-content');
            if (mainEl) mainEl.classList.add('pos-page-transition');

            const fetchPromise = prefetchCache.get(targetUrl) || fetch(targetUrl, {
                headers: { 'X-Requested-With': 'turbo-fetch', 'Cache-Control': 'no-cache' }
            }).then(r => {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.text();
            });

            prefetchCache.delete(targetUrl);

            fetchPromise
                .then(html => {
                    if (!html) throw new Error('Empty response');
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newMain = doc.getElementById('main-content');

                    if (newMain && mainEl) {
                        mainEl.innerHTML = newMain.innerHTML;
                        mainEl.classList.remove('pos-page-transition');

                        // Update title
                        if (doc.title) document.title = doc.title;

                        // Update topbar breadcrumb & page title
                        const newBreadcrumb = doc.querySelector('.breadcrumb');
                        const curBreadcrumb = document.querySelector('.breadcrumb');
                        if (newBreadcrumb && curBreadcrumb) curBreadcrumb.innerHTML = newBreadcrumb.innerHTML;

                        const newTitle = doc.querySelector('#topbar .fw-bold');
                        const curTitle = document.querySelector('#topbar .fw-bold');
                        if (newTitle && curTitle) curTitle.innerHTML = newTitle.innerHTML;

                        // Push history if navigating to new page
                        if (pushHistory && targetUrl !== window.location.href) {
                            window.history.pushState({ url: targetUrl }, '', targetUrl);
                        }

                        // Update active sidebar link
                        try {
                            const parsedUrl = new URL(targetUrl, window.location.origin);
                            updateSidebarActive(parsedUrl.pathname);
                        } catch (e) {}

                        // Sync new head styles
                        try {
                            doc.querySelectorAll('head style, head link[rel="stylesheet"]').forEach(s => {
                                if (s.tagName.toLowerCase() === 'link' && s.href && document.querySelector(`link[href="${s.href}"]`)) return;
                                document.head.appendChild(s.cloneNode(true));
                            });
                        } catch (e) {}

                        // Execute page scripts
                        const newPageScripts = doc.getElementById('page-scripts-container');
                        const curPageScripts = document.getElementById('page-scripts-container');
                        if (newPageScripts && curPageScripts) {
                            curPageScripts.innerHTML = newPageScripts.innerHTML;
                            executeScripts(curPageScripts);
                        }

                        // Also execute any scripts inside #main-content
                        executeScripts(mainEl);

                        // Re-init Bootstrap tooltips
                        mainEl.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));

                        // Re-attach engine to new forms
                        attachEngine();

                        // Fire app page loaded event
                        document.dispatchEvent(new CustomEvent('app:page-loaded', { detail: { url: targetUrl } }));

                        // Scroll to top
                        window.scrollTo({ top: 0, behavior: 'instant' });
                    } else {
                        window.location.href = targetUrl;
                    }
                    progressDone();
                })
                .catch(() => {
                    progressFail();
                    window.location.href = targetUrl;
                });
        }

        /* ── Core AJAX submit handler for CRUD forms ── */
        function handleFormSubmit(e) {
            const form = e.currentTarget;
            if (form.dataset.skipAjax) return;

            e.preventDefault();
            e.stopPropagation();

            clearErrors(form);

            const submitBtn = form.querySelector('[type="submit"]') || form.querySelector('button:not([type="button"])');
            spinBtn(submitBtn, true);
            progressStart();

            const method = (form.method || 'POST').toUpperCase();
            const action = form.action;
            const fd = new FormData(form);

            fetch(action, {
                method: method,
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: fd,
            })
            .then(async (res) => {
                let data;
                try { data = await res.json(); } catch { data = {}; }

                if (res.ok || res.status === 201) {
                    progressDone();
                    spinBtn(submitBtn, false);

                    // Close any open Bootstrap modal
                    const modalEl = form.closest('.modal');
                    if (modalEl) {
                        const bsModal = bootstrap.Modal.getInstance(modalEl);
                        if (bsModal) bsModal.hide();
                        setTimeout(cleanupModals, 200);
                    }

                    const msg = data.message || 'Action completed successfully.';
                    toast('success', msg);

                    // Soft-refresh the page content
                    softRefresh();

                } else if (res.status === 422) {
                    progressFail();
                    spinBtn(submitBtn, false);
                    const msg = data.message || 'Please fix the errors below.';
                    toast('error', msg);
                    if (data.errors) showErrors(form, data.errors);

                } else if (res.status === 419) {
                    progressFail();
                    spinBtn(submitBtn, false);
                    toast('error', 'Session expired. Please refresh the page and try again.');

                } else if (res.status === 413) {
                    progressFail();
                    spinBtn(submitBtn, false);
                    toast('error', 'Uploaded file is too large. Please select an image under 10MB.');

                } else {
                    progressFail();
                    spinBtn(submitBtn, false);
                    let msg = data.message || '';
                    if (!msg || msg === 'Server Error') {
                        msg = 'Server encountered an error saving the formulation. Please verify the fields and try again.';
                    }
                    toast('error', msg);
                }
            })
            .catch(() => {
                progressFail();
                spinBtn(submitBtn, false);
                toast('error', 'Network error. Please check your connection.');
            });
        }

        /* ── Turbo Soft Navigation Click & Hover Interceptor ── */
        function attachNavigation() {
            document.addEventListener('click', function (e) {
                const link = e.target.closest('a[href]');
                if (!link) return;

                const href = link.getAttribute('href');
                if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:')) return;
                if (link.target === '_blank' || link.hasAttribute('download') || link.dataset.skipTurbo === 'true') return;

                try {
                    const targetUrl = new URL(link.href, window.location.origin);
                    if (targetUrl.origin !== window.location.origin) return;

                    // Don't intercept logout, API, or language switch routes
                    if (targetUrl.pathname === '/logout' || targetUrl.pathname.startsWith('/api/') || targetUrl.pathname.startsWith('/language/')) return;

                    e.preventDefault();
                    softRefresh(targetUrl.href, true);
                } catch (err) {}
            });

            // Hover prefetch for instantaneous navigation
            document.addEventListener('mouseover', function (e) {
                const link = e.target.closest('a[href]');
                if (!link) return;
                const href = link.getAttribute('href');
                if (!href || href.startsWith('#') || link.target === '_blank' || link.dataset.skipTurbo === 'true' || href.includes('/language/')) return;

                try {
                    const targetUrl = new URL(link.href, window.location.origin);
                    if (targetUrl.origin === window.location.origin && targetUrl.pathname !== window.location.pathname) {
                        prefetchPage(targetUrl.href);
                    }
                } catch (err) {}
            }, { passive: true });

            // Handle Browser Back / Forward buttons
            window.addEventListener('popstate', function () {
                softRefresh(window.location.href, false);
            });
        }

        /* ── Attach engine to forms ── */
        function attachEngine() {
            // Modal forms (create / edit / delete)
            document.querySelectorAll('.modal form:not([data-skip-ajax])').forEach(form => {
                if (!form.dataset.posEngineAttached) {
                    form.dataset.posEngineAttached = '1';
                    form.addEventListener('submit', handleFormSubmit);
                }
            });

            // Standalone quick-action forms outside modals (toggle status, inline actions)
            document.querySelectorAll('form.pos-quick-form:not([data-skip-ajax]), .pos-quick-action form:not([data-skip-ajax])').forEach(form => {
                if (!form.dataset.posEngineAttached) {
                    form.dataset.posEngineAttached = '1';
                    form.addEventListener('submit', handleFormSubmit);
                }
            });
        }

        // Initial attach
        document.addEventListener('DOMContentLoaded', function () {
            attachEngine();
            attachNavigation();
        });

        document.addEventListener('shown.bs.modal', attachEngine);

        // Expose helpers globally
        window.posAttachEngine = attachEngine;
        window.posSoftRefresh = softRefresh;
        window.posToast = toast;
    })();
    </script>
    <div id="page-scripts-container">
        @stack('scripts')
    </div>
</body>
</html>
