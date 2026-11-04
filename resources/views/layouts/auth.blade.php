<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Sign In') — Élixir Botanique</title>

    <!-- Google Fonts: Plus Jakarta Sans (English), Battambang & Noto Sans Khmer (Khmer), Noto Sans SC (Chinese), Playfair Display (Serif) -->
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
            --border-radius: 12px;
            --shadow-subtle: 0 4px 20px rgba(37, 43, 37, 0.05);
            --shadow-hover: 0 10px 30px rgba(37, 43, 37, 0.08);
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
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 24px;
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

        .auth-card {
            background-color: var(--surface);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-subtle);
            width: 100%;
            max-width: 440px;
            overflow: hidden;
            transition: box-shadow 0.25s ease;
        }

        .auth-card:hover {
            box-shadow: var(--shadow-hover);
        }

        .brand-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 52px;
            height: 52px;
            border-radius: 14px;
            background-color: var(--primary-subtle);
            color: var(--primary);
            font-size: 26px;
            margin-bottom: 16px;
        }

        .form-label {
            font-size: 0.84rem;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 6px;
        }

        .form-control {
            border: 1.5px solid var(--border-color);
            border-radius: 9px;
            padding: 10px 14px;
            font-size: 0.92rem;
            color: var(--text-main);
            background-color: #FAFAFA;
            transition: border-color 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
        }

        .form-control:focus {
            background-color: var(--surface);
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(113, 133, 107, 0.18);
        }

        .btn-primary-skincare {
            background-color: var(--primary);
            border-color: var(--primary);
            color: #FFFFFF;
            font-weight: 600;
            border-radius: 9px;
            padding: 11px 18px;
            font-size: 0.95rem;
            letter-spacing: 0.2px;
            transition: all 0.2s ease;
        }

        .btn-primary-skincare:hover, .btn-primary-skincare:focus {
            background-color: var(--primary-hover);
            border-color: var(--primary-hover);
            color: #FFFFFF;
            transform: translateY(-1px);
        }

        .form-check-input:checked {
            background-color: var(--primary);
            border-color: var(--primary);
        }

        .input-group-text {
            background-color: #FAFAFA;
            border: 1.5px solid var(--border-color);
            border-left: none;
            border-radius: 0 9px 9px 0;
            color: var(--text-muted);
            cursor: pointer;
        }

        .input-group .form-control {
            border-right: none;
            border-radius: 9px 0 0 9px;
        }

        .badge-role {
            font-size: 0.75rem;
            padding: 4px 10px;
            border-radius: 20px;
            font-weight: 600;
        }

        .role-btn {
            background: #F2F4EF;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 0.8rem;
            text-align: left;
            cursor: pointer;
            transition: all 0.15s ease;
            width: 100%;
        }

        .role-btn:hover {
            background: var(--primary-subtle);
            border-color: var(--primary);
        }
    </style>
</head>
<body>
    @yield('content')

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
