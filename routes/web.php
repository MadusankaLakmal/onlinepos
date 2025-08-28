<?php

use App\Http\Controllers\ProfileController;
use App\Models\Tenant;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Test route for permission system
Route::get('/test-permissions', function () {
    if (auth()->check()) {
        $user = auth()->user();
        return response()->json([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ]);
    }
    return response()->json(['message' => 'Not authenticated']);
})->middleware('auth');

// Business Management Routes - Super Admin only
Route::middleware(['auth', 'permission:tenants.manage.all'])->group(function () {
    // Tenants - Only Super Admin can manage all tenants
    Route::resource('tenants', \App\Http\Controllers\TenantController::class);
});

// Tenant-specific routes - Tenant Owners can access their own tenant
Route::middleware(['auth', 'permission:tenant.own.manage'])->group(function () {
    // Tenant Dashboard - shows tenant-specific data
    Route::get('/tenant-dashboard', [\App\Http\Controllers\TenantDashboardController::class, 'index'])
        ->name('tenant.dashboard');
    
    // Tenant Owners can view their own tenant
    Route::get('/my-tenant', function () {
        $user = auth()->user();
        $tenant = Tenant::findOrFail($user->tenant_id);
        return Inertia::render('Tenants/Show', [
            'tenant' => $tenant,
            'canManageAll' => false
        ]);
    })->name('my.tenant');
});

// Store creation routes - only for Super Admins (must come first to avoid conflicts)
Route::middleware(['auth', 'permission:tenants.manage.all'])->group(function () {
    Route::get('/stores/create', [\App\Http\Controllers\StoreController::class, 'create'])->name('stores.create');
    Route::post('/stores', [\App\Http\Controllers\StoreController::class, 'store'])->name('stores.store');
});

// Store management routes - restricted by tenant ownership
Route::middleware(['auth', 'permission:tenant.own.stores.manage'])->group(function () {
    Route::get('/stores', [\App\Http\Controllers\StoreController::class, 'index'])->name('stores.index');
    Route::get('/stores/{store}', [\App\Http\Controllers\StoreController::class, 'show'])->name('stores.show');
    Route::get('/stores/{store}/edit', [\App\Http\Controllers\StoreController::class, 'edit'])->name('stores.edit');
    Route::patch('/stores/{store}', [\App\Http\Controllers\StoreController::class, 'update'])->name('stores.update');
    Route::delete('/stores/{store}', [\App\Http\Controllers\StoreController::class, 'destroy'])->name('stores.destroy');
});

// Staff management - restricted by tenant ownership
Route::middleware(['auth', 'permission:tenant.own.staff.manage'])->group(function () {
    Route::resource('staff-profiles', \App\Http\Controllers\StaffProfileController::class);
});

// Customer management - restricted by tenant ownership
Route::middleware(['auth', 'permission:tenant.own.customers.manage'])->group(function () {
    Route::resource('customers', \App\Http\Controllers\CustomerController::class);
});

// Supplier management - restricted by tenant ownership
Route::middleware(['auth', 'permission:tenant.own.suppliers.manage'])->group(function () {
    Route::resource('suppliers', \App\Http\Controllers\SupplierController::class);
});

// Payroll management - restricted by tenant ownership
Route::middleware(['auth', 'permission:tenant.own.payroll.manage', 'tenant.access:payroll'])->group(function () {
    Route::resource('payrolls', \App\Http\Controllers\PayrollController::class);
});

// Test route for suppliers (temporarily without permission middleware)
Route::get('/test-suppliers', function () {
    return Inertia::render('Suppliers/Index', [
        'suppliers' => \App\Models\Supplier::with('store')->latest()->paginate(10),
        'stores' => \App\Models\Store::all()
    ]);
})->middleware(['auth'])->name('test.suppliers');

// Dashboard with business overview - accessible after login
Route::get('/dashboard', function () {
    $user = auth()->user();
    
    // Get basic stats
    $stats = [
        'stores' => \App\Models\Store::count(),
        'staff' => \App\Models\StaffProfile::count(),
        'customers' => \App\Models\Customer::count(),
        'suppliers' => \App\Models\Supplier::count(),
    ];
    
    // Get recent data for dashboard
    $recentTenants = \App\Models\Tenant::latest()->take(5)->get();
    $recentStores = \App\Models\Store::with('tenant')->latest()->take(5)->get();
    $recentStaff = \App\Models\StaffProfile::with('user')->latest()->take(5)->get();
    
    return Inertia::render('Dashboard', [
        'user' => $user,
        'stats' => $stats,
        'recentTenants' => $recentTenants,
        'recentStores' => $recentStores,
        'recentStaff' => $recentStaff,
        'recentActivities' => [], // Empty for now, can be populated later
        'canManageAll' => $user->hasPermissionTo('tenants.manage.all'),
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__.'/auth.php';