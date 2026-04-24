<?php

use App\Http\Controllers\Home\DashboardController;
use App\Http\Controllers\PurchaseOrders\PurchaseOrderController;
use App\Http\Controllers\Sales\ExpenseController;
use App\Http\Controllers\Sales\SaleController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Sublimations\SublimationController;
use App\Http\Controllers\Sublimations\SublimationImageController;
use App\Http\Controllers\Sublimations\SublimationTagController;
use App\Http\Controllers\Settings\TagController;
use App\Http\Controllers\Users\BranchController;
use App\Http\Controllers\Users\CustomerController;
use App\Http\Controllers\Users\EndorsementController;
use App\Http\Controllers\Users\UserController;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;


Route::get('/add-user', function () {
    User::updateOrCreate(
        ['username' => 'superadmin'],
        [
            'first_name' => 'Jacob',
            'last_name' => 'Elemento',
            'password' => Hash::make('password'),
            'role' => 'superadmin',
            'branch_id' => null, // Super admins usually aren't tied to a branch
        ]
    );

    Artisan::call('db:seed', ['--class' => 'BranchSeeder']);
});
Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');

    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');

    // users
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

    Route::resource('branches', BranchController::class);
    Route::resource('tags', TagController::class);

    Route::get('/sublimation-tags', [SublimationTagController::class, 'index']);
    Route::patch('/sublimation/{sublimation}/update-status', [SublimationController::class, 'updateStatus'])->name('sublimations.update-status');
    Route::patch('/sublimation/{sublimation}/update-staff', [SublimationController::class, 'updateStaff'])->name('sublimations.update-staff');
    Route::patch('/sublimation/{sublimation}/update-duedate', [SublimationController::class, 'updateDueDate'])->name('sublimations.update-duedate');
    Route::post('/sublimations/{sublimation}/tags', [SublimationTagController::class, 'addTag'])->name('sublimations.tags.add');
    Route::delete('/sublimations/{sublimation}/tags/{tag}', [SublimationTagController::class, 'removeTag'])->name('sublimations.tags.remove');

    Route::get('/sublimations/{sublimation}/images', [SublimationImageController::class, 'index'])->name('sublimations.images.index');
    Route::post('/sublimations/{sublimation}/images', [SublimationImageController::class, 'store'])->name('sublimations.images.store');
    Route::delete('/sublimations/{sublimation}/images/{image}', [SublimationImageController::class, 'destroy'])->name('sublimations.images.destroy');

    Route::resource('sublimations', SublimationController::class);
    Route::resource('customers', CustomerController::class);

    Route::patch('sales/payment/{transaction}', [SaleController::class, 'updatePayment'])->name('sales.update-payment');
    Route::resource('sales', SaleController::class)
        ->only(['index', 'store', 'update']);
    Route::resource('purchase-orders', PurchaseOrderController::class);
    Route::resource('endorsements', EndorsementController::class);
    Route::patch('/expenses/{expense}/void', [ExpenseController::class, 'void'])->name('expenses.void');
    Route::resource('expenses', ExpenseController::class);

    Route::prefix('api')->group(function () {
        Route::get('/customers', [CustomerController::class, 'indexApiList'])->name('api.customers.index');
    });
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');
});
