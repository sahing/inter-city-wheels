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
use App\Http\Controllers\Admin\LocationAdminController;
use App\Http\Controllers\Admin\BerthRouteAdminController;
use App\Http\Controllers\Admin\SeatLayoutAdminController;
use App\Http\Controllers\Admin\VehicleAdminController;
use App\Http\Controllers\Admin\CabRouteAdminController;
use App\Http\Controllers\Admin\LocalPackageAdminController;
use App\Http\Controllers\Admin\AirportHubAdminController;
use App\Http\Controllers\Admin\PlatformSettingAdminController;
use App\Http\Controllers\Api\RouteCalculationController;

// Main Portal
Route::get('/', [HomeController::class, 'index'])->name('home');

// Authentication Routes (Role-based)
Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/login/quick-demo', [AuthController::class, 'quickDemoLogin'])->name('login.demo');
Route::match(['get', 'post'], '/logout', [AuthController::class, 'logout'])->name('logout');
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
    Route::get('/local-packages', function () {
        return response()->json([
            'success' => true,
            'packages' => \App\Models\LocalPackage::where('is_active', true)->orderBy('duration_hours')->get()
        ]);
    })->name('api.localPackages.active');
    Route::get('/airport-hubs', function () {
        return response()->json([
            'success' => true,
            'airports' => \App\Models\AirportHub::where('is_active', true)->orderBy('city')->get()
        ]);
    })->name('api.airportHubs.active');

    // Bookings
    Route::post('/bookings', [BookingController::class, 'store'])->name('api.bookings.store');
    Route::get('/bookings/{reference}', [BookingController::class, 'show'])->name('api.bookings.show');

    // Driver Bidding & Trip Console
    Route::get('/bids', [DriverBiddingController::class, 'getActiveBids'])->name('api.bids.active');
    Route::post('/bids/counter', [DriverBiddingController::class, 'counterOffer'])->name('api.bids.counter');
    Route::post('/bids/submit', [DriverBiddingController::class, 'submitDriverBid'])->name('api.bids.submit');
    Route::post('/bids/accept', [DriverBiddingController::class, 'acceptBid'])->name('api.bids.accept');
    Route::post('/driver/trips/{id}/verify-otp', [DriverBiddingController::class, 'verifyStartOtp'])->name('api.driver.trips.verifyOtp');
    Route::post('/driver/trips/{id}/complete', [DriverBiddingController::class, 'completeTrip'])->name('api.driver.trips.complete');
    Route::post('/driver/wallet/withdraw', [DriverBiddingController::class, 'withdrawPayout'])->name('api.driver.wallet.withdraw');
    Route::get('/driver/profile', [DriverBiddingController::class, 'getProfile'])->name('api.driver.profile.get');
    Route::post('/driver/profile', [DriverBiddingController::class, 'updateProfile'])->name('api.driver.profile.update');
    Route::post('/driver/toggle-online', [DriverBiddingController::class, 'toggleOnlineStatus'])->name('api.driver.toggleOnline');

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

    // Outstation Cab Routes Setup (Admin)
    Route::get('/admin/cab-routes', [CabRouteAdminController::class, 'index'])->name('api.admin.cabRoutes.index');
    Route::post('/admin/cab-routes', [CabRouteAdminController::class, 'store'])->name('api.admin.cabRoutes.store');
    Route::get('/admin/cab-routes/{id}', [CabRouteAdminController::class, 'show'])->name('api.admin.cabRoutes.show');
    Route::put('/admin/cab-routes/{id}', [CabRouteAdminController::class, 'update'])->name('api.admin.cabRoutes.update');
    Route::post('/admin/cab-routes/{id}/toggle', [CabRouteAdminController::class, 'toggleStatus'])->name('api.admin.cabRoutes.toggle');
    Route::delete('/admin/cab-routes/{id}', [CabRouteAdminController::class, 'destroy'])->name('api.admin.cabRoutes.destroy');

    // Local Rental Packages Setup (Admin)
    Route::get('/admin/local-packages', [LocalPackageAdminController::class, 'index'])->name('api.admin.localPackages.index');
    Route::post('/admin/local-packages', [LocalPackageAdminController::class, 'store'])->name('api.admin.localPackages.store');
    Route::get('/admin/local-packages/{id}', [LocalPackageAdminController::class, 'show'])->name('api.admin.localPackages.show');
    Route::put('/admin/local-packages/{id}', [LocalPackageAdminController::class, 'update'])->name('api.admin.localPackages.update');
    Route::post('/admin/local-packages/{id}/toggle', [LocalPackageAdminController::class, 'toggleStatus'])->name('api.admin.localPackages.toggle');
    Route::delete('/admin/local-packages/{id}', [LocalPackageAdminController::class, 'destroy'])->name('api.admin.localPackages.destroy');

    // Airport Transit Hubs & Surcharges Setup (Admin)
    Route::get('/admin/airport-hubs', [AirportHubAdminController::class, 'index'])->name('api.admin.airportHubs.index');
    Route::post('/admin/airport-hubs', [AirportHubAdminController::class, 'store'])->name('api.admin.airportHubs.store');
    Route::get('/admin/airport-hubs/{id}', [AirportHubAdminController::class, 'show'])->name('api.admin.airportHubs.show');
    Route::put('/admin/airport-hubs/{id}', [AirportHubAdminController::class, 'update'])->name('api.admin.airportHubs.update');
    Route::post('/admin/airport-hubs/{id}/toggle', [AirportHubAdminController::class, 'toggleStatus'])->name('api.admin.airportHubs.toggle');
    Route::delete('/admin/airport-hubs/{id}', [AirportHubAdminController::class, 'destroy'])->name('api.admin.airportHubs.destroy');

    // Service Locations Setup (Admin)
    Route::get('/admin/locations', [LocationAdminController::class, 'index'])->name('api.admin.locations.index');
    Route::post('/admin/locations', [LocationAdminController::class, 'store'])->name('api.admin.locations.store');
    Route::get('/admin/locations/{id}', [LocationAdminController::class, 'show'])->name('api.admin.locations.show');
    Route::put('/admin/locations/{id}', [LocationAdminController::class, 'update'])->name('api.admin.locations.update');
    Route::post('/admin/locations/{id}/toggle', [LocationAdminController::class, 'toggleStatus'])->name('api.admin.locations.toggle');
    Route::delete('/admin/locations/{id}', [LocationAdminController::class, 'destroy'])->name('api.admin.locations.destroy');

    // Berth Routes Setup (Admin)
    Route::get('/admin/berth-routes', [BerthRouteAdminController::class, 'index'])->name('api.admin.berthRoutes.index');
    Route::post('/admin/berth-routes', [BerthRouteAdminController::class, 'store'])->name('api.admin.berthRoutes.store');
    Route::get('/admin/berth-routes/{id}', [BerthRouteAdminController::class, 'show'])->name('api.admin.berthRoutes.show');
    Route::put('/admin/berth-routes/{id}', [BerthRouteAdminController::class, 'update'])->name('api.admin.berthRoutes.update');
    Route::post('/admin/berth-routes/{id}/toggle', [BerthRouteAdminController::class, 'toggleStatus'])->name('api.admin.berthRoutes.toggle');
    Route::delete('/admin/berth-routes/{id}', [BerthRouteAdminController::class, 'destroy'])->name('api.admin.berthRoutes.destroy');

    // Seat Layouts & Vehicle Berths Setup (Admin)
    Route::get('/admin/berths', [SeatLayoutAdminController::class, 'index'])->name('api.admin.berths.index');
    Route::post('/admin/berths', [SeatLayoutAdminController::class, 'store'])->name('api.admin.berths.store');
    Route::get('/admin/berths/{id}', [SeatLayoutAdminController::class, 'show'])->name('api.admin.berths.show');
    Route::put('/admin/berths/{id}', [SeatLayoutAdminController::class, 'update'])->name('api.admin.berths.update');
    Route::delete('/admin/berths/{id}', [SeatLayoutAdminController::class, 'destroy'])->name('api.admin.berths.destroy');

    // Driver Vehicle Listing (Driver submission) & Fleet Approvals (Admin)
    Route::post('/driver/vehicles', [VehicleAdminController::class, 'driverStore'])->name('api.driver.vehicles.store');
    Route::get('/admin/vehicles', [VehicleAdminController::class, 'index'])->name('api.admin.vehicles.index');
    Route::get('/admin/vehicles/{id}', [VehicleAdminController::class, 'show'])->name('api.admin.vehicles.show');
    Route::put('/admin/vehicles/{id}', [VehicleAdminController::class, 'update'])->name('api.admin.vehicles.update');
    Route::post('/admin/vehicles/{id}/approve', [VehicleAdminController::class, 'approve'])->name('api.admin.vehicles.approve');
    Route::post('/admin/vehicles/{id}/reject', [VehicleAdminController::class, 'reject'])->name('api.admin.vehicles.reject');
    Route::delete('/admin/vehicles/{id}', [VehicleAdminController::class, 'destroy'])->name('api.admin.vehicles.destroy');

    // Platform Settings & Legal URLs Configuration (Admin)
    Route::get('/admin/settings', [PlatformSettingAdminController::class, 'getSettings'])->name('api.admin.settings.get');
    Route::post('/admin/settings/policy-urls', [PlatformSettingAdminController::class, 'updatePolicyUrls'])->name('api.admin.settings.updatePolicyUrls');
});

// Public Policy & Legal Documents
Route::get('/privacy-policy', function () {
    return view('legal', [
        'pageTitle' => 'Privacy Policy',
        'contentTitle' => 'IntercityWheels Privacy Policy',
        'updatedAt' => 'November 2026',
        'body' => 'IntercityWheels Mobility Services Pvt. Ltd. is committed to protecting your personal information. When you book outstation cabs or sleeper berths through our platform, we collect minimal operational data including name, contact phone, pickup/drop GPS coordinates, and payment receipts solely to deliver and coordinate transport services.'
    ]);
})->name('privacy.policy');

Route::get('/terms-conditions', function () {
    return view('legal', [
        'pageTitle' => 'Terms & Conditions (T&Cs)',
        'contentTitle' => 'IntercityWheels Rider & Chauffeur Terms & Conditions',
        'updatedAt' => 'November 2026',
        'body' => 'By accessing or using IntercityWheels.com, customers and driver partners agree to be bound by these Terms of Service. All outstation trips include transparent per-km billing, toll charges, night allowance rules, and real-time OTP validation for verified chauffeur departure.'
    ]);
})->name('terms.conditions');
