<?php

namespace App\Http\Controllers;

use App\Models\BerthRoute;
use App\Models\Booking;
use App\Models\Coupon;
use App\Models\Driver;
use App\Models\DriverVehicle;
use App\Models\DynamicFormField;
use App\Models\RouteFare;
use App\Models\SeatLayout;
use App\Models\ServiceLocation;
use App\Models\WorkflowStage;

class HomeController extends Controller
{
    public function index()
    {
        $routes = RouteFare::where('is_active', true)->get();
        $allCabRoutes = RouteFare::latest()->get();
        $drivers = Driver::where('is_online', true)->get();
        $lowerBerths = SeatLayout::where('deck', 'lower')->get();
        $upperBerths = SeatLayout::where('deck', 'upper')->get();
        $dynamicFields = DynamicFormField::where('is_active', true)->orderBy('order_index')->get();
        $workflowStages = WorkflowStage::where('is_active', true)->orderBy('order_index')->get();
        $coupons = Coupon::where('is_active', true)->get();
        $recentBookings = Booking::with('driver')->latest()->get();
        $allDrivers = Driver::all();
        $allUsers = \App\Models\User::latest()->get();
        $currentUser = auth()->user();

        // Admin Datasets for Locations, Berth Routes, Berth Layouts & Vehicles
        $serviceLocations = ServiceLocation::orderBy('name')->get();
        $berthRoutes = BerthRoute::with('seatLayouts')->latest()->get();
        $allSeatLayouts = SeatLayout::orderBy('coach_code')->orderBy('deck')->orderBy('berth_number')->get();
        $driverVehicles = DriverVehicle::with('driver')->latest()->get();
        $localPackages = \App\Models\LocalPackage::orderBy('duration_hours')->get();
        $airportHubs = \App\Models\AirportHub::orderBy('city')->get();
        $allCarTypes = \App\Models\CarType::orderBy('order_index')->get();
        $platformSettings = \App\Models\PlatformSetting::allAdminSettings();
        $isSchedulesEnabled = $platformSettings['enable_schedules_and_berths'] === '1';

        // Role-specific datasets.
        //
        // A GET must not write. This previously reassigned the first booking in
        // the table to whichever driver loaded the page, and matched a
        // customer's trips by first name, so customers with a common name saw
        // each other's bookings. Ownership is now the account link, with a
        // precise phone match for bookings made before accounts were linked.
        $customerBookings = collect();
        $driverProfile = null;
        $driverTrips = collect();
        $driverMyVehicles = collect();

        if ($currentUser && $currentUser->role === 'customer') {
            $digits = preg_replace('/[^0-9]/', '', (string) $currentUser->phone) ?? '';
            $last10 = strlen($digits) >= 10 ? substr($digits, -10) : $digits;

            $customerBookings = Booking::with('driver')
                ->where(function ($query) use ($currentUser, $last10) {
                    $query->where('customer_user_id', $currentUser->id);

                    if (strlen($last10) === 10) {
                        $query->orWhere('customer_phone', 'like', '%'.$last10.'%');
                    }
                })
                ->latest()
                ->get();
        }

        if ($currentUser && $currentUser->role === 'driver') {
            $driverProfile = Driver::where('phone', $currentUser->phone)->first();

            if (! $driverProfile) {
                $digits = preg_replace('/[^0-9]/', '', (string) $currentUser->phone) ?? '';
                $last10 = substr($digits, -10);

                if (strlen($last10) === 10) {
                    $driverProfile = Driver::where('phone', 'like', '%'.$last10.'%')->first();
                }
            }

            if ($driverProfile) {
                $driverMyVehicles = DriverVehicle::where('driver_id', $driverProfile->id)->latest()->get();

                $driverTrips = Booking::with('driver')
                    ->where('assigned_driver_id', $driverProfile->id)
                    ->whereNotIn('status', ['Requested', 'Bidding', 'Cancelled'])
                    ->latest()
                    ->get();
            }
        } elseif ($currentUser && $currentUser->role === 'admin') {
            $driverProfile = Driver::first();
            if ($driverProfile) {
                $driverMyVehicles = DriverVehicle::where('driver_id', $driverProfile->id)->latest()->get();
                $driverTrips = Booking::with('driver')
                    ->where('assigned_driver_id', $driverProfile->id)
                    ->whereNotIn('status', ['Requested', 'Bidding', 'Cancelled'])
                    ->latest()
                    ->get();
            }
        }

        return view('intercity', compact(
            'routes',
            'allCabRoutes',
            'drivers',
            'allDrivers',
            'lowerBerths',
            'upperBerths',
            'dynamicFields',
            'workflowStages',
            'coupons',
            'recentBookings',
            'currentUser',
            'customerBookings',
            'driverProfile',
            'driverTrips',
            'allUsers',
            'serviceLocations',
            'berthRoutes',
            'allSeatLayouts',
            'driverVehicles',
            'driverMyVehicles',
            'localPackages',
            'airportHubs',
            'allCarTypes',
            'platformSettings',
            'isSchedulesEnabled'
        ));
    }
}
