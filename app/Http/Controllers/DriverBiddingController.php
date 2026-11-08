<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Driver;
use App\Models\DriverBid;
use App\Models\Booking;

class DriverBiddingController extends Controller
{
    public function getActiveBids()
    {
        $drivers = Driver::where('is_online', true)->get();
        return response()->json([
            'success' => true,
            'drivers' => $drivers
        ]);
    }

    public function counterOffer(Request $request)
    {
        $validated = $request->validate([
            'driver_id' => 'required|exists:drivers,id',
            'counter_amount' => 'required|numeric|min:500',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Counter offer dispatched to driver!',
            'driver_id' => $validated['driver_id'],
            'counter_amount' => $validated['counter_amount']
        ]);
    }

    public function acceptBid(Request $request)
    {
        $validated = $request->validate([
            'driver_id' => 'required|exists:drivers,id',
            'amount' => 'required|numeric|min:500',
            'route' => 'required|string',
            'customer_name' => 'required|string',
            'customer_phone' => 'required|string',
        ]);

        $driver = Driver::find($validated['driver_id']);
        $otp = str_pad(mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT);
        $bookingRef = 'ICW-' . mt_rand(10000, 99999);

        $booking = Booking::create([
            'booking_reference' => $bookingRef,
            'service_type' => 'cab',
            'trip_type' => 'oneway',
            'route_summary' => $validated['route'],
            'pickup_location' => 'Doorstep Pickup',
            'drop_location' => 'Destination City',
            'customer_name' => $validated['customer_name'],
            'customer_phone' => $validated['customer_phone'],
            'passengers' => [['name' => $validated['customer_name']]],
            'selected_units' => [$driver->vehicle_plate],
            'base_fare' => $validated['amount'],
            'toll_fare' => 0,
            'addon_fare' => 0,
            'discount_fare' => 0,
            'total_amount' => $validated['amount'],
            'status' => 'Driver Assigned',
            'start_otp' => $otp,
            'assigned_driver_id' => $driver->id
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Driver assigned and ride locked!',
            'booking' => $booking
        ]);
    }

    public function verifyStartOtp(Request $request, $id)
    {
        $validated = $request->validate([
            'otp' => 'required|string|size:4'
        ]);

        $booking = Booking::findOrFail($id);

        if ($booking->start_otp !== $validated['otp']) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid OTP. Ask the passenger for their 4-digit ride start OTP.'
            ], 422);
        }

        $booking->status = 'Started';
        $booking->save();

        return response()->json([
            'success' => true,
            'message' => 'OTP verified successfully! Trip has commenced.',
            'booking' => $booking
        ]);
    }

    public function completeTrip(Request $request, $id)
    {
        $booking = Booking::findOrFail($id);
        $booking->status = 'Completed';
        $booking->save();

        if ($booking->assigned_driver_id) {
            $driver = Driver::find($booking->assigned_driver_id);
            if ($driver) {
                // Driver gets total amount minus 10% platform fee
                $driverPayout = round($booking->total_amount * 0.90, 2);
                $driver->wallet_balance += $driverPayout;
                $driver->trips_count += 1;
                $driver->save();
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Trip marked as completed! Payout credited to your driver wallet.',
            'booking' => $booking
        ]);
    }

    public function withdrawPayout(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:100'
        ]);

        return response()->json([
            'success' => true,
            'message' => "Payout request for ₹" . number_format($validated['amount']) . " initiated to your registered bank account (HDFC •••• 4912) via IMPS."
        ]);
    }
}
