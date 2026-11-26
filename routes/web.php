<?php

use App\Http\Controllers\Admin\AirportHubAdminController;
use App\Http\Controllers\Admin\BerthRouteAdminController;
use App\Http\Controllers\Admin\BiddingAdminController;
use App\Http\Controllers\Admin\BookingAdminController;
use App\Http\Controllers\Admin\CabRouteAdminController;
use App\Http\Controllers\Admin\CarTypeAdminController;
use App\Http\Controllers\Admin\CouponAdminController;
use App\Http\Controllers\Admin\DynamicFormController;
use App\Http\Controllers\Admin\LocalPackageAdminController;
use App\Http\Controllers\Admin\LocationAdminController;
use App\Http\Controllers\Admin\PlatformSettingAdminController;
use App\Http\Controllers\Admin\SeatLayoutAdminController;
use App\Http\Controllers\Admin\UserAdminController;
use App\Http\Controllers\Admin\VehicleAdminController;
use App\Http\Controllers\Admin\WorkflowConfigController;
use App\Http\Controllers\Api\BiddingController;
use App\Http\Controllers\Api\LocationSearchController;
use App\Http\Controllers\Api\RouteCalculationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BiddingPageController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\DriverBiddingController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NotificationController;
use App\Models\AirportHub;
use App\Models\CarType;
use App\Models\LocalPackage;
use App\Models\ServiceLocation;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Portal
|--------------------------------------------------------------------------
*/

Route::get('/', [HomeController::class, 'index'])->name('home');

/*
|--------------------------------------------------------------------------
| Authentication Routes (Role-based, OTP & Alternate Providers)
|--------------------------------------------------------------------------
| Login, registration, OTP dispatch and provider callbacks must stay public.
*/

Route::get('/login', [AuthController::class, 'showCustomerPortal'])->name('login.portal');
Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/register', [AuthController::class, 'register'])->name('register');

Route::get('/driver', [AuthController::class, 'showDriverPortal'])->name('driver.portal');
Route::post('/driver/login', [AuthController::class, 'driverLogin'])->name('driver.login');
Route::post('/driver/register', [AuthController::class, 'driverRegister'])->name('driver.register');

Route::get('/admin', [AuthController::class, 'showAdminPortal'])->name('admin.portal');
Route::post('/admin/login', [AuthController::class, 'adminLogin'])->name('admin.login');

Route::post('/auth/send-otp', [AuthController::class, 'sendMobileOtp'])->name('auth.sendOtp');
Route::post('/auth/verify-otp', [AuthController::class, 'verifyMobileOtp'])->name('auth.verifyOtp');
Route::get('/auth/alternate-config', [AuthController::class, 'alternateConfig'])->name('auth.alternateConfig');
Route::post('/auth/firebase-login', [AuthController::class, 'firebaseLogin'])->name('auth.firebaseLogin');

/*
| Demo-only authentication surfaces. Each of these can authenticate a caller
| without credentials, so `demo` middleware answers 404 unless demo mode is on.
| See config/demo.php and INTERCITY_DEMO_MODE.
*/
Route::middleware('demo')->group(function () {
    Route::post('/auth/alternate-login', [AuthController::class, 'alternateLogin'])->name('auth.alternateLogin');
    Route::post('/auth/clerk-login', [AuthController::class, 'clerkLogin'])->name('auth.clerkLogin');
    Route::post('/login/quick-demo', [AuthController::class, 'quickDemoLogin'])->name('login.demo');
});
Route::match(['get', 'post'], '/logout', [AuthController::class, 'logout'])->name('logout');

/*
|--------------------------------------------------------------------------
| Health Check
|--------------------------------------------------------------------------
*/

Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'framework' => 'Laravel 12 (PHP '.PHP_VERSION.')',
        'database' => 'connected',
        'timestamp' => now()->toIso8601String(),
    ]);
});

/*
|--------------------------------------------------------------------------
| Public API (catalogue, search, fare calculation, booking intake)
|--------------------------------------------------------------------------
| Booking intake and lookup stay public so guests can complete a booking
| without an account. Lookup hardening is tracked separately.
*/

