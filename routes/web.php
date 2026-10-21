<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Guest Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

// Authenticated & Active Account Routes
Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('/', function () {
        return redirect()->route('dashboard');
    });

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Profile & Password Management
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'updateProfile'])->name('profile.update');
    Route::put('/password', [ProfileController::class, 'updatePassword'])->name('password.update');

    // POS Terminal & Sales Checkout (Admin, Manager, Cashier)
    Route::middleware('role:admin,manager,cashier')->group(function () {
        Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
        Route::post('/pos/checkout', [PosController::class, 'checkout'])->name('pos.checkout');
        Route::get('/pos/receipt/{sale}', [PosController::class, 'receipt'])->name('pos.receipt');

        Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
        Route::get('/sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
        Route::post('/sales/{sale}/cancel', [SaleController::class, 'cancel'])->name('sales.cancel');
        Route::post('/sales/{sale}/return', [SaleController::class, 'processReturn'])->name('sales.return');

        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
        Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::patch('/customers/{customer}/toggle-status', [CustomerController::class, 'toggleStatus'])->name('customers.toggle-status');
        Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');
    });

    // Products, Categories & Promotions (Admin, Manager)
    Route::middleware('role:admin,manager')->group(function () {
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::post('/products', [ProductController::class, 'store'])->name('products.store');
        Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');
        Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::patch('/products/{product}/toggle-status', [ProductController::class, 'toggleStatus'])->name('products.toggle-status');
        Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');

        Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::patch('/categories/{category}/toggle-status', [CategoryController::class, 'toggleStatus'])->name('categories.toggle-status');
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

        Route::get('/promotions', [PromotionController::class, 'index'])->name('promotions.index');
        Route::post('/promotions', [PromotionController::class, 'store'])->name('promotions.store');
        Route::put('/promotions/{promotion}', [PromotionController::class, 'update'])->name('promotions.update');
        Route::patch('/promotions/{promotion}/toggle-status', [PromotionController::class, 'toggleStatus'])->name('promotions.toggle-status');
        Route::delete('/promotions/{promotion}', [PromotionController::class, 'destroy'])->name('promotions.destroy');
    });

    // Inventory Management & Movements (Admin, Manager, Inventory)
    Route::middleware('role:admin,manager,inventory')->group(function () {
        Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::post('/inventory/update-threshold/{product}', [InventoryController::class, 'updateThreshold'])->name('inventory.update-threshold');
        Route::get('/inventory/history', [InventoryController::class, 'history'])->name('inventory.history');

        Route::get('/inventory/stock-in', [InventoryController::class, 'stockIn'])->name('inventory.stock-in');
        Route::post('/inventory/stock-in', [InventoryController::class, 'processStockIn'])->name('inventory.process-stock-in');

        Route::get('/inventory/stock-out', [InventoryController::class, 'stockOut'])->name('inventory.stock-out');
        Route::post('/inventory/stock-out', [InventoryController::class, 'processStockOut'])->name('inventory.process-stock-out');

        Route::get('/inventory/transfers', [InventoryController::class, 'transfers'])->name('inventory.transfers');
        Route::post('/inventory/transfers', [InventoryController::class, 'processTransfer'])->name('inventory.process-transfer');
        Route::patch('/inventory/transfers/{transfer}/status', [InventoryController::class, 'updateTransferStatus'])->name('inventory.update-transfer-status');

        Route::get('/inventory/damaged', [InventoryController::class, 'damaged'])->name('inventory.damaged');
        Route::post('/inventory/damaged', [InventoryController::class, 'processDamaged'])->name('inventory.process-damaged');

        Route::get('/inventory/returns', [InventoryController::class, 'returns'])->name('inventory.returns');
        Route::post('/inventory/returns/customer', [InventoryController::class, 'processCustomerReturn'])->name('inventory.process-customer-return');
        Route::post('/inventory/returns/supplier', [InventoryController::class, 'processSupplierReturn'])->name('inventory.process-supplier-return');

        Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
        Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
        Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
        Route::patch('/suppliers/{supplier}/toggle-status', [SupplierController::class, 'toggleStatus'])->name('suppliers.toggle-status');
        Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');
    });

    // HR, Employees & Attendance (Admin, Manager, HR)
    Route::middleware('role:admin,manager,hr')->group(function () {
        // Positions
        Route::get('/positions', [PositionController::class, 'index'])->name('positions.index');
        Route::post('/positions', [PositionController::class, 'store'])->name('positions.store');
        Route::put('/positions/{position}', [PositionController::class, 'update'])->name('positions.update');
        Route::delete('/positions/{position}', [PositionController::class, 'destroy'])->name('positions.destroy');

        // Employees
        Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
        Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
        Route::get('/employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
        Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
        Route::patch('/employees/{employee}/toggle-status', [EmployeeController::class, 'toggleStatus'])->name('employees.toggle-status');
        Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy');

        // Attendances & Reports
        Route::get('/attendances', [AttendanceController::class, 'index'])->name('attendances.index');
        Route::get('/attendances/report', [AttendanceController::class, 'monthlyReport'])->name('attendances.report');
        Route::post('/attendances', [AttendanceController::class, 'store'])->name('attendances.store');
        Route::patch('/attendances/{attendance}/checkout', [AttendanceController::class, 'checkOut'])->name('attendances.checkout');
        Route::put('/attendances/{attendance}', [AttendanceController::class, 'update'])->name('attendances.update');
        Route::delete('/attendances/{attendance}', [AttendanceController::class, 'destroy'])->name('attendances.destroy');
    });

    // Business Reports & Financial Analytics (Admin, Manager)
    Route::middleware('role:admin,manager')->group(function () {
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
    });

    // Administrator Only (User Administration & System Settings)
    Route::middleware('role:admin')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::patch('/users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle-status');

        Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
        Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');
    });

    // Language Switcher (English / Khmer)
    Route::get('/language/{locale}', [\App\Http\Controllers\LanguageController::class, 'switch'])->name('language.switch');
});
