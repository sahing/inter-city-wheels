<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SeatLayout;
use Illuminate\Http\Request;

class SeatLayoutAdminController extends Controller
{
    public function index(Request $request)
    {
        $query = SeatLayout::query();

        $coachCode = $request->input('coach_code') ?: $request->input('coach');
        if ($coachCode && $coachCode !== 'all') {
            $query->where('coach_code', $coachCode);
        }

        $berths = $query->orderBy('coach_code')->orderBy('deck')->orderBy('berth_number')->get();

        return response()->json([
            'success' => true,
            'berths' => $berths,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'coach_code' => 'required|string|max:30',
            'operator_name' => 'required|string|max:120',
            'deck' => 'required|in:lower,upper,single',
            'berth_number' => 'required|string|max:15',
            'berth_type' => 'required|string|max:30', // window, aisle, sleeper, double_sleeper
            'price' => 'required|numeric|min:50',
            'is_ladies_quota' => 'nullable|boolean',
            'is_booked' => 'nullable|boolean',
        ]);

        $validated['is_ladies_quota'] = $request->boolean('is_ladies_quota', false);
        $validated['is_booked'] = $request->boolean('is_booked', false);

        // Check if berth number already exists for this coach/car
        $exists = SeatLayout::where('coach_code', $validated['coach_code'])
            ->where('berth_number', $validated['berth_number'])
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => "Berth '{$validated['berth_number']}' already exists on vehicle/coach {$validated['coach_code']}.",
            ], 422);
        }

        $berth = SeatLayout::create($validated);

        return response()->json([
            'success' => true,
            'message' => "Berth '{$berth->berth_number}' added to vehicle {$berth->coach_code} successfully!",
            'berth' => $berth,
        ]);
    }

    public function show($id)
    {
        $berth = SeatLayout::findOrFail($id);

        return response()->json([
            'success' => true,
            'berth' => $berth,
        ]);
    }

    public function update(Request $request, $id)
    {
        $berth = SeatLayout::findOrFail($id);

        $validated = $request->validate([
            'coach_code' => 'required|string|max:30',
            'operator_name' => 'required|string|max:120',
            'deck' => 'required|in:lower,upper,single',
            'berth_number' => 'required|string|max:15',
            'berth_type' => 'required|string|max:30',
            'price' => 'required|numeric|min:50',
            'is_ladies_quota' => 'nullable|boolean',
            'is_booked' => 'nullable|boolean',
        ]);

        $validated['is_ladies_quota'] = $request->boolean('is_ladies_quota', $berth->is_ladies_quota);
        $validated['is_booked'] = $request->boolean('is_booked', $berth->is_booked);

        $berth->update($validated);

        return response()->json([
            'success' => true,
            'message' => "Berth '{$berth->berth_number}' updated successfully!",
            'berth' => $berth,
        ]);
    }

    public function destroy($id)
    {
        $berth = SeatLayout::findOrFail($id);
        $num = $berth->berth_number;
        $coach = $berth->coach_code;
        $berth->delete();

        return response()->json([
            'success' => true,
            'message' => "Berth '{$num}' removed from vehicle {$coach}.",
        ]);
    }
}
