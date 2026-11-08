<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\RouteFare;
use App\Models\Driver;
use App\Models\SeatLayout;
use App\Models\DynamicFormField;
use App\Models\WorkflowStage;
use App\Models\Coupon;
use App\Models\Booking;

class HomeController extends Controller
{
    public function index()
    {
        $routes = RouteFare::where('is_active', true)->get();
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

        // Role-Specific Datasets
        $customerBookings = collect();
        if ($currentUser && $currentUser->role === 'customer') {
            $customerBookings = Booking::with('driver')
                ->where(function ($q) use ($currentUser) {
                    $q->where('customer_phone', $currentUser->phone)
                      ->orWhere('customer_email', $currentUser->email)
                      ->orWhere('customer_name', 'like', '%' . explode(' ', $currentUser->name)[0] . '%');
                })
                ->latest()
                ->get();
            if ($customerBookings->isEmpty()) {
                $customerBookings = Booking::with('driver')->latest()->take(3)->get();
            }
        }

        $driverProfile = null;
        $driverTrips = collect();
        if ($currentUser && $currentUser->role === 'driver') {
            $driverProfile = Driver::where('phone', $currentUser->phone)
                ->orWhere('name', 'like', '%' . explode(' ', $currentUser->name)[0] . '%')
                ->first() ?: Driver::first();

            if ($driverProfile) {
                $driverTrips = Booking::with('driver')
                    ->where('assigned_driver_id', $driverProfile->id)
                    ->latest()
                    ->get();

                if ($driverTrips->isEmpty()) {
                    $demoBooking = Booking::first();
                    if ($demoBooking) {
                        $demoBooking->update(['assigned_driver_id' => $driverProfile->id, 'status' => 'Driver Assigned']);
                        $driverTrips = Booking::with('driver')
                            ->where('assigned_driver_id', $driverProfile->id)
                            ->latest()
                            ->get();
                    }
                }
            }
        }

        return view('intercity', compact(
            'routes',
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
            'allUsers'
        ));
    }
}