Route::prefix('api')->group(function () {
    Route::get('/routes/calculate', [RouteCalculationController::class, 'calculate'])->name('api.routes.calculate');
    Route::get('/locations/search', [LocationSearchController::class, 'search'])->name('api.locations.search');
    Route::get('/locations/detect', [LocationSearchController::class, 'detect'])->name('api.locations.detect');
    Route::get('/locations', function () {
        return response()->json([
            'success' => true,
            'locations' => ServiceLocation::where('is_active', true)->orderBy('name')->get(),
        ]);
    })->name('api.locations.active');
    Route::get('/local-packages', function () {
        return response()->json([
            'success' => true,
            'packages' => LocalPackage::where('is_active', true)->orderBy('duration_hours')->get(),
        ]);
    })->name('api.localPackages.active');
    Route::get('/airport-hubs', function () {
        return response()->json([
            'success' => true,
            'airports' => AirportHub::where('is_active', true)->orderBy('city')->get(),
        ]);
    })->name('api.airportHubs.active');
    Route::get('/car-types', function () {
        return response()->json([
            'success' => true,
            'car_types' => CarType::where('is_active', true)->orderBy('order_index')->get(),
        ]);
    })->name('api.carTypes.active');

    // Booking creation requires a signed-in customer — see the authenticated
    // group below. Browsing, quoting and ticket retrieval stay anonymous.

    // Anonymous ticket retrieval: reference + the mobile number on the booking.
    Route::post('/bookings/lookup', [BookingController::class, 'lookup'])->name('api.bookings.lookup');

    // P0-8: private. Authorised by signed URL (handed out at booking time),
    // account ownership, or the booking's mobile number — never by reference alone.
    Route::get('/bookings/{reference}', [BookingController::class, 'show'])->name('api.bookings.show');
});

/*
|--------------------------------------------------------------------------
| Authenticated API (any signed-in role)
|--------------------------------------------------------------------------
*/

