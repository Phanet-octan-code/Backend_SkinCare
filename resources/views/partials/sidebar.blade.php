<aside id="sidebar">
    <!-- Brand Identity (No Logo Icon - Clean Editorial Wordmark) -->
    <a href="{{ route('dashboard') }}" class="sidebar-brand">
        <div class="sidebar-brand-text d-flex flex-column justify-content-center">
            <div class="sidebar-brand-title" title="Élixir Botanique">
                ÉLIXIR <span style="color: var(--primary); font-weight: 400;">BOTANIQUE</span>
            </div>
            <div class="sidebar-brand-tagline text-truncate" title="{{ __('messages.app_subtitle') }}">
                {{ __('messages.app_subtitle') }}
            </div>
        </div>
        <div class="sidebar-brand-collapsed font-serif" title="Élixir Botanique">
            É<span style="color: var(--primary); font-weight: 400;">B</span>
        </div>
    </a>

    <!-- Navigation Menu with Collapsible Groups -->
    <div class="sidebar-scrollable-container">
        <ul class="sidebar-menu">
            <!-- 1. Quick Access: Dashboard & POS -->
            <li class="sidebar-menu-item">
                <a href="{{ route('dashboard') }}" 
                   class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}"
                   data-bs-toggle="tooltip" data-bs-placement="right" title="{{ __('messages.dashboard') }}">
                    <i class="bi bi-grid-1x2"></i>
                    <span class="nav-label">{{ __('messages.dashboard') }}</span>
                </a>
            </li>

            @if(auth()->user()->isCashier())
            <li class="sidebar-menu-item">
                <a href="{{ route('pos.index') }}" 
                   class="sidebar-link {{ request()->routeIs('pos.*') ? 'active' : '' }}"
                   data-skip-turbo="true"
                   data-bs-toggle="tooltip" data-bs-placement="right" title="{{ __('messages.pos_terminal') }}">
                    <i class="bi bi-cart3 text-success"></i>
                    <span class="nav-label fw-bold">{{ __('messages.pos_terminal') }}</span>
                    <span class="badge bg-success-subtle text-success ms-auto nav-label" style="font-size: 0.65rem;">{{ __('messages.active') }}</span>
                </a>
            </li>
            @endif

            <!-- 2. Sales & Orders Group -->
            @if(auth()->user()->isCashier())
            <li class="sidebar-menu-item">
                <a href="{{ route('sales.index') }}" 
                   class="sidebar-link {{ request()->routeIs('sales.index') ? 'active' : '' }}"
                   data-bs-toggle="tooltip" data-bs-placement="right" title="{{ __('messages.sales_history') }}">
                    <i class="bi bi-receipt"></i>
                    <span class="nav-label">{{ __('messages.sales_history') }}</span>
                </a>
            </li>
            @endif

            <!-- 3. Catalog Management Group (Admin, Manager) -->
            @if(auth()->user()->isManager())
            <div class="sidebar-section-title">{{ __('messages.catalog_offers') }}</div>
            <li class="sidebar-menu-item">
                <a href="{{ route('products.index') }}" 
                   class="sidebar-link {{ request()->routeIs('products.*') ? 'active' : '' }}"
                   data-bs-toggle="tooltip" data-bs-placement="right" title="{{ __('messages.products') }}">
                    <i class="bi bi-box-seam"></i>
                    <span class="nav-label">{{ __('messages.products') }}</span>
                </a>
            </li>
            <li class="sidebar-menu-item">
                <a href="{{ route('categories.index') }}" 
                   class="sidebar-link {{ request()->routeIs('categories.*') ? 'active' : '' }}"
                   data-bs-toggle="tooltip" data-bs-placement="right" title="{{ __('messages.categories') }}">
                    <i class="bi bi-tags"></i>
                    <span class="nav-label">{{ __('messages.categories') }}</span>
                </a>
            </li>
            <li class="sidebar-menu-item">
                <a href="{{ route('promotions.index') }}" 
                   class="sidebar-link {{ request()->routeIs('promotions.*') ? 'active' : '' }}"
                   data-bs-toggle="tooltip" data-bs-placement="right" title="{{ __('messages.promotions_discounts') }}">
                    <i class="bi bi-percent"></i>
                    <span class="nav-label">{{ __('messages.promotions') }}</span>
                </a>
            </li>
            @endif

            <!-- 4. Inventory Management Group (Collapsible Accordion) -->
            @if(auth()->user()->isInventory())
            <div class="sidebar-section-title">{{ __('messages.inventory_stock') }}</div>
            <li class="sidebar-menu-item">
                <a class="sidebar-link {{ request()->is('inventory*') ? 'active-parent' : '' }}" 
                   data-bs-toggle="collapse" 
                   href="#inventoryMenu" 
                   role="button" 
                   aria-expanded="{{ request()->is('inventory*') ? 'true' : 'false' }}" 
                   aria-controls="inventoryMenu"
                   title="{{ __('messages.inventory_logistics') }}">
                    <i class="bi bi-boxes"></i>
                    <span class="nav-label">{{ __('messages.inventory_logistics') }}</span>
                    <i class="bi bi-chevron-down ms-auto nav-label" style="font-size: 0.75rem;"></i>
                </a>
                <div class="collapse {{ request()->is('inventory*') ? 'show' : '' }} ms-3 ps-2 border-start" id="inventoryMenu">
                    <ul class="sidebar-sub-menu list-unstyled mb-0">
                        <li>
                            <a href="{{ route('inventory.index') }}" class="sidebar-sub-link {{ request()->routeIs('inventory.index') ? 'active' : '' }}">
                                <i class="bi bi-dot"></i> {{ __('messages.stock_overview') }}
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('inventory.stock-in') }}" class="sidebar-sub-link {{ request()->routeIs('inventory.stock-in') ? 'active' : '' }}">
                                <i class="bi bi-box-arrow-in-down"></i> {{ __('messages.stock_in') }}
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('inventory.stock-out') }}" class="sidebar-sub-link {{ request()->routeIs('inventory.stock-out') ? 'active' : '' }}">
                                <i class="bi bi-box-arrow-up-right"></i> {{ __('messages.stock_out') }}
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('inventory.transfers') }}" class="sidebar-sub-link {{ request()->routeIs('inventory.transfers') ? 'active' : '' }}">
                                <i class="bi bi-arrow-left-right"></i> {{ __('messages.stock_transfer') }}
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('inventory.damaged') }}" class="sidebar-sub-link {{ request()->routeIs('inventory.damaged') ? 'active' : '' }}">
                                <i class="bi bi-trash3 text-danger"></i> {{ __('messages.damaged_stock') }}
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('inventory.returns') }}" class="sidebar-sub-link {{ request()->routeIs('inventory.returns') ? 'active' : '' }}">
                                <i class="bi bi-arrow-counterclockwise"></i> {{ __('messages.returns_restock') }}
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('inventory.history') }}" class="sidebar-sub-link {{ request()->routeIs('inventory.history') ? 'active' : '' }}">
                                <i class="bi bi-clock-history"></i> {{ __('messages.movement_history') }}
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="sidebar-menu-item">
                <a href="{{ route('suppliers.index') }}" 
                   class="sidebar-link {{ request()->routeIs('suppliers.*') ? 'active' : '' }}"
                   data-bs-toggle="tooltip" data-bs-placement="right" title="{{ __('messages.suppliers') }}">
                    <i class="bi bi-truck"></i>
                    <span class="nav-label">{{ __('messages.suppliers') }}</span>
                </a>
            </li>
            @endif

            <!-- 5. People & CRM Group -->
            @if(auth()->user()->isCashier())
            <div class="sidebar-section-title">{{ __('messages.relationships') }}</div>
            <li class="sidebar-menu-item">
                <a href="{{ route('customers.index') }}" 
                   class="sidebar-link {{ request()->routeIs('customers.*') ? 'active' : '' }}"
                   data-bs-toggle="tooltip" data-bs-placement="right" title="{{ __('messages.customers_members') }}">
                    <i class="bi bi-person-heart"></i>
                    <span class="nav-label">{{ __('messages.customers_members') }}</span>
                </a>
            </li>
            @endif

            <!-- 6. Human Resources & Attendance Group (Collapsible) -->
            @if(auth()->user()->isHr())
            <div class="sidebar-section-title">{{ __('messages.human_resources') }}</div>
            <li class="sidebar-menu-item">
                <a class="sidebar-link {{ request()->is('employees*') || request()->is('positions*') || request()->is('attendances*') ? 'active-parent' : '' }}" 
                   data-bs-toggle="collapse" 
                   href="#hrMenu" 
                   role="button" 
                   aria-expanded="{{ request()->is('employees*') || request()->is('positions*') || request()->is('attendances*') ? 'true' : 'false' }}" 
                   aria-controls="hrMenu"
                   title="{{ __('messages.staff_payroll') }}">
                    <i class="bi bi-person-badge"></i>
                    <span class="nav-label">{{ __('messages.staff_payroll') }}</span>
                    <i class="bi bi-chevron-down ms-auto nav-label" style="font-size: 0.75rem;"></i>
                </a>
                <div class="collapse {{ request()->is('employees*') || request()->is('positions*') || request()->is('attendances*') ? 'show' : '' }} ms-3 ps-2 border-start" id="hrMenu">
                    <ul class="sidebar-sub-menu list-unstyled mb-0">
                        <li>
                            <a href="{{ route('employees.index') }}" class="sidebar-sub-link {{ request()->routeIs('employees.*') ? 'active' : '' }}">
                                <i class="bi bi-people"></i> {{ __('messages.employees_directory') }}
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('positions.index') }}" class="sidebar-sub-link {{ request()->routeIs('positions.*') ? 'active' : '' }}">
                                <i class="bi bi-briefcase"></i> {{ __('messages.positions_salary') }}
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('attendances.index') }}" class="sidebar-sub-link {{ request()->routeIs('attendances.index') ? 'active' : '' }}">
                                <i class="bi bi-calendar-check"></i> {{ __('messages.attendance_logs') }}
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('attendances.report') }}" class="sidebar-sub-link {{ request()->routeIs('attendances.report') ? 'active' : '' }}">
                                <i class="bi bi-file-earmark-bar-graph"></i> {{ __('messages.monthly_attendance') }}
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
            @endif

            <!-- 7. Analytics & System Admin Group -->
            @if(auth()->user()->isManager())
            <div class="sidebar-section-title">{{ __('messages.analytics_config') }}</div>
            <li class="sidebar-menu-item">
                <a href="{{ route('reports.index') }}" 
                   class="sidebar-link {{ request()->routeIs('reports.*') ? 'active' : '' }}"
                   data-bs-toggle="tooltip" data-bs-placement="right" title="{{ __('messages.reports_analytics') }}">
                    <i class="bi bi-graph-up-arrow"></i>
                    <span class="nav-label">{{ __('messages.reports_analytics') }}</span>
                </a>
            </li>
            @endif

            @if(auth()->user()->isAdmin())
            <li class="sidebar-menu-item">
                <a href="{{ route('users.index') }}" 
                   class="sidebar-link {{ request()->routeIs('users.*') ? 'active' : '' }}"
                   data-bs-toggle="tooltip" data-bs-placement="right" title="{{ __('messages.user_management') }}">
                    <i class="bi bi-shield-lock"></i>
                    <span class="nav-label">{{ __('messages.user_management') }}</span>
                </a>
            </li>
            <li class="sidebar-menu-item">
                <a href="{{ route('settings.index') }}" 
                   class="sidebar-link {{ request()->routeIs('settings.*') ? 'active' : '' }}"
                   data-bs-toggle="tooltip" data-bs-placement="right" title="{{ __('messages.settings') }}">
                    <i class="bi bi-sliders"></i>
                    <span class="nav-label">{{ __('messages.settings') }}</span>
                </a>
            </li>
            @endif
        </ul>
    </div>

    <!-- User Profile & Quick Logout Footer -->
    <div class="sidebar-footer p-3 border-top mt-auto" style="border-color: var(--border-color) !important;">
        <div class="d-flex align-items-center justify-content-between">
            <a href="{{ route('profile.edit') }}" class="d-flex align-items-center gap-2 text-decoration-none text-dark">
                <span class="avatar-circle">
                    {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                </span>
                <div class="sidebar-user-info" style="line-height: 1.2;">
                    <div class="fw-semibold small text-truncate" style="max-width: 110px;">{{ auth()->user()->name }}</div>
                    <span class="badge-role-pill badge-{{ auth()->user()->role }}">
                        {{ auth()->user()->role }}
                    </span>
                </div>
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-link text-muted p-0" title="{{ __('messages.sign_out') }}">
                    <i class="bi bi-box-arrow-right fs-5"></i>
                </button>
            </form>
        </div>
    </div>
</aside>
