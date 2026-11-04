@php
    $topbarAlerts = \Illuminate\Support\Facades\Cache::remember('topbar_low_stock_alerts', 60, function () {
        return [
            'products' => \App\Models\Product::lowStock()->select(['id', 'name', 'barcode', 'qty'])->take(5)->get(),
            'count' => \App\Models\Product::lowStock()->count(),
        ];
    });
    $lowStockProducts = $topbarAlerts['products'];
    $lowStockCount = $topbarAlerts['count'];
    $storeName = \App\Models\Setting::get('business_name', 'Élixir Botanique Dispensary');
@endphp

<header id="topbar">
    <!-- Left: Sidebar Toggle, Topbar Breadcrumb & Current Page Title -->
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-sm btn-skincare-outline p-2 border-0" id="sidebarToggleBtn" type="button" title="Toggle Navigation (Desktop / Mobile)">
            <i class="bi bi-list fs-5"></i>
        </button>

        <div class="d-flex flex-column justify-content-center" style="line-height: 1.2;">
            <nav aria-label="breadcrumb" class="d-none d-sm-block">
                <ol class="breadcrumb mb-0 py-0" style="font-size: 0.72rem;">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-muted">{{ __('messages.dashboard') }}</a></li>
                    @yield('breadcrumb')
                </ol>
            </nav>
            <div class="fw-bold text-dark text-truncate" style="font-size: 0.92rem; max-width: clamp(120px, 28vw, 260px); letter-spacing: -0.3px;">
                @yield('title', __('messages.app_name'))
            </div>
        </div>

        <div class="d-none d-xl-flex align-items-center gap-2 text-muted small ms-2 ps-3 border-start" style="height: 28px;">
            <span class="d-flex align-items-center gap-1 text-dark fw-semibold" style="font-size: 0.8rem;">
                <i class="bi bi-shop text-success"></i>
                <span class="text-truncate" style="max-width: 170px;">{{ $storeName }}</span>
            </span>
            <span class="text-muted">&bull;</span>
            <span class="badge bg-light text-muted border" style="font-size: 0.68rem;">{{ __('messages.station') }}</span>
        </div>
    </div>

    <!-- Center/Right: Global Search, Quick Actions, Notifications, Language, User Menu -->
    <div class="d-flex align-items-center gap-2 gap-md-3">
        <!-- Quick Global Search with Ctrl+K shortcut indicator -->
        <div class="d-none d-lg-block">
            <form method="GET" action="{{ route('products.index') }}" class="position-relative">
                <input type="text" 
                       name="search" 
                       class="form-control form-control-sm ps-4 pe-5 bg-light border-0" 
                       placeholder="{{ __('messages.search_placeholder') }}" 
                       value="{{ request('search') }}"
                       style="border-radius: 20px; width: 230px; font-size: 0.8rem;">
                <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-2 text-muted" style="font-size: 0.75rem;"></i>
                <kbd class="position-absolute top-50 end-0 translate-middle-y me-2 bg-white text-muted border px-1" style="font-size: 0.65rem; border-radius: 4px;">Ctrl+K</kbd>
            </form>
        </div>

        <!-- POS Quick Launch Button (If Cashier, Manager, Admin) -->
        @if(auth()->user()->isCashier())
        <a href="{{ route('pos.index') }}" data-skip-turbo="true" class="btn btn-sm btn-skincare-primary d-none d-sm-inline-flex align-items-center gap-1 px-3">
            <i class="bi bi-cart3"></i>
            <span>{{ __('messages.pos_screen') }}</span>
        </a>
        @endif

        <!-- Low Stock Notifications Dropdown -->
        <div class="dropdown">
            <button class="btn btn-sm btn-skincare-outline position-relative p-2" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="{{ __('messages.inventory_alerts') }}">
                <i class="bi bi-bell"></i>
                @if($lowStockCount > 0)
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem;">
                    {{ $lowStockCount }}
                </span>
                @endif
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm border p-0 mt-2" style="width: 320px; border-radius: 12px; overflow: hidden;">
                <li class="bg-light px-3 py-2 border-bottom d-flex align-items-center justify-content-between">
                    <span class="fw-bold small text-dark">{{ __('messages.inventory_alerts') }}</span>
                    <span class="badge bg-danger-subtle text-danger" style="font-size: 0.7rem;">{{ $lowStockCount }} {{ __('messages.low') }}</span>
                </li>
                @forelse($lowStockProducts as $lowItem)
                <li class="border-bottom">
                    <a class="dropdown-item py-2 px-3 small text-wrap d-flex justify-content-between align-items-center" href="{{ route('inventory.index') }}">
                        <div>
                            <div class="fw-semibold text-dark text-truncate" style="max-width: 190px;">{{ $lowItem->name }}</div>
                            <div class="text-muted" style="font-size: 0.72rem;">{{ __('messages.barcode') }}: {{ $lowItem->barcode }}</div>
                        </div>
                        <span class="badge bg-danger-subtle text-danger font-monospace">{{ $lowItem->qty }} {{ __('messages.units') }}</span>
                    </a>
                </li>
                @empty
                <li class="p-3 text-center text-muted small">
                    <i class="bi bi-check2-circle text-success fs-4 d-block mb-1"></i>
                    {{ __('messages.adequate_stock') }}
                </li>
                @endforelse
                @if($lowStockCount > 5)
                <li class="text-center py-2 bg-light">
                    <a href="{{ route('inventory.index') }}" class="small text-decoration-none fw-semibold" style="color: var(--primary); font-size: 0.76rem;">
                        {{ __('messages.view_all_low_stock') }} &rarr;
                    </a>
                </li>
                @endif
            </ul>
        </div>

        <!-- Language Selector (English / Khmer / Chinese) -->
        <div class="dropdown">
            <button class="btn btn-sm btn-skincare-outline p-1 px-2 d-flex align-items-center gap-1" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="{{ __('messages.language') }}">
                <i class="bi bi-translate"></i>
                <span class="small fw-semibold text-uppercase" id="currentLangLabel">{{ strtoupper(app()->getLocale()) }}</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm border mt-2" style="border-radius: 10px; min-width: 165px;">
                <li>
                    <a href="{{ route('language.switch', 'en') }}" data-skip-turbo="true" class="dropdown-item py-2 small d-flex align-items-center justify-content-between {{ app()->getLocale() === 'en' ? 'active fw-bold' : '' }}">
                        <span>English (EN)</span>
                        @if(app()->getLocale() === 'en')
                            <i class="bi bi-check2"></i>
                        @endif
                    </a>
                </li>
                <li>
                    <a href="{{ route('language.switch', 'km') }}" data-skip-turbo="true" class="dropdown-item py-2 small d-flex align-items-center justify-content-between {{ app()->getLocale() === 'km' ? 'active fw-bold' : '' }}">
                        <span>ភាសាខ្មែរ (KM)</span>
                        @if(app()->getLocale() === 'km')
                            <i class="bi bi-check2"></i>
                        @endif
                    </a>
                </li>
                <li>
                    <a href="{{ route('language.switch', 'zh') }}" data-skip-turbo="true" class="dropdown-item py-2 small d-flex align-items-center justify-content-between {{ app()->getLocale() === 'zh' ? 'active fw-bold' : '' }}">
                        <span>简体中文 (ZH)</span>
                        @if(app()->getLocale() === 'zh')
                            <i class="bi bi-check2"></i>
                        @endif
                    </a>
                </li>
            </ul>
        </div>

        <!-- User Profile Dropdown -->
        <div class="dropdown">
            <button class="btn btn-sm p-0 d-flex align-items-center gap-2 dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="border: none;">
                <span class="avatar-circle">
                    {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                </span>
                <div class="d-none d-lg-block text-start" style="line-height: 1.1;">
                    <span class="d-block fw-semibold text-dark" style="font-size: 0.82rem;">{{ auth()->user()->name }}</span>
                    <span class="text-muted" style="font-size: 0.7rem;">{{ ucfirst(auth()->user()->role) }}</span>
                </div>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm border mt-2" style="border-radius: 10px; min-width: 180px;">
                <li class="px-3 py-2 border-bottom">
                    <div class="fw-bold small">{{ auth()->user()->name }}</div>
                    <div class="text-muted" style="font-size: 0.72rem;">{{ auth()->user()->email }}</div>
                    <span class="badge-role-pill badge-{{ auth()->user()->role }} mt-1 d-inline-block">{{ auth()->user()->role }}</span>
                </li>
                <li>
                    <a class="dropdown-item py-2 small d-flex align-items-center gap-2" href="{{ route('profile.edit') }}">
                        <i class="bi bi-person-gear"></i>
                        <span>{{ __('messages.profile_password') }}</span>
                    </a>
                </li>
                @if(auth()->user()->isAdmin())
                <li>
                    <a class="dropdown-item py-2 small d-flex align-items-center gap-2" href="{{ route('settings.index') }}">
                        <i class="bi bi-sliders"></i>
                        <span>{{ __('messages.store_settings') }}</span>
                    </a>
                </li>
                @endif
                <li><hr class="dropdown-divider my-1"></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item py-2 small d-flex align-items-center gap-2 text-danger">
                            <i class="bi bi-box-arrow-right"></i>
                            <span>{{ __('messages.sign_out') }}</span>
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>
