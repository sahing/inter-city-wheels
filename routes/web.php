<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\DriverBiddingController;
use App\Http\Controllers\Admin\DynamicFormController;
use App\Http\Controllers\Admin\WorkflowConfigController;
use App\Http\Controllers\Admin\BookingAdminController;
use App\Http\Controllers\Admin\UserAdminController;
use App\Http\Controllers\Api\RouteCalculationController;

// Main Portal
Route::get('/', [HomeController::class, 'index'])->name('home');

// Authentication Routes (Role-based)
Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/login/quick-demo', [AuthController::class, 'quickDemoLogin'])->name('login.demo');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/api/auth/user', [AuthController::class, 'current'])->name('api.auth.user');

// Health Check Endpoint
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'framework' => 'Laravel 12 (PHP ' . PHP_VERSION . ')',
        'database' => 'connected',
        'timestamp' => now()->toIso8601String()
    ]);
});

// Platform API Endpoints
Route::prefix('api')->group(function () {
    // Route & Fare Calculation
    Route::get('/routes/calculate', [RouteCalculationController::class, 'calculate'])->name('api.routes.calculate');

    // Bookings
    Route::post('/bookings', [BookingController::class, 'store'])->name('api.bookings.store');
    Route::get('/bookings/{reference}', [BookingController::class, 'show'])->name('api.bookings.show');

    // Driver Bidding & Trip Console
    Route::get('/bids', [DriverBiddingController::class, 'getActiveBids'])->name('api.bids.active');
    Route::post('/bids/counter', [DriverBiddingController::class, 'counterOffer'])->name('api.bids.counter');
    Route::post('/bids/accept', [DriverBiddingController::class, 'acceptBid'])->name('api.bids.accept');
    Route::post('/driver/trips/{id}/verify-otp', [DriverBiddingController::class, 'verifyStartOtp'])->name('api.driver.trips.verifyOtp');
    Route::post('/driver/trips/{id}/complete', [DriverBiddingController::class, 'completeTrip'])->name('api.driver.trips.complete');
    Route::post('/driver/wallet/withdraw', [DriverBiddingController::class, 'withdrawPayout'])->name('api.driver.wallet.withdraw');

    // Booking Management (Admin)
    Route::get('/admin/bookings', [BookingAdminController::class, 'index'])->name('api.admin.bookings.index');
    Route::post('/admin/bookings', [BookingAdminController::class, 'store'])->name('api.admin.bookings.store');
    Route::get('/admin/bookings/{id}', [BookingAdminController::class, 'show'])->name('api.admin.bookings.show');
    Route::put('/admin/bookings/{id}', [BookingAdminController::class, 'update'])->name('api.admin.bookings.update');
    Route::post('/admin/bookings/{id}/allot-driver', [BookingAdminController::class, 'allotDriver'])->name('api.admin.bookings.allotDriver');
    Route::get('/admin/bookings/{id}/invoice', [BookingAdminController::class, 'invoice'])->name('api.admin.bookings.invoice');

    // Users & Drivers Directory (Admin)
    Route::get('/admin/users', [UserAdminController::class, 'index'])->name('api.admin.users.index');
    Route::post('/admin/users', [UserAdminController::class, 'store'])->name('api.admin.users.store');
    Route::get('/admin/users/{id}', [UserAdminController::class, 'show'])->name('api.admin.users.show');
    Route::put('/admin/users/{id}', [UserAdminController::class, 'update'])->name('api.admin.users.update');
    Route::delete('/admin/users/{id}', [UserAdminController::class, 'destroy'])->name('api.admin.users.destroy');

    // Dynamic Form Builder (Admin)
    Route::get('/admin/dynamic-fields', [DynamicFormController::class, 'index'])->name('api.admin.fields.index');
    Route::post('/admin/dynamic-fields', [DynamicFormController::class, 'store'])->name('api.admin.fields.store');
    Route::delete('/admin/dynamic-fields/{id}', [DynamicFormController::class, 'destroy'])->name('api.admin.fields.destroy');

    // Workflow Stages (Admin)
    Route::get('/admin/workflows', [WorkflowConfigController::class, 'index'])->name('api.admin.workflows.index');
    Route::put('/admin/workflows/{id}', [WorkflowConfigController::class, 'update'])->name('api.admin.workflows.update');
});
