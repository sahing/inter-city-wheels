<?php

namespace App\Http\Controllers;

use App\Models\CustomerTraveller;
use App\Support\IndianPhone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CustomerProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'profile' => $this->serializeProfile($user),
            'travellers' => $user->travellers()->get(),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'phone' => ['required', 'string', 'min:8', 'max:20', 'regex:/^[0-9+\-\s()]{8,20}$/'],
            'email' => [
                'nullable',
                'email',
                'max:150',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'avatar' => 'nullable|string|max:10',
        ]);

        $user->name = trim($validated['name']);
        $user->phone = IndianPhone::format($validated['phone']);

        if (array_key_exists('email', $validated) && trim((string) $validated['email']) !== '') {
            $user->email = strtolower(trim((string) $validated['email']));
        }
        if (! empty($validated['avatar'])) {
            $user->avatar = trim((string) $validated['avatar']);
        }

        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Profile and phone number updated successfully.',
            'profile' => $this->serializeProfile($user->fresh()),
        ]);
    }

    public function listTravellers(Request $request)
    {
        return response()->json([
            'success' => true,
            'travellers' => $request->user()->travellers()->get(),
        ]);
    }

    public function storeTraveller(Request $request)
    {
        $validated = $this->validateTraveller($request);
        $user = $request->user();

        $traveller = DB::transaction(function () use ($user, $validated) {
            $makeDefault = (bool) ($validated['is_default'] ?? false);
            if ($makeDefault) {
                CustomerTraveller::where('user_id', $user->id)->update(['is_default' => false]);
            }

            return CustomerTraveller::create([
                'user_id' => $user->id,
                'name' => trim($validated['name']),
                'phone' => IndianPhone::format($validated['phone']),
                'email' => ! empty($validated['email']) ? strtolower(trim((string) $validated['email'])) : null,
                'age' => $validated['age'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'relation' => ! empty($validated['relation']) ? trim((string) $validated['relation']) : 'Self',
                'is_default' => $makeDefault,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Traveller saved to your profile.',
            'traveller' => $traveller->fresh(),
            'travellers' => $user->travellers()->get(),
        ], 201);
    }

    public function updateTraveller(Request $request, int $id)
    {
        $user = $request->user();
        $traveller = CustomerTraveller::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $validated = $this->validateTraveller($request);

        DB::transaction(function () use ($user, $traveller, $validated) {
            $makeDefault = (bool) ($validated['is_default'] ?? false);
            if ($makeDefault) {
                CustomerTraveller::where('user_id', $user->id)
                    ->where('id', '!=', $traveller->id)
                    ->update(['is_default' => false]);
            }

            $traveller->forceFill([
                'name' => trim($validated['name']),
                'phone' => IndianPhone::format($validated['phone']),
                'email' => ! empty($validated['email']) ? strtolower(trim((string) $validated['email'])) : null,
                'age' => $validated['age'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'relation' => ! empty($validated['relation']) ? trim((string) $validated['relation']) : ($traveller->relation ?: 'Self'),
                'is_default' => $makeDefault,
            ])->save();
        });

        return response()->json([
            'success' => true,
            'message' => 'Traveller details updated.',
            'traveller' => $traveller->fresh(),
            'travellers' => $user->travellers()->get(),
        ]);
    }

    public function destroyTraveller(Request $request, int $id)
    {
        $user = $request->user();
        $traveller = CustomerTraveller::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $traveller->delete();

        return response()->json([
            'success' => true,
            'message' => 'Traveller removed from your profile.',
            'travellers' => $user->travellers()->get(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateTraveller(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:100',
            'phone' => ['required', 'string', 'min:8', 'max:20', 'regex:/^[0-9+\-\s()]{8,20}$/'],
            'email' => 'nullable|email|max:150',
            'age' => 'nullable|integer|min:1|max:120',
            'gender' => 'nullable|string|in:Male,Female,Other',
            'relation' => 'nullable|string|max:50',
            'is_default' => 'nullable|boolean',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeProfile($user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar' => $user->avatar,
            'role' => $user->role,
        ];
    }
}
