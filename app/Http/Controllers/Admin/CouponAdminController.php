<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Driver;
use App\Models\RouteFare;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Issues and maintains promotional coupons from the admin panel.
 *
 * Redemption itself is owned by FareCalculatorService, so an admin-issued
 * coupon is immediately redeemable at booking time with no extra wiring.
 */
class CouponAdminController extends Controller
{
    public function index(Request $request)
    {
        $query = Coupon::query()->orderByDesc('is_active')->orderByDesc('id');

        if ($request->filled('search')) {
            $needle = strtoupper(trim((string) $request->search));
            $query->where('code', 'like', '%'.$needle.'%');
        }

        if ($request->filled('status') && $request->status !== 'all') {
            if ($request->status === 'auto_apply') {
                $query->where('auto_apply', true);
            } else {
                $query->where('is_active', $request->status === 'active');
            }
        }

        $coupons = $query->get()->map(fn (Coupon $coupon) => $this->present($coupon))->values();

        return response()->json([
            'success' => true,
            'coupons' => $coupons,
            'meta' => [
                'total' => $coupons->count(),
                'active' => $coupons->where('is_active', true)->count(),
                'redeemable' => $coupons->where('redeemable', true)->count(),
                'auto_apply' => $coupons->where('auto_apply', true)->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $request->merge(['code' => $this->normaliseCode($request->input('code'))]);

        $validated = $request->validate($this->rules());

        $coupon = Coupon::create($this->attributes($validated));

        return response()->json([
            'success' => true,
            'message' => "Coupon '{$coupon->code}' issued successfully!",
            'coupon' => $this->present($coupon),
        ], 201);
    }

    public function show($id)
    {
        $coupon = Coupon::findOrFail($id);

        return response()->json([
            'success' => true,
            'coupon' => $this->present($coupon),
        ]);
    }

    public function update(Request $request, $id)
    {
        $coupon = Coupon::findOrFail($id);

        if ($request->has('code')) {
            $request->merge(['code' => $this->normaliseCode($request->input('code'))]);
        }

        $validated = $request->validate($this->rules($coupon->id));

        $coupon->update($this->attributes($validated, $coupon));

        return response()->json([
            'success' => true,
            'message' => "Coupon '{$coupon->code}' updated successfully!",
            'coupon' => $this->present($coupon->fresh()),
        ]);
    }

    public function toggleStatus($id)
    {
        $coupon = Coupon::findOrFail($id);
        $coupon->is_active = ! $coupon->is_active;
        $coupon->save();

        return response()->json([
            'success' => true,
            'message' => "Coupon '{$coupon->code}' is now ".($coupon->is_active ? 'Active' : 'Inactive'),
            'coupon' => $this->present($coupon),
        ]);
    }

    public function destroy($id)
    {
        $coupon = Coupon::findOrFail($id);
        $code = $coupon->code;
        $coupon->delete();

        return response()->json([
            'success' => true,
            'message' => "Coupon '{$code}' deleted successfully!",
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(?int $ignoreId = null): array
    {
        return [
            'code' => ['required', 'string', 'max:40', Rule::unique('coupons', 'code')->ignore($ignoreId)],
            'title' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in(['percentage', 'flat'])],
            'value' => [
                'required',
                'numeric',
                'min:0.01',
                function (string $attribute, $value, Closure $fail): void {
                    if (request()->input('type') === 'percentage' && (float) $value > 100) {
                        $fail('A percentage coupon cannot exceed 100%.');
                    }
                },
            ],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'min_spend' => ['nullable', 'numeric', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_user_limit' => ['nullable', 'integer', 'min:1'],
            'booking_types' => ['nullable', 'array'],
            'booking_types.*' => ['string', Rule::in(['oneway', 'roundtrip', 'local', 'airport', 'airport_pickup', 'airport_drop', 'bus'])],
            'car_types' => ['nullable', 'array'],
            'car_types.*' => ['string', 'max:60'],
            'route_fare_ids' => ['nullable', 'array'],
            'route_fare_ids.*' => ['integer', 'exists:route_fares,id'],
            'target_user_ids' => ['nullable', 'array'],
            'target_user_ids.*' => ['integer', 'exists:users,id'],
            'target_driver_ids' => ['nullable', 'array'],
            'target_driver_ids.*' => ['integer', 'exists:drivers,id'],
            'auto_apply' => ['nullable', 'boolean'],
            'show_on_checkout' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function attributes(array $validated, ?Coupon $existing = null): array
    {
        $bookingTypes = array_values(array_unique(array_filter(array_map(
            static fn ($v) => strtolower(trim((string) $v)),
            (array) ($validated['booking_types'] ?? [])
        ))));
        $carTypes = array_values(array_unique(array_filter(array_map(
            static fn ($v) => strtolower(trim((string) $v)),
            (array) ($validated['car_types'] ?? [])
        ))));
        $routeIds = array_values(array_unique(array_filter(array_map('intval', (array) ($validated['route_fare_ids'] ?? [])))));
        $userIds = array_values(array_unique(array_filter(array_map('intval', (array) ($validated['target_user_ids'] ?? [])))));
        $driverIds = array_values(array_unique(array_filter(array_map('intval', (array) ($validated['target_driver_ids'] ?? [])))));

        $attributes = [
            'code' => $validated['code'],
            'title' => ! empty($validated['title']) ? trim((string) $validated['title']) : null,
            'description' => ! empty($validated['description']) ? trim((string) $validated['description']) : null,
            'type' => $validated['type'],
            'value' => (float) $validated['value'],
            'max_discount' => isset($validated['max_discount']) && $validated['max_discount'] !== '' ? (float) $validated['max_discount'] : null,
            'min_spend' => (float) ($validated['min_spend'] ?? 0),
            'starts_at' => $validated['starts_at'] ?? null,
            'expires_at' => $validated['expires_at'] ?? null,
            'usage_limit' => isset($validated['usage_limit']) && $validated['usage_limit'] !== '' ? (int) $validated['usage_limit'] : null,
            'per_user_limit' => isset($validated['per_user_limit']) && $validated['per_user_limit'] !== '' ? (int) $validated['per_user_limit'] : null,
            'booking_types' => $bookingTypes !== [] ? $bookingTypes : null,
            'car_types' => $carTypes !== [] ? $carTypes : null,
            'route_fare_ids' => $routeIds !== [] ? $routeIds : null,
            'target_user_ids' => $userIds !== [] ? $userIds : null,
            'target_driver_ids' => $driverIds !== [] ? $driverIds : null,
        ];

        if (array_key_exists('auto_apply', $validated)) {
            $attributes['auto_apply'] = (bool) $validated['auto_apply'];
        } elseif ($existing === null) {
            $attributes['auto_apply'] = false;
        }

        if (array_key_exists('show_on_checkout', $validated)) {
            $attributes['show_on_checkout'] = (bool) $validated['show_on_checkout'];
        } elseif ($existing === null) {
            $attributes['show_on_checkout'] = true;
        }

        if (array_key_exists('is_active', $validated)) {
            $attributes['is_active'] = (bool) $validated['is_active'];
        } elseif ($existing === null) {
            $attributes['is_active'] = true;
        }

        return $attributes;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Coupon $coupon): array
    {
        $routeIds = array_values(array_filter(array_map('intval', (array) ($coupon->route_fare_ids ?? []))));
        $userIds = array_values(array_filter(array_map('intval', (array) ($coupon->target_user_ids ?? []))));
        $driverIds = array_values(array_filter(array_map('intval', (array) ($coupon->target_driver_ids ?? []))));

        return array_merge($coupon->toArray(), [
            'redeemable' => $coupon->isRedeemableAt(),
            'remaining_uses' => $coupon->remainingUses(),
            'starts_at_display' => $coupon->starts_at?->format('d M Y, H:i'),
            'expires_at_display' => $coupon->expires_at?->format('d M Y, H:i'),
            'type_label' => $coupon->type === 'percentage' ? 'Percentage' : 'Flat',
            'condition_badges' => $coupon->conditionSummaryList(),
            'route_labels' => $routeIds !== []
                ? RouteFare::whereIn('id', $routeIds)->get()->map(fn ($r) => "{$r->from_city} → {$r->to_city}")->values()->all()
                : [],
            'user_labels' => $userIds !== []
                ? User::whereIn('id', $userIds)->get()->map(fn ($u) => $u->name)->values()->all()
                : [],
            'driver_labels' => $driverIds !== []
                ? Driver::whereIn('id', $driverIds)->get()->map(fn ($d) => $d->name)->values()->all()
                : [],
        ]);
    }

    private function normaliseCode(?string $raw): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', (string) $raw));
    }
}
