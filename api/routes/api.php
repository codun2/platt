<?php

use App\Http\Controllers\Api\Accounting;
use App\Http\Controllers\Api\App;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Bookings;
use App\Http\Controllers\Api\Education;
use App\Http\Controllers\Api\Hr;
use App\Http\Controllers\Api\Inventory;
use App\Http\Controllers\Api\Manufacturing;
use App\Http\Controllers\Api\Travel;
use App\Http\Controllers\Api\Whatsapp;
use App\Http\Controllers\Api\Pos;
use App\Http\Controllers\Api\Projects;
use App\Http\Controllers\Api\PublicController;
use App\Http\Controllers\Api\Rental;
use App\Http\Controllers\Api\Service;
use App\Http\Controllers\Api\Super;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
*/
Route::middleware('throttle:20,1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::post('auth/register', [AuthController::class, 'register']);
});
Route::get('public/sectors', [PublicController::class, 'sectors']);
Route::get('public/plans', [PublicController::class, 'plans']);

/*
|--------------------------------------------------------------------------
| Any signed-in user
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'tenant'])->prefix('auth')->group(function () {
    Route::get('me', [AuthController::class, 'me']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::patch('profile', [AuthController::class, 'updateProfile']);
});

/*
|--------------------------------------------------------------------------
| Tenant app (scoped by tenant_id)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'tenant-user', 'tenant', 'idem'])->prefix('app')->group(function () {
    Route::get('dashboard', App\DashboardController::class);

    Route::get('company', [App\CompanyController::class, 'show'])->middleware('perm:settings.manage');
    Route::patch('company', [App\CompanyController::class, 'update'])->middleware('perm:settings.manage');

    Route::middleware('perm:users.manage')->group(function () {
        Route::get('users', [App\UserController::class, 'index']);
        Route::post('users', [App\UserController::class, 'store']);
        Route::match(['put', 'patch'], 'users/{id}', [App\UserController::class, 'update'])->whereNumber('id');
        Route::delete('users/{id}', [App\UserController::class, 'destroy'])->whereNumber('id');
    });

    Route::middleware('perm:roles.manage')->group(function () {
        Route::get('roles', [App\RoleController::class, 'index']);
        Route::get('permissions', [App\RoleController::class, 'permissions']);
        Route::post('roles', [App\RoleController::class, 'store']);
        Route::match(['put', 'patch'], 'roles/{id}', [App\RoleController::class, 'update'])->whereNumber('id');
        Route::delete('roles/{id}', [App\RoleController::class, 'destroy'])->whereNumber('id');
    });

    /* ---- CRM: customers (core) ---- */
    Route::middleware('perm:crm_customers.view')->group(function () {
        Route::get('customers', [App\CustomerController::class, 'index']);
        Route::get('customers/{id}', [App\CustomerController::class, 'show'])->whereNumber('id');
        Route::middleware('perm:crm.manage')->group(function () {
            Route::post('customers', [App\CustomerController::class, 'store']);
            Route::match(['put', 'patch'], 'customers/{id}', [App\CustomerController::class, 'update'])->whereNumber('id');
            Route::delete('customers/{id}', [App\CustomerController::class, 'destroy'])->whereNumber('id');
        });
    });

    /* ---- CRM: invoices (core) ---- */
    Route::middleware('perm:crm_invoices.view')->group(function () {
        Route::get('invoices', [App\InvoiceController::class, 'index']);
        Route::get('invoices/{id}', [App\InvoiceController::class, 'show'])->whereNumber('id');
        Route::middleware('perm:crm.manage')->group(function () {
            Route::post('invoices', [App\InvoiceController::class, 'store']);
            Route::match(['put', 'patch'], 'invoices/{id}', [App\InvoiceController::class, 'update'])->whereNumber('id');
            Route::delete('invoices/{id}', [App\InvoiceController::class, 'destroy'])->whereNumber('id');
            Route::post('invoices/{id}/send', [App\InvoiceController::class, 'markSent'])->whereNumber('id');
            Route::post('invoices/{id}/cancel', [App\InvoiceController::class, 'cancel'])->whereNumber('id');
            Route::post('invoices/{id}/duplicate', [App\InvoiceController::class, 'duplicate'])->whereNumber('id');
            Route::post('invoices/{id}/pay', [App\InvoiceController::class, 'pay'])->whereNumber('id');
            Route::delete('invoices/{id}/payments/{paymentId}', [App\InvoiceController::class, 'deletePayment'])->whereNumber('id')->whereNumber('paymentId');
        });
    });

    /* ---- CRM: quotes (core) ---- */
    Route::middleware('perm:crm_quotes.view')->group(function () {
        Route::get('quotes', [App\QuoteController::class, 'index']);
        Route::get('quotes/{id}', [App\QuoteController::class, 'show'])->whereNumber('id');
        Route::middleware('perm:crm.manage')->group(function () {
            Route::post('quotes', [App\QuoteController::class, 'store']);
            Route::match(['put', 'patch'], 'quotes/{id}', [App\QuoteController::class, 'update'])->whereNumber('id');
            Route::delete('quotes/{id}', [App\QuoteController::class, 'destroy'])->whereNumber('id');
            Route::post('quotes/{id}/status', [App\QuoteController::class, 'setStatus'])->whereNumber('id');
            Route::post('quotes/{id}/convert', [App\QuoteController::class, 'convert'])->whereNumber('id');
        });
    });

    /* ---- Accounting (core) ---- */
    Route::prefix('accounting')->group(function () {
        Route::middleware('perm:accounting_accounts.view')->group(function () {
            Route::get('accounts', [Accounting\AccountController::class, 'index']);
            Route::get('accounts/{id}', [Accounting\AccountController::class, 'show'])->whereNumber('id');
            Route::middleware('perm:accounting.manage')->group(function () {
                Route::post('accounts', [Accounting\AccountController::class, 'store']);
                Route::match(['put', 'patch'], 'accounts/{id}', [Accounting\AccountController::class, 'update'])->whereNumber('id');
                Route::delete('accounts/{id}', [Accounting\AccountController::class, 'destroy'])->whereNumber('id');
            });
        });
        Route::middleware('perm:accounting_journal.view')->group(function () {
            Route::get('journal', [Accounting\JournalController::class, 'index']);
            Route::get('journal/{id}', [Accounting\JournalController::class, 'show'])->whereNumber('id');
            Route::middleware('perm:accounting.manage')->group(function () {
                Route::post('journal', [Accounting\JournalController::class, 'store']);
                Route::match(['put', 'patch'], 'journal/{id}', [Accounting\JournalController::class, 'update'])->whereNumber('id');
                Route::delete('journal/{id}', [Accounting\JournalController::class, 'destroy'])->whereNumber('id');
            });
        });
        Route::middleware('perm:accounting_expenses.view')->group(function () {
            Route::get('expenses/meta', [Accounting\ExpenseController::class, 'meta']);
            Route::get('expenses', [Accounting\ExpenseController::class, 'index']);
            Route::get('expenses/{id}', [Accounting\ExpenseController::class, 'show'])->whereNumber('id');
            Route::middleware('perm:accounting.manage')->group(function () {
                Route::post('expenses', [Accounting\ExpenseController::class, 'store']);
                Route::match(['put', 'patch'], 'expenses/{id}', [Accounting\ExpenseController::class, 'update'])->whereNumber('id');
                Route::delete('expenses/{id}', [Accounting\ExpenseController::class, 'destroy'])->whereNumber('id');
            });
        });
        Route::middleware('perm:accounting_reports.view')->group(function () {
            Route::get('summary', [Accounting\ReportController::class, 'summary']);
            Route::get('reports/trial-balance', [Accounting\ReportController::class, 'trialBalance']);
            Route::get('reports/income-statement', [Accounting\ReportController::class, 'incomeStatement']);
            Route::get('reports/balance-sheet', [Accounting\ReportController::class, 'balanceSheet']);
        });
    });

    /* ---- HR (core) ---- */
    Route::prefix('hr')->group(function () {
        Route::middleware('perm:hr_employees.view')->group(function () {
            Route::get('employees', [Hr\EmployeeController::class, 'index']);
            Route::get('employees/{id}', [Hr\EmployeeController::class, 'show'])->whereNumber('id');
            Route::middleware('perm:hr.manage')->group(function () {
                Route::post('employees', [Hr\EmployeeController::class, 'store']);
                Route::match(['put', 'patch'], 'employees/{id}', [Hr\EmployeeController::class, 'update'])->whereNumber('id');
                Route::delete('employees/{id}', [Hr\EmployeeController::class, 'destroy'])->whereNumber('id');
            });
        });
        Route::middleware('perm:hr_attendance.view')->group(function () {
            Route::get('attendance/day', [Hr\AttendanceController::class, 'day']);
            Route::get('attendance/summary', [Hr\AttendanceController::class, 'summary']);
            Route::get('leaves', [Hr\LeaveController::class, 'index']);
            Route::get('leaves/{id}', [Hr\LeaveController::class, 'show'])->whereNumber('id');
            Route::middleware('perm:hr.manage')->group(function () {
                Route::post('attendance/bulk', [Hr\AttendanceController::class, 'bulk']);
                Route::post('leaves', [Hr\LeaveController::class, 'store']);
                Route::match(['put', 'patch'], 'leaves/{id}', [Hr\LeaveController::class, 'update'])->whereNumber('id');
                Route::post('leaves/{id}/status', [Hr\LeaveController::class, 'setStatus'])->whereNumber('id');
                Route::delete('leaves/{id}', [Hr\LeaveController::class, 'destroy'])->whereNumber('id');
            });
        });
        Route::middleware('perm:hr_payroll.view')->group(function () {
            Route::get('payroll', [Hr\PayrollController::class, 'index']);
            Route::get('payroll/{id}', [Hr\PayrollController::class, 'show'])->whereNumber('id');
            Route::middleware('perm:hr.manage')->group(function () {
                Route::post('payroll/generate', [Hr\PayrollController::class, 'generate']);
                Route::patch('payroll/{id}/items/{itemId}', [Hr\PayrollController::class, 'updateItem'])->whereNumber('id')->whereNumber('itemId');
                Route::post('payroll/{id}/approve', [Hr\PayrollController::class, 'approve'])->whereNumber('id');
                Route::post('payroll/{id}/pay', [Hr\PayrollController::class, 'pay'])->whereNumber('id');
                Route::delete('payroll/{id}', [Hr\PayrollController::class, 'destroy'])->whereNumber('id');
            });
        });
    });

    /* ---- Engine: Inventory ---- */
    Route::prefix('inventory')->group(function () {
        Route::get('items/meta', [Inventory\ItemController::class, 'meta']);
        Route::get('items/lookup', [Inventory\ItemController::class, 'lookup']);          // POS + purchase lines
        Route::middleware('perm:inventory_items.view')->group(function () {
            Route::get('items', [Inventory\ItemController::class, 'index']);
            Route::get('items/{id}', [Inventory\ItemController::class, 'show'])->whereNumber('id');
            Route::get('low-stock', [Inventory\StockController::class, 'lowStock']);
            Route::get('stock', [Inventory\StockController::class, 'levels']);
            Route::get('movements', [Inventory\StockController::class, 'movements']);
            Route::get('suppliers/{id}', [Inventory\SupplierController::class, 'show'])->whereNumber('id');
            Route::get('{kind}', [Inventory\SimpleCrudController::class, 'index'])->where('kind', 'categories|units|warehouses|suppliers');
        });
        Route::middleware('perm:inventory.manage')->group(function () {
            Route::post('items', [Inventory\ItemController::class, 'store']);
            Route::match(['put', 'patch'], 'items/{id}', [Inventory\ItemController::class, 'update'])->whereNumber('id');
            Route::delete('items/{id}', [Inventory\ItemController::class, 'destroy'])->whereNumber('id');
            Route::post('stock/adjust', [Inventory\StockController::class, 'adjust']);
            Route::post('stock/transfer', [Inventory\StockController::class, 'transfer']);
            Route::post('{kind}', [Inventory\SimpleCrudController::class, 'store'])->where('kind', 'categories|units|warehouses|suppliers');
            Route::match(['put', 'patch'], '{kind}/{id}', [Inventory\SimpleCrudController::class, 'update'])->where('kind', 'categories|units|warehouses|suppliers')->whereNumber('id');
            Route::delete('{kind}/{id}', [Inventory\SimpleCrudController::class, 'destroy'])->where('kind', 'categories|units|warehouses|suppliers')->whereNumber('id');
        });
        Route::middleware('perm:inventory_purchases.view')->group(function () {
            Route::get('purchases', [Inventory\PurchaseController::class, 'index']);
            Route::get('purchases/{id}', [Inventory\PurchaseController::class, 'show'])->whereNumber('id');
            Route::middleware('perm:inventory.manage')->group(function () {
                Route::post('purchases', [Inventory\PurchaseController::class, 'store']);
                Route::match(['put', 'patch'], 'purchases/{id}', [Inventory\PurchaseController::class, 'update'])->whereNumber('id');
                Route::post('purchases/{id}/receive', [Inventory\PurchaseController::class, 'receive'])->whereNumber('id');
                Route::post('purchases/{id}/cancel', [Inventory\PurchaseController::class, 'cancel'])->whereNumber('id');
                Route::post('purchases/{id}/pay', [Inventory\PurchaseController::class, 'pay'])->whereNumber('id');
            });
        });
    });

    /* ---- Engine: POS ---- */
    Route::prefix('pos')->middleware('perm:pos.view')->group(function () {
        Route::get('sales', [Pos\SaleController::class, 'index']);
        Route::get('sales/{id}', [Pos\SaleController::class, 'show'])->whereNumber('id');
        Route::post('sales', [Pos\SaleController::class, 'store']);
        Route::post('sales/{id}/pay', [Pos\SaleController::class, 'pay'])->whereNumber('id');
        Route::post('sales/{id}/cancel', [Pos\SaleController::class, 'cancel'])->whereNumber('id')->middleware('perm:pos.manage');

        Route::get('shifts', [Pos\ShiftController::class, 'index']);
        Route::get('offline-pack', [Pos\SaleController::class, 'offlinePack']);
        Route::get('shifts/current', [Pos\ShiftController::class, 'current']);
        Route::get('shifts/{id}', [Pos\ShiftController::class, 'show'])->whereNumber('id');
        Route::post('shifts/open', [Pos\ShiftController::class, 'open']);
        Route::post('shifts/{id}/close', [Pos\ShiftController::class, 'close'])->whereNumber('id');

        Route::get('reports/summary', [Pos\ReportController::class, 'summary'])->middleware('perm:pos_reports.view');
    });

    /* ---- Engine: Bookings ---- */
    Route::prefix('bookings')->middleware('perm:bookings.view')->group(function () {
        Route::get('calendar', [Bookings\BookingController::class, 'calendar']);
        Route::get('availability', [Bookings\BookingController::class, 'availability']);
        Route::get('list', [Bookings\BookingController::class, 'index']);
        Route::get('list/{id}', [Bookings\BookingController::class, 'show'])->whereNumber('id');
        Route::post('list', [Bookings\BookingController::class, 'store']);
        Route::match(['put', 'patch'], 'list/{id}', [Bookings\BookingController::class, 'update'])->whereNumber('id');
        Route::post('list/{id}/status', [Bookings\BookingController::class, 'setStatus'])->whereNumber('id');
        Route::post('list/{id}/pay', [Bookings\BookingController::class, 'pay'])->whereNumber('id');

        Route::get('{kind}', [Bookings\CatalogController::class, 'index'])->where('kind', 'units|services');
        Route::middleware('perm:bookings.manage')->group(function () {
            Route::post('list/{id}/checkin', [Bookings\BookingController::class, 'checkin'])->whereNumber('id');
            Route::post('{kind}', [Bookings\CatalogController::class, 'store'])->where('kind', 'units|services');
            Route::match(['put', 'patch'], '{kind}/{id}', [Bookings\CatalogController::class, 'update'])->where('kind', 'units|services')->whereNumber('id');
            Route::delete('{kind}/{id}', [Bookings\CatalogController::class, 'destroy'])->where('kind', 'units|services')->whereNumber('id');
        });
    });

    /* ---- WhatsApp messages (click-to-chat; no Business API needed) ---- */
    Route::prefix('whatsapp')->middleware('perm:whatsapp.view')->group(function () {
        Route::get('audiences', [Whatsapp\WhatsappController::class, 'audiences']);
        Route::get('templates', [Whatsapp\WhatsappController::class, 'templates']);
        Route::get('recipients', [Whatsapp\WhatsappController::class, 'recipients']);
        Route::get('history', [Whatsapp\WhatsappController::class, 'history']);
        Route::post('log', [Whatsapp\WhatsappController::class, 'log']);
        Route::post('templates', [Whatsapp\WhatsappController::class, 'storeTemplate']);
        Route::match(['put', 'patch'], 'templates/{id}', [Whatsapp\WhatsappController::class, 'updateTemplate'])->whereNumber('id');
        Route::delete('templates/{id}', [Whatsapp\WhatsappController::class, 'destroyTemplate'])->whereNumber('id');
    });

    /* ---- Engine: Manufacturing (recipes / production) ---- */
    Route::prefix('manufacturing')->middleware('perm:manufacturing.view')->group(function () {
        Route::get('summary', [Manufacturing\OrderController::class, 'summary']);
        Route::get('bom', [Manufacturing\BomController::class, 'index']);
        Route::get('bom/{id}', [Manufacturing\BomController::class, 'show'])->whereNumber('id');
        Route::get('orders', [Manufacturing\OrderController::class, 'index']);
        Route::get('orders/{id}', [Manufacturing\OrderController::class, 'show'])->whereNumber('id');
        Route::post('orders', [Manufacturing\OrderController::class, 'store']);
        Route::post('orders/{id}/start', [Manufacturing\OrderController::class, 'start'])->whereNumber('id');
        Route::post('orders/{id}/complete', [Manufacturing\OrderController::class, 'complete'])->whereNumber('id');
        Route::post('orders/{id}/cancel', [Manufacturing\OrderController::class, 'cancel'])->whereNumber('id');
        Route::middleware('perm:manufacturing.manage')->group(function () {
            Route::post('bom', [Manufacturing\BomController::class, 'store']);
            Route::match(['put', 'patch'], 'bom/{id}', [Manufacturing\BomController::class, 'update'])->whereNumber('id');
            Route::delete('bom/{id}', [Manufacturing\BomController::class, 'destroy'])->whereNumber('id');
        });
    });

    /* ---- Engine: Education (schools) ---- */
    Route::prefix('education')->middleware('perm:education.view')->group(function () {
        Route::get('summary', [Education\DashboardController::class, 'summary']);
        Route::get('classes', [Education\ClassController::class, 'index']);
        Route::get('teachers', [Education\ClassController::class, 'teachers']);
        Route::get('students', [Education\StudentController::class, 'index']);
        Route::get('students/{id}', [Education\StudentController::class, 'show'])->whereNumber('id');
        Route::get('attendance', [Education\AttendanceController::class, 'sheet']);
        Route::post('attendance', [Education\AttendanceController::class, 'save']);
        Route::get('attendance/report', [Education\AttendanceController::class, 'report']);
        Route::get('fees', [Education\FeeController::class, 'summary']);
        Route::middleware('perm:education.manage')->group(function () {
            Route::post('classes', [Education\ClassController::class, 'store']);
            Route::match(['put', 'patch'], 'classes/{id}', [Education\ClassController::class, 'update'])->whereNumber('id');
            Route::delete('classes/{id}', [Education\ClassController::class, 'destroy'])->whereNumber('id');
            Route::post('students', [Education\StudentController::class, 'store']);
            Route::match(['put', 'patch'], 'students/{id}', [Education\StudentController::class, 'update'])->whereNumber('id');
            Route::delete('students/{id}', [Education\StudentController::class, 'destroy'])->whereNumber('id');
            Route::post('fees/generate', [Education\FeeController::class, 'generate']);
        });
    });

    /* ---- Engine: Service (garages / laundries / maintenance / tailoring) ---- */
    Route::prefix('service')->middleware('perm:service.view')->group(function () {
        Route::get('summary', [Service\OrderController::class, 'summary']);
        Route::get('orders', [Service\OrderController::class, 'index']);
        Route::get('orders/{id}', [Service\OrderController::class, 'show'])->whereNumber('id');
        Route::post('orders', [Service\OrderController::class, 'store']);
        Route::match(['put', 'patch'], 'orders/{id}', [Service\OrderController::class, 'update'])->whereNumber('id');
        Route::post('orders/{id}/status', [Service\OrderController::class, 'status'])->whereNumber('id');
        Route::post('orders/{id}/deliver', [Service\OrderController::class, 'deliver'])->whereNumber('id');
        Route::get('{kind}', [Service\CatalogController::class, 'index'])->where('kind', 'technicians|catalog');
        Route::middleware('perm:service.manage')->group(function () {
            Route::delete('orders/{id}', [Service\OrderController::class, 'destroy'])->whereNumber('id');
            Route::post('{kind}', [Service\CatalogController::class, 'store'])->where('kind', 'technicians|catalog');
            Route::match(['put', 'patch'], '{kind}/{id}', [Service\CatalogController::class, 'update'])->where('kind', 'technicians|catalog')->whereNumber('id');
            Route::delete('{kind}/{id}', [Service\CatalogController::class, 'destroy'])->where('kind', 'technicians|catalog')->whereNumber('id');
        });
    });

    /* ---- Engine: Projects (legal cases / engineering projects) ---- */
    Route::prefix('projects')->middleware('perm:projects.view')->group(function () {
        Route::get('summary', [Projects\ProjectController::class, 'summary']);
        Route::get('people', [Projects\ProjectController::class, 'people']);
        Route::get('items', [Projects\ProjectController::class, 'index']);
        Route::get('items/{id}', [Projects\ProjectController::class, 'show'])->whereNumber('id');
        Route::get('tasks', [Projects\TaskController::class, 'index']);
        Route::get('timesheets', [Projects\TimesheetController::class, 'index']);
        // Day-to-day work any team member does.
        Route::post('tasks', [Projects\TaskController::class, 'store']);
        Route::match(['put', 'patch'], 'tasks/{id}', [Projects\TaskController::class, 'update'])->whereNumber('id');
        Route::post('tasks/{id}/complete', [Projects\TaskController::class, 'complete'])->whereNumber('id');
        Route::post('timesheets', [Projects\TimesheetController::class, 'store']);
        Route::match(['put', 'patch'], 'timesheets/{id}', [Projects\TimesheetController::class, 'update'])->whereNumber('id');
        Route::delete('timesheets/{id}', [Projects\TimesheetController::class, 'destroy'])->whereNumber('id');
        Route::middleware('perm:projects.manage')->group(function () {
            Route::post('items', [Projects\ProjectController::class, 'store']);
            Route::match(['put', 'patch'], 'items/{id}', [Projects\ProjectController::class, 'update'])->whereNumber('id');
            Route::delete('items/{id}', [Projects\ProjectController::class, 'destroy'])->whereNumber('id');
            Route::post('items/{id}/bill', [Projects\ProjectController::class, 'bill'])->whereNumber('id');
            Route::delete('tasks/{id}', [Projects\TaskController::class, 'destroy'])->whereNumber('id');
        });
    });

    /* ---- Engine: Rental (real estate / car rental / installment sales) ---- */
    Route::prefix('rental')->middleware('perm:rental.view')->group(function () {
        Route::get('summary', [Rental\DashboardController::class, 'summary']);
        Route::get('installments', [Rental\DashboardController::class, 'installments']);
        Route::get('assets', [Rental\AssetController::class, 'index']);
        Route::get('assets/{id}', [Rental\AssetController::class, 'show'])->whereNumber('id');
        Route::get('owners', [Rental\OwnerController::class, 'index']);
        Route::get('owners/{id}', [Rental\OwnerController::class, 'show'])->whereNumber('id');
        Route::get('contracts', [Rental\ContractController::class, 'index']);
        Route::get('contracts/{id}', [Rental\ContractController::class, 'show'])->whereNumber('id');
        Route::post('contracts/preview', [Rental\ContractController::class, 'preview']);
        Route::post('contracts/{id}/collect', [Rental\ContractController::class, 'collect'])->whereNumber('id');
        Route::middleware('perm:rental.manage')->group(function () {
            Route::post('assets', [Rental\AssetController::class, 'store']);
            Route::match(['put', 'patch'], 'assets/{id}', [Rental\AssetController::class, 'update'])->whereNumber('id');
            Route::delete('assets/{id}', [Rental\AssetController::class, 'destroy'])->whereNumber('id');
            Route::post('owners', [Rental\OwnerController::class, 'store']);
            Route::match(['put', 'patch'], 'owners/{id}', [Rental\OwnerController::class, 'update'])->whereNumber('id');
            Route::delete('owners/{id}', [Rental\OwnerController::class, 'destroy'])->whereNumber('id');
            Route::post('contracts', [Rental\ContractController::class, 'store']);
            Route::match(['put', 'patch'], 'contracts/{id}', [Rental\ContractController::class, 'update'])->whereNumber('id');
            Route::delete('contracts/{id}', [Rental\ContractController::class, 'destroy'])->whereNumber('id');
            Route::post('contracts/{id}/deposit', [Rental\ContractController::class, 'deposit'])->whereNumber('id');
            Route::post('contracts/{id}/close', [Rental\ContractController::class, 'close'])->whereNumber('id');
        });
    });

    /* ---- Engine: Travel (hajj / umrah / tourism) ---- */
    Route::prefix('travel')->middleware('perm:travel.view')->group(function () {
        Route::get('trips', [Travel\TripController::class, 'index']);
        Route::get('trips/{id}', [Travel\TripController::class, 'show'])->whereNumber('id');
        Route::get('packages', [Travel\TripController::class, 'packages']);
        Route::get('travelers', [Travel\TravelerController::class, 'index']);
        Route::get('travelers/{id}', [Travel\TravelerController::class, 'show'])->whereNumber('id');
        Route::post('travelers', [Travel\TravelerController::class, 'store']);
        Route::match(['put', 'patch'], 'travelers/{id}', [Travel\TravelerController::class, 'update'])->whereNumber('id');
        Route::post('travelers/{id}/pay', [Travel\TravelerController::class, 'pay'])->whereNumber('id');
        Route::middleware('perm:travel.manage')->group(function () {
            Route::post('trips', [Travel\TripController::class, 'store']);
            Route::match(['put', 'patch'], 'trips/{id}', [Travel\TripController::class, 'update'])->whereNumber('id');
            Route::delete('trips/{id}', [Travel\TripController::class, 'destroy'])->whereNumber('id');
            Route::post('trips/{id}/expenses', [Travel\TripController::class, 'addExpense'])->whereNumber('id');
            Route::delete('trips/{id}/expenses/{expenseId}', [Travel\TripController::class, 'deleteExpense'])->whereNumber('id')->whereNumber('expenseId');
            Route::delete('travelers/{id}', [Travel\TravelerController::class, 'destroy'])->whereNumber('id');
            Route::post('packages', [Travel\TripController::class, 'storePackage']);
            Route::match(['put', 'patch'], 'packages/{id}', [Travel\TripController::class, 'updatePackage'])->whereNumber('id');
            Route::delete('packages/{id}', [Travel\TripController::class, 'destroyPackage'])->whereNumber('id');
        });
    });
});

