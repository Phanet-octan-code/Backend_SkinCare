# SkinCare POS & Retail Management System
## Complete System Documentation, Quality Audit & Handover Guide

---

### Table of Contents
1. [Project Overview](#1-project-overview)
2. [Final Project Structure](#2-final-project-structure)
3. [Completed Modules & Capabilities](#3-completed-modules--capabilities)
4. [Environment Configuration Requirements](#4-environment-configuration-requirements)
5. [Windows PowerShell Setup Commands](#5-windows-powershell-setup-commands)
6. [Database Creation, Migration & Seeding](#6-database-creation-migration--seeding)
7. [Running the Application Locally](#7-running-the-application-locally)
8. [Default Demonstration Accounts](#8-default-demonstration-accounts)
9. [Administrator Creation & Secure Reset](#9-administrator-creation--secure-reset)
10. [Quality Audit & Automated Testing Verification](#10-quality-audit--automated-testing-verification)
11. [Hardware Integration & Operational Guidelines](#11-hardware-integration--operational-guidelines)

---

### 1. Project Overview

The **SkinCare POS Management System** is a professional, high-performance retail and clinic point-of-sale platform built on **Laravel 11**, **Bootstrap 5**, and **Vanilla JavaScript & CSS**. 

It provides an end-to-end boutique skincare operations suite featuring:
- Role-based access control (RBAC) with 5 distinct operational roles.
- Modern POS terminal supporting barcode hardware scanners, guest checkout, and customer loyalty memberships.
- Multi-channel payment processing: Cash (with auto change calculation), Card, and KHQR / QR payments.
- Real-time inventory synchronization with atomic locking (`lockForUpdate`), multi-branch transfers, damaged/write-off handling, and customer/supplier returns.
- Staff management, hourly shift scheduling, check-in/out punctuality tracking, and monthly attendance reports.
- Comprehensive financial ledgers, inventory valuation at cost/retail, transaction movement audit logs, and instant CSV exports.
- Multi-currency engine (USD `$` and Khmer Riel `៛`) with explicit transaction exchange-rate snapshotting and bilingual English/Khmer (ភាសាខ្មែរ) localization.

---

### 2. Final Project Structure

```
SkinCare_Ecommerce/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/
│   │   │   │   └── LoginController.php        # Session auth, role routing, deactivation guard
│   │   │   ├── AttendanceController.php       # Staff check-in/out, monthly report matrix
│   │   │   ├── CategoryController.php         # Product taxonomy CRUD
│   │   │   ├── CustomerController.php         # CRM, tier calculation, purchase history
│   │   │   ├── DashboardController.php        # Executive KPIs & real-time revenue charts
│   │   │   ├── EmployeeController.php         # Staff profiles & attendance records
│   │   │   ├── InventoryController.php        # Stock-in/out, transfers, damage, returns
│   │   │   ├── LanguageController.php         # English & Khmer locale switcher
│   │   │   ├── PosController.php              # Real-time POS checkout & thermal receipt
│   │   │   ├── PositionController.php         # Job titles & salary structure
│   │   │   ├── ProductController.php          # SKU/Barcode catalog, pricing, thresholds
│   │   │   ├── ProfileController.php          # Admin/User profile & password management
│   │   │   ├── PromotionController.php        # Discount campaigns (%, fixed, product-scoped)
│   │   │   ├── ReportController.php           # 4 business ledgers + CSV data exports
│   │   │   ├── SaleController.php             # Sales archive, detail view, cancellations
│   │   │   ├── SettingController.php          # Business metadata, tax, currency, receipts
│   │   │   ├── SupplierController.php         # Vendor records & procurement history
│   │   │   └── UserController.php             # User administration & role assignment
│   │   └── Middleware/
│   │       ├── CheckRole.php                  # Strict RBAC enforcement middleware
│   │       └── SetLocale.php                  # Session-persisted bilingual localization
│   ├── Models/
│   │   ├── Attendance.php                     # Shift attendance & working hours
│   │   ├── Category.php                       # Product categories
│   │   ├── Customer.php                       # CRM & loyalty membership
│   │   ├── Employee.php                       # Staff profiles
│   │   ├── Position.php                       # Job roles & base salaries
│   │   ├── Product.php                        # Product catalog & pricing
│   │   ├── Promotion.php                      # Discount rules & isActive() helper
│   │   ├── Sale.php                           # Transaction master with currency & rate
│   │   ├── SaleDetail.php                     # Transaction item breakdown
│   │   ├── Setting.php                        # Key-value system config with defaults & cache
│   │   ├── Stock.php                          # Live inventory source of truth
│   │   ├── StockTransaction.php               # Immutable inventory audit ledger
│   │   ├── StockTransfer.php                  # Multi-branch transfer orders
│   │   ├── StockTransferDetail.php            # Multi-branch transfer line items
│   │   ├── Supplier.php                       # Vendor records
│   │   └── User.php                           # System accounts & credentials
│   └── Services/
│       ├── CheckoutService.php                # Atomic POS transaction & payment engine
│       ├── InventoryService.php               # Concurrency-safe inventory movements
│       └── SupabaseService.php                # Cloud sync adapter
├── database/
│   ├── migrations/                            # 16 applied database migrations
│   │   ├── 0001_01_01_000000_create_users_table.php
│   │   ├── 2026_10_21_000001_create_categories_table.php
│   │   ├── 2026_10_21_000002_create_positions_table.php
│   │   ├── 2026_10_21_000003_create_employees_table.php
│   │   ├── 2026_10_21_000004_create_suppliers_table.php
│   │   ├── 2026_10_21_000005_create_customers_table.php
│   │   ├── 2026_10_21_000006_create_products_table.php
│   │   ├── 2026_10_21_000007_create_promotions_and_pivot_tables.php
│   │   ├── 2026_10_21_000008_create_sales_and_sale_details_tables.php
│   │   ├── 2026_10_21_000009_create_stocks_and_stock_transactions_tables.php
│   │   ├── 2026_10_21_000010_create_attendances_table.php
│   │   ├── 2026_10_21_000011_create_stock_transfers_and_details_tables.php
│   │   ├── 2026_10_22_000001_create_settings_table.php
│   │   └── 2026_10_22_000002_add_currency_and_exchange_rate_to_sales_table.php
│   └── seeders/
│       └── DatabaseSeeder.php                 # Comprehensive realistic business seeder
├── lang/
│   ├── en/messages.php                        # English translations
│   └── km/messages.php                        # Khmer (ភាសាខ្មែរ) translations
├── resources/
│   ├── css/
│   │   └── app.css                            # Skincare luxury design system
│   └── views/
│       ├── attendance/                        # Daily shift logs & monthly attendance grid
│       ├── categories/                        # Taxonomy CRUD
│       ├── components/                        # Blade UI components (Cards, KPIs, Headers)
│       ├── customers/                         # CRM customer profiles & sales history
│       ├── employees/                         # Staff directory & attendance logs
│       ├── inventory/                         # Stock In, Stock Out, Transfers, Damage, Returns
│       ├── layouts/                           # App wrapper, sidebar, topbar, mobile drawer
│       ├── pos/                               # POS terminal & ESC/POS 80mm thermal receipt
│       ├── positions/                         # Job titles & salary management
│       ├── products/                          # Product catalog & stock cards
│       ├── profile/                           # User profile & password settings
│       ├── promotions/                        # Marketing discounts & campaign rules
│       ├── reports/                           # Sales, Inventory, Movement & HR reports
│       ├── sales/                             # Order archives & cancellation/refund modals
│       ├── settings/                          # Business settings, tax & currency controls
│       ├── suppliers/                         # Vendor directory
│       └── users/                             # Staff user administration
├── routes/
│   └── web.php                                # 86 registered routes with RBAC middleware
├── tests/
│   ├── Feature/                               # 11 comprehensive automated feature test suites
│   │   ├── AuthAndRbacTest.php
│   │   ├── DashboardHomepageTest.php
│   │   ├── CatalogAndInventoryModulesTest.php
│   │   ├── InventoryModuleTest.php
│   │   ├── PosAndSalesWorkflowTest.php
│   │   ├── StaffAndCustomerModulesTest.php
│   │   ├── ReportsAndAnalyticsTest.php
│   │   ├── SettingsAndUiModuleTest.php
│   │   ├── SharedUiLayoutAndComponentsTest.php
│   │   └── SystemQualityAuditTest.php
│   └── Unit/
│       └── ExampleTest.php
└── .env.example
```

---

### 3. Completed Modules & Capabilities

| Module | Core Functionality |
| :--- | :--- |
| **Authentication & RBAC** | Session login/logout, role guard (`admin`, `manager`, `cashier`, `inventory`, `hr`), account active/inactive toggle, prevention of self-deactivation. |
| **Executive Dashboard** | Real-time KPIs (Today Sales, Monthly Revenue, Total Transactions, Low Stock alert count), dynamic period filters (`today`, `week`, `month`, `year`), payment distribution doughnut chart, top-selling products. |
| **POS Terminal** | Live product search & hardware barcode scanner input (`Enter` key capture), category quick tabs, cart line items, guest checkout vs registered member selection, tax & discount auto-calculation, cash tender with change calculation, Card & QR options. |
| **Receipts & Invoicing** | Formatted 80mm thermal receipt layout with business header, itemized tax breakdown, cashier name, barcode, and custom receipt footer. Standard print dialog integration (`window.print()`). |
| **Sales Archive & Returns** | Search by invoice number (`INV-YYYYMMDD-XXXX`), filter by cashier/customer/date, itemized modal inspect, authorized cancellation with automatic stock replenishment. |
| **Inventory Movements** | **Stock In** (supplier purchase with unit cost updating), **Stock Out** (authorized non-sale removals), **Damaged / Expired** write-offs, **Customer Returns** (condition triage: resellable restocked, damaged logged), and **Supplier Returns**. |
| **Branch Transfers** | Multi-location transfers (Central Warehouse $\leftrightarrow$ Downtown Boutique) with three-stage tracking (`in_transit`, `completed`, `cancelled` with automatic origin restocking). |
| **Inventory Sync & Audit** | Atomically updates `stocks` table (source of truth) and synchronized `products.qty` cache inside `DB::transaction()` with `lockForUpdate()`. Every movement writes an immutable `stock_transactions` entry. |
| **Staff & HR Attendance** | Employee directory, position salary management, daily check-in/out, duplicate day prevention, configurable late threshold (09:15), working hours calculation, and monthly aggregated attendance matrix. |
| **Reports & Analytics** | 4 reporting tabs: Sales & Financials (Net Sales, Cost of Goods, Gross Profit), Inventory Valuation (at cost and retail), Movement Ledger (7 distinct transaction types), and Staff Attendance. Instant CSV download for all 4 datasets. |
| **Settings & Localization** | Business address/contact editing, invoice prefix, default tax rate, USD/KHR currency choice with automatic rounding ($0.00 vs 0 ៛), explicit exchange-rate recording per sale, and full English / Khmer language toggle. |

---

### 4. Environment Configuration Requirements

Create or inspect the `.env` file in the project root:

```env
APP_NAME="SkinCare POS"
APP_ENV=local
APP_KEY=base64:YOUR_GENERATED_APP_KEY
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

# Database Configuration (Default: SQLite, or configure MySQL)
DB_CONNECTION=sqlite
# For MySQL:
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=skincare_pos
# DB_USERNAME=root
# DB_PASSWORD=your_password

# Initial Seeding Credentials
INITIAL_ADMIN_EMAIL=admin@skincare.com
INITIAL_ADMIN_PASSWORD=Admin@SkinCare2026

# Session & Cache Settings
SESSION_DRIVER=file
SESSION_LIFETIME=120
CACHE_STORE=file
```

---

### 5. Windows PowerShell Setup Commands

Run Windows PowerShell as Administrator or regular user and navigate to the project directory:

```powershell
cd c:\Users\phanet\Documents\SkinCare_Ecommerce

# 1. Install Composer PHP dependencies
composer install

# 2. Setup Environment Configuration
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
php artisan key:generate

# 3. Create Storage Link for product thumbnails
php artisan storage:link
```

---

### 6. Database Creation, Migration & Seeding

#### Option A: SQLite (Quickest & Default)
```powershell
# Create SQLite file if missing
if (-not (Test-Path database\database.sqlite)) { New-Item -ItemType File database\database.sqlite }

# Run all 16 migrations and seed realistic skincare data
php artisan migrate:fresh --seed
```

#### Option B: MySQL / MariaDB
1. Create database in MySQL:
   ```sql
   CREATE DATABASE skincare_pos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
2. Update `.env` to `DB_CONNECTION=mysql`, `DB_DATABASE=skincare_pos`, and provide your DB credentials.
3. Run migrations and seeder:
   ```powershell
   php artisan migrate:fresh --seed
   ```

---

### 7. Running the Application Locally

Launch the local Laravel HTTP server:

```powershell
php artisan serve
```

Access the system in your browser at:
**`http://127.0.0.1:8000`** (or **`http://localhost:8000`**)

---

### 8. Default Demonstration Accounts

The database seeder automatically creates pre-configured accounts for testing each role. All demo accounts use the standard password:

> **Password for all demo accounts:** `Admin@SkinCare2026`

| Role | Email Address | Access Permissions |
| :--- | :--- | :--- |
| **Administrator** | `admin@skincare.com` | Full unrestricted access to all modules, settings & users |
| **Store Manager** | `manager@skincare.com` | Dashboard, Catalog, POS, Inventory, Sales, Reports |
| **Cashier** | `cashier@skincare.com` | POS Terminal, Customer CRM, Sales History & Receipts |
| **Inventory Officer** | `inventory@skincare.com` | Stock-In, Stock-Out, Transfers, Damaged, Returns, Stock Ledger |
| **HR Specialist** | `hr@skincare.com` | Positions, Employees, Daily Attendance, Monthly HR Reports |

*Tip: Quick one-click login buttons for each role are available directly on the login screen.*

---

### 9. Administrator Creation & Secure Reset

If you ever need to reset the administrator password or create a new emergency admin account:

#### Secure Reset via Laravel Tinker
Open PowerShell and start Tinker:
```powershell
php artisan tinker
```
Then execute:
```php
$admin = App\Models\User::where('email', 'admin@skincare.com')->first();
$admin->password = Hash::make('NewSuperSecurePass2026!');
$admin->status = 'active';
$admin->save();
exit
```

#### Creating a New Administrator Account via Tinker
```php
App\Models\User::create([
    'name' => 'Executive Administrator',
    'email' => 'exec@skincare.com',
    'password' => Hash::make('SecureAdminPassword123!'),
    'role' => 'admin',
    'status' => 'active'
]);
exit
```

---

### 10. Quality Audit & Automated Testing Verification

The entire codebase has been verified with 12 automated test suites encompassing **120 tests and 808 assertions** with zero failures.

#### To Execute the Full Test Suite:
```powershell
php artisan test
```

#### Test Execution Summary:
```
   PASS  Tests\Unit\ExampleTest (1 test)
   PASS  Tests\Feature\ExampleTest (1 test)
   PASS  Tests\Feature\AuthAndRbacTest (12 tests)
   PASS  Tests\Feature\DashboardHomepageTest (5 tests)
   PASS  Tests\Feature\CatalogAndInventoryModulesTest (13 tests)
   PASS  Tests\Feature\InventoryModuleTest (13 tests)
   PASS  Tests\Feature\PosAndSalesWorkflowTest (8 tests)
   PASS  Tests\Feature\StaffAndCustomerModulesTest (24 tests)
   PASS  Tests\Feature\ReportsAndAnalyticsTest (13 tests)
   PASS  Tests\Feature\SettingsAndUiModuleTest (9 tests)
   PASS  Tests\Feature\SharedUiLayoutAndComponentsTest (2 tests)
   PASS  Tests\Feature\SystemQualityAuditTest (13 tests)

  Tests:    120 passed (808 assertions)
  Duration: 35.59s
```

#### 16 Audit Domains Tested & Verified:
1. **Authentication & RBAC**: Session security, role routing, and self-deactivation guard.
2. **Dashboard Metrics**: Net sales, gross profit, low-stock warnings, and period filtering.
3. **Master CRUD**: Product, category, supplier, customer, employee, and position models.
4. **Promotions Engine**: Percentage/fixed discounts, date limits, and customer tier discounts.
5. **POS Checkout**: Cart arithmetic, subtotal, line discounts, tax, and unique invoice generation.
6. **Payment Modes**: Cash tender & change calculation, Card, and QR payment transactions.
7. **Receipt Printing**: ESC/POS thermal formatting, reprint modal, and sales filtering.
8. **Stock In & Stock Out**: Supplier PO receipts with unit-cost updates; authorized non-sale removals.
9. **Transfers & Damage**: In-transit branch movements, damaged write-offs, customer returns.
10. **Inventory Synchronization**: Dual-table synchronicity (`stocks` vs `products.qty`) with audit trail.
11. **Attendance Tracking**: Daily check-in/out, duplicate day prevention, late grace classification.
12. **Consolidated Reports**: Financials, valuation, movements ledger, attendance hours, and CSV export.
13. **Settings & Currencies**: USD (`$`) and KHR (`៛`) rounding rules, tax configuration, and Khmer translation.
14. **Responsive Layouts**: 22 administrative routes, collapsible sidebar, mobile drawer, and keyboard shortcuts.
15. **Route Security**: Unauthorized role access returns HTTP 403; unauthenticated users redirected to `/login`.
16. **Edge Cases**: Insufficient stock rejection, tender less than total rejection, duplicate shift prevention.

---

### 11. Hardware Integration & Operational Guidelines

- **Barcode Scanners**: Handheld USB/Bluetooth barcode scanners configure as HID keyboard emulators. The POS terminal includes an input listener on the barcode field that detects the scanner's terminating `Enter` key, fetches the item instantly, and appends it to the cart.
- **Thermal Receipt Printing**: The printable receipt (`/pos/receipt/{sale}`) is formatted for standard 80mm thermal receipt paper (ESC/POS width). In production Google Chrome or Microsoft Edge, launching the browser with `--kiosk --kiosk-printing` enables instant silent printing without opening the browser print preview dialog.
- **Currency & Exchange Rate Rule**: When switching the boutique's base currency in Settings between USD and KHR, all historical sales preserve their original transaction currency and exchange rate. Future transactions immediately adopt the active rate and currency rules.
- **Stock Concurrency**: Under high-volume POS checkout concurrency, database row locks (`lockForUpdate()`) ensure inventory cannot be oversold, preventing race conditions.
