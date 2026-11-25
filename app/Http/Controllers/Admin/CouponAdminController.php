<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
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
            $query->where('is_active', $request->status === 'active');
        }

        $coupons = $query->get()->map(fn (Coupon $coupon) => $this->present($coupon))->values();

        return response()->json([
            'success' => true,
            'coupons' => $coupons,
            'meta' => [
                'total' => $coupons->count(),
                'active' => $coupons->where('is_active', true)->count(),
                'redeemable' => $coupons->where('redeemable', true)->count(),
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
            'expires_at' => ['nullable', 'date'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function attributes(array $validated, ?Coupon $existing = null): array
    {
        $attributes = [
            'code' => $validated['code'],
            'type' => $validated['type'],
            'value' => (float) $validated['value'],
            'max_discount' => isset($validated['max_discount']) ? (float) $validated['max_discount'] : null,
            'min_spend' => (float) ($validated['min_spend'] ?? 0),
            'expires_at' => $validated['expires_at'] ?? null,
            'usage_limit' => isset($validated['usage_limit']) ? (int) $validated['usage_limit'] : null,
        ];

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
        return array_merge($coupon->toArray(), [
            'redeemable' => $coupon->isRedeemableAt(),
            'remaining_uses' => $coupon->remainingUses(),
            'expires_at_display' => $coupon->expires_at?->format('d M Y, H:i'),
            'type_label' => $coupon->type === 'percentage' ? 'Percentage' : 'Flat',
        ]);
    }

    private function normaliseCode(?string $raw): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', (string) $raw));
    }
}