/*
|--------------------------------------------------------------------------
| Platform super-admin
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'super'])->prefix('super')->group(function () {
    Route::get('stats', Super\StatsController::class);

    Route::get('tenants', [Super\TenantController::class, 'index']);
    Route::post('tenants', [Super\TenantController::class, 'store']);
    Route::get('tenants/{id}', [Super\TenantController::class, 'show'])->whereNumber('id');
    Route::match(['put', 'patch'], 'tenants/{id}', [Super\TenantController::class, 'update'])->whereNumber('id');
    Route::post('tenants/{id}/modules', [Super\TenantController::class, 'toggleModule'])->whereNumber('id');
    Route::post('tenants/{id}/apply-preset', [Super\TenantController::class, 'applyPreset'])->whereNumber('id');
    Route::post('tenants/{id}/renew', [Super\TenantController::class, 'renew'])->whereNumber('id');
    Route::post('tenants/{id}/reset-owner-password', [Super\TenantController::class, 'resetOwnerPassword'])->whereNumber('id');

    Route::get('modules', [Super\ModuleController::class, 'index']);
    Route::post('modules', [Super\ModuleController::class, 'store']);
    Route::match(['put', 'patch'], 'modules/{id}', [Super\ModuleController::class, 'update'])->whereNumber('id');
    Route::delete('modules/{id}', [Super\ModuleController::class, 'destroy'])->whereNumber('id');

    Route::get('presets', [Super\PresetController::class, 'index']);
    Route::post('presets', [Super\PresetController::class, 'store']);
    Route::match(['put', 'patch'], 'presets/{id}', [Super\PresetController::class, 'update'])->whereNumber('id');
    Route::delete('presets/{id}', [Super\PresetController::class, 'destroy'])->whereNumber('id');

    Route::get('plans', [Super\PlanController::class, 'index']);
    Route::post('plans', [Super\PlanController::class, 'store']);
    Route::match(['put', 'patch'], 'plans/{id}', [Super\PlanController::class, 'update'])->whereNumber('id');
    Route::delete('plans/{id}', [Super\PlanController::class, 'destroy'])->whereNumber('id');

    Route::get('subscriptions', [Super\SubscriptionController::class, 'index']);
    Route::get('subscriptions/history', [Super\SubscriptionController::class, 'history']);
    Route::post('subscriptions/expire-overdue', [Super\SubscriptionController::class, 'expireOverdue']);
});