Route::prefix('api')->middleware(['auth'])->group(function () {
    Route::get('/auth/user', [AuthController::class, 'current'])->name('api.auth.user');

    // In-app Notifications
    Route::get('/notifications', [NotificationController::class, 'index'])->name('api.notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('api.notifications.readAll');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('api.notifications.read');

    // Confirming a booking requires a signed-in customer, so every trip is
    // owned by an account. Guests are sent to the one-tap login gate.
    Route::post('/bookings', [BookingController::class, 'store'])->name('api.bookings.store');
    Route::post('/bookings/{reference}/razorpay/order', [BookingController::class, 'createRazorpayOrder'])->name('api.bookings.razorpay.order');
    Route::post('/bookings/{reference}/razorpay/verify', [BookingController::class, 'verifyRazorpayPayment'])->name('api.bookings.razorpay.verify');
});

/*
|--------------------------------------------------------------------------
| Customer API (customer + admin)
|--------------------------------------------------------------------------
| Accepting a bid locks a fare and allots a driver, so it belongs to the
| customer who owns the request — not to a driver.
*/

Route::prefix('api')->middleware(['auth', 'role:customer,admin'])->group(function () {
    Route::post('/bids/accept', [DriverBiddingController::class, 'acceptBid'])->name('api.bids.accept');
});

/*
|--------------------------------------------------------------------------
| Driver Bidding Marketplace
|--------------------------------------------------------------------------
| Drivers arrive from a WhatsApp link. Reading the masked auction is public so
| the page can say what the trip is before asking anybody to sign in; placing a
| bid requires an authenticated, verified driver. The payload from
| GET /api/bidding/{token} is built from the auction row only — no customer
| name, contact, addresses or fare.
*/

Route::get('/bid/{token}', [BiddingPageController::class, 'show'])
    ->name('bidding.page');

Route::prefix('api')->group(function () {
    Route::get('/bidding/{token}', [BiddingController::class, 'show'])
        ->name('api.bidding.show');
});

Route::prefix('api')->middleware(['auth', 'role:driver,admin'])->group(function () {
    Route::post('/bidding/{token}/bids', [BiddingController::class, 'placeBid'])
        ->name('api.bidding.bids.store');
    Route::get('/bidding/{token}/mine', [BiddingController::class, 'mine'])
        ->name('api.bidding.mine');
});

/*
|--------------------------------------------------------------------------
| Driver Console (driver + admin)
|--------------------------------------------------------------------------
*/

Route::prefix('api')->middleware(['auth', 'role:driver,admin'])->group(function () {
    // Live auctions for the driver portal: masked, open auctions only.
    Route::get('/driver/auctions', [BiddingController::class, 'activeAuctions'])->name('api.driver.auctions');

    Route::get('/bids', [DriverBiddingController::class, 'getActiveBids'])->name('api.bids.active');
    Route::post('/bids/counter', [DriverBiddingController::class, 'counterOffer'])->name('api.bids.counter');
    Route::post('/bids/submit', [DriverBiddingController::class, 'submitDriverBid'])->name('api.bids.submit');

    Route::post('/driver/trips/{id}/verify-otp', [DriverBiddingController::class, 'verifyStartOtp'])->name('api.driver.trips.verifyOtp');
    Route::post('/driver/trips/{id}/complete', [DriverBiddingController::class, 'completeTrip'])->name('api.driver.trips.complete');
    Route::post('/driver/wallet/withdraw', [DriverBiddingController::class, 'withdrawPayout'])->name('api.driver.wallet.withdraw');
    Route::get('/driver/profile', [DriverBiddingController::class, 'getProfile'])->name('api.driver.profile.get');
    Route::post('/driver/profile', [DriverBiddingController::class, 'updateProfile'])->name('api.driver.profile.update');
    Route::post('/driver/toggle-online', [DriverBiddingController::class, 'toggleOnlineStatus'])->name('api.driver.toggleOnline');

    Route::post('/driver/vehicles', [VehicleAdminController::class, 'driverStore'])->name('api.driver.vehicles.store');
});

/*
|--------------------------------------------------------------------------
| Admin Operations (admin only)
|--------------------------------------------------------------------------
*/

Route::prefix('api')->middleware(['auth', 'role:admin'])->group(function () {
    // Booking Management
    Route::get('/admin/bookings', [BookingAdminController::class, 'index'])->name('api.admin.bookings.index');
    Route::post('/admin/bookings', [BookingAdminController::class, 'store'])->name('api.admin.bookings.store');
    Route::get('/admin/bookings/{id}', [BookingAdminController::class, 'show'])->name('api.admin.bookings.show');
    Route::put('/admin/bookings/{id}', [BookingAdminController::class, 'update'])->name('api.admin.bookings.update');
    Route::post('/admin/bookings/{id}/allot-driver', [BookingAdminController::class, 'allotDriver'])->name('api.admin.bookings.allotDriver');
    Route::get('/admin/bookings/{id}/invoice', [BookingAdminController::class, 'invoice'])->name('api.admin.bookings.invoice');

    // Users & Drivers Directory
    Route::get('/admin/users', [UserAdminController::class, 'index'])->name('api.admin.users.index');
    Route::post('/admin/users', [UserAdminController::class, 'store'])->name('api.admin.users.store');
    Route::get('/admin/users/{id}', [UserAdminController::class, 'show'])->name('api.admin.users.show');
    Route::put('/admin/users/{id}', [UserAdminController::class, 'update'])->name('api.admin.users.update');
    Route::patch('/admin/users/{id}/role', [UserAdminController::class, 'updateRole'])->name('api.admin.users.updateRole');
    Route::delete('/admin/users/{id}', [UserAdminController::class, 'destroy'])->name('api.admin.users.destroy');

    // Dynamic Form Builder
    Route::get('/admin/dynamic-fields', [DynamicFormController::class, 'index'])->name('api.admin.fields.index');
    Route::post('/admin/dynamic-fields', [DynamicFormController::class, 'store'])->name('api.admin.fields.store');
    Route::delete('/admin/dynamic-fields/{id}', [DynamicFormController::class, 'destroy'])->name('api.admin.fields.destroy');

    // Workflow Stages
    Route::get('/admin/workflows', [WorkflowConfigController::class, 'index'])->name('api.admin.workflows.index');
    Route::put('/admin/workflows/{id}', [WorkflowConfigController::class, 'update'])->name('api.admin.workflows.update');

    // Outstation Cab Routes
    Route::get('/admin/cab-routes', [CabRouteAdminController::class, 'index'])->name('api.admin.cabRoutes.index');
    Route::post('/admin/cab-routes/calculate-distance', [CabRouteAdminController::class, 'calculateDistance'])->name('api.admin.cabRoutes.calculateDistance');
    Route::post('/admin/cab-routes', [CabRouteAdminController::class, 'store'])->name('api.admin.cabRoutes.store');
    Route::post('/admin/cab-routes/bulk-adjust', [CabRouteAdminController::class, 'bulkAdjust'])->name('api.admin.cabRoutes.bulkAdjust');
    Route::get('/admin/cab-routes/{id}', [CabRouteAdminController::class, 'show'])->name('api.admin.cabRoutes.show');
    Route::put('/admin/cab-routes/{id}', [CabRouteAdminController::class, 'update'])->name('api.admin.cabRoutes.update');
    Route::post('/admin/cab-routes/{id}/toggle', [CabRouteAdminController::class, 'toggleStatus'])->name('api.admin.cabRoutes.toggle');
    Route::delete('/admin/cab-routes/{id}', [CabRouteAdminController::class, 'destroy'])->name('api.admin.cabRoutes.destroy');

    // Local Rental Packages
    Route::get('/admin/local-packages', [LocalPackageAdminController::class, 'index'])->name('api.admin.localPackages.index');
    Route::post('/admin/local-packages', [LocalPackageAdminController::class, 'store'])->name('api.admin.localPackages.store');
    Route::post('/admin/local-packages/bulk-rates', [LocalPackageAdminController::class, 'bulkUpdateRates'])->name('api.admin.localPackages.bulkRates');
    Route::get('/admin/local-packages/{id}', [LocalPackageAdminController::class, 'show'])->name('api.admin.localPackages.show');
    Route::put('/admin/local-packages/{id}', [LocalPackageAdminController::class, 'update'])->name('api.admin.localPackages.update');
    Route::post('/admin/local-packages/{id}/toggle', [LocalPackageAdminController::class, 'toggleStatus'])->name('api.admin.localPackages.toggle');
    Route::delete('/admin/local-packages/{id}', [LocalPackageAdminController::class, 'destroy'])->name('api.admin.localPackages.destroy');

    // Airport Transit Hubs
    Route::get('/admin/airport-hubs', [AirportHubAdminController::class, 'index'])->name('api.admin.airportHubs.index');
    Route::post('/admin/airport-hubs', [AirportHubAdminController::class, 'store'])->name('api.admin.airportHubs.store');
    Route::get('/admin/airport-hubs/{id}', [AirportHubAdminController::class, 'show'])->name('api.admin.airportHubs.show');
    Route::put('/admin/airport-hubs/{id}', [AirportHubAdminController::class, 'update'])->name('api.admin.airportHubs.update');
    Route::post('/admin/airport-hubs/{id}/toggle', [AirportHubAdminController::class, 'toggleStatus'])->name('api.admin.airportHubs.toggle');
    Route::delete('/admin/airport-hubs/{id}', [AirportHubAdminController::class, 'destroy'])->name('api.admin.airportHubs.destroy');

    // Car Types & Fleet Categories
    Route::get('/admin/car-types', [CarTypeAdminController::class, 'index'])->name('api.admin.carTypes.index');
    Route::post('/admin/car-types', [CarTypeAdminController::class, 'store'])->name('api.admin.carTypes.store');
    Route::post('/admin/car-types/bulk-rates', [CarTypeAdminController::class, 'bulkUpdateRates'])->name('api.admin.carTypes.bulkRates');
    Route::get('/admin/car-types/{id}', [CarTypeAdminController::class, 'show'])->name('api.admin.carTypes.show');
    Route::put('/admin/car-types/{id}', [CarTypeAdminController::class, 'update'])->name('api.admin.carTypes.update');
    Route::post('/admin/car-types/{id}/toggle', [CarTypeAdminController::class, 'toggleStatus'])->name('api.admin.carTypes.toggle');
    Route::delete('/admin/car-types/{id}', [CarTypeAdminController::class, 'destroy'])->name('api.admin.carTypes.destroy');

    // Service Locations
    Route::get('/admin/locations', [LocationAdminController::class, 'index'])->name('api.admin.locations.index');
    Route::post('/admin/locations', [LocationAdminController::class, 'store'])->name('api.admin.locations.store');
    Route::get('/admin/locations/{id}', [LocationAdminController::class, 'show'])->name('api.admin.locations.show');
    Route::put('/admin/locations/{id}', [LocationAdminController::class, 'update'])->name('api.admin.locations.update');
    Route::post('/admin/locations/{id}/toggle', [LocationAdminController::class, 'toggleStatus'])->name('api.admin.locations.toggle');
    Route::delete('/admin/locations/{id}', [LocationAdminController::class, 'destroy'])->name('api.admin.locations.destroy');

    // Berth Routes
    Route::get('/admin/berth-routes', [BerthRouteAdminController::class, 'index'])->name('api.admin.berthRoutes.index');
    Route::post('/admin/berth-routes', [BerthRouteAdminController::class, 'store'])->name('api.admin.berthRoutes.store');
    Route::get('/admin/berth-routes/{id}', [BerthRouteAdminController::class, 'show'])->name('api.admin.berthRoutes.show');
    Route::put('/admin/berth-routes/{id}', [BerthRouteAdminController::class, 'update'])->name('api.admin.berthRoutes.update');
    Route::post('/admin/berth-routes/{id}/toggle', [BerthRouteAdminController::class, 'toggleStatus'])->name('api.admin.berthRoutes.toggle');
    Route::delete('/admin/berth-routes/{id}', [BerthRouteAdminController::class, 'destroy'])->name('api.admin.berthRoutes.destroy');

    // Seat Layouts & Vehicle Berths
    Route::get('/admin/berths', [SeatLayoutAdminController::class, 'index'])->name('api.admin.berths.index');
    Route::post('/admin/berths', [SeatLayoutAdminController::class, 'store'])->name('api.admin.berths.store');
    Route::get('/admin/berths/{id}', [SeatLayoutAdminController::class, 'show'])->name('api.admin.berths.show');
    Route::put('/admin/berths/{id}', [SeatLayoutAdminController::class, 'update'])->name('api.admin.berths.update');
    Route::delete('/admin/berths/{id}', [SeatLayoutAdminController::class, 'destroy'])->name('api.admin.berths.destroy');

    // Fleet Approvals
    Route::get('/admin/vehicles', [VehicleAdminController::class, 'index'])->name('api.admin.vehicles.index');
    Route::get('/admin/vehicles/{id}', [VehicleAdminController::class, 'show'])->name('api.admin.vehicles.show');
    Route::put('/admin/vehicles/{id}', [VehicleAdminController::class, 'update'])->name('api.admin.vehicles.update');
    Route::post('/admin/vehicles/{id}/approve', [VehicleAdminController::class, 'approve'])->name('api.admin.vehicles.approve');
    Route::post('/admin/vehicles/{id}/reject', [VehicleAdminController::class, 'reject'])->name('api.admin.vehicles.reject');
    Route::delete('/admin/vehicles/{id}', [VehicleAdminController::class, 'destroy'])->name('api.admin.vehicles.destroy');

    // Platform Settings & Legal URLs
    Route::get('/admin/settings', [PlatformSettingAdminController::class, 'getSettings'])->name('api.admin.settings.get');
    Route::post('/admin/settings/policy-urls', [PlatformSettingAdminController::class, 'updatePolicyUrls'])->name('api.admin.settings.updatePolicyUrls');
    Route::post('/admin/settings/toggle-feature', [PlatformSettingAdminController::class, 'toggleFeature'])->name('api.admin.settings.toggleFeature');
    Route::post('/admin/settings/booking-type-car-types', [PlatformSettingAdminController::class, 'updateBookingTypeCarTypes'])->name('api.admin.settings.bookingTypeCarTypes');
    Route::post('/admin/settings/pricing-conditions', [PlatformSettingAdminController::class, 'updatePricingConditions'])->name('api.admin.settings.pricingConditions');
    Route::post('/admin/settings/bidding', [PlatformSettingAdminController::class, 'updateBiddingSettings'])->name('api.admin.settings.bidding');
    Route::post('/admin/settings/payment-options', [PlatformSettingAdminController::class, 'updatePaymentSettings'])->name('api.admin.settings.paymentOptions');
    Route::post('/admin/settings/invoice-template', [PlatformSettingAdminController::class, 'updateInvoiceSettings'])->name('api.admin.settings.invoiceTemplate');
    Route::post('/admin/settings/legal-pages', [PlatformSettingAdminController::class, 'updateLegalPages'])->name('api.admin.settings.legalPages');

    // Driver Bidding Marketplace
    Route::get('/admin/biddings', [BiddingAdminController::class, 'index'])->name('api.admin.biddings.index');
    Route::post('/admin/bookings/{bookingId}/bidding', [BiddingAdminController::class, 'store'])->name('api.admin.bidding.store');
    Route::get('/admin/biddings/{id}', [BiddingAdminController::class, 'show'])->name('api.admin.biddings.show');
    Route::post('/admin/biddings/{id}/close', [BiddingAdminController::class, 'close'])->name('api.admin.biddings.close');
    Route::post('/admin/biddings/{id}/award', [BiddingAdminController::class, 'award'])->name('api.admin.biddings.award');
    Route::post('/admin/biddings/{id}/cancel', [BiddingAdminController::class, 'cancel'])->name('api.admin.biddings.cancel');

    // Promotional Coupons
    Route::get('/admin/coupons', [CouponAdminController::class, 'index'])->name('api.admin.coupons.index');
    Route::post('/admin/coupons', [CouponAdminController::class, 'store'])->name('api.admin.coupons.store');
    Route::get('/admin/coupons/{id}', [CouponAdminController::class, 'show'])->name('api.admin.coupons.show');
    Route::put('/admin/coupons/{id}', [CouponAdminController::class, 'update'])->name('api.admin.coupons.update');
    Route::post('/admin/coupons/{id}/toggle', [CouponAdminController::class, 'toggleStatus'])->name('api.admin.coupons.toggle');
    Route::delete('/admin/coupons/{id}', [CouponAdminController::class, 'destroy'])->name('api.admin.coupons.destroy');
});

/*
|--------------------------------------------------------------------------
| Public Policy & Legal Documents
|--------------------------------------------------------------------------
*/

Route::get('/privacy-policy', function () {
    $legal = \App\Models\PlatformSetting::legalSettings();

    return view('legal', [
        'pageTitle' => 'Privacy Policy',
        'contentTitle' => $legal['privacy_policy_title'],
        'updatedAt' => $legal['privacy_policy_updated_at'],
        'body' => $legal['privacy_policy_body'],
        'contactHelpline' => $legal['legal_contact_helpline'],
        'contactEmail' => $legal['legal_contact_email'],
    ]);
})->name('privacy.policy');

Route::get('/terms-conditions', function () {
    $legal = \App\Models\PlatformSetting::legalSettings();

    return view('legal', [
        'pageTitle' => 'Terms & Conditions (T&Cs)',
        'contentTitle' => $legal['terms_conditions_title'],
        'updatedAt' => $legal['terms_conditions_updated_at'],
        'body' => $legal['terms_conditions_body'],
        'contactHelpline' => $legal['legal_contact_helpline'],
        'contactEmail' => $legal['legal_contact_email'],
    ]);
})->name('terms.conditions');
