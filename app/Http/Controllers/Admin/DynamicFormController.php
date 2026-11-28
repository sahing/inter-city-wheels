<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DynamicFormField;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DynamicFormController extends Controller
{
    public function index()
    {
        return response()->json(
            DynamicFormField::with('routeFare')->orderBy('order_index')->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'label' => 'required|string|max:255',
            'field_type' => 'required|in:text,number,select,checkbox,price',
            'applies_to' => 'nullable|string|max:40',
            'conditional_logic' => 'nullable|string|max:120',
            'is_required' => 'nullable|boolean',
            'is_price_field' => 'nullable|boolean',
            'price_amount' => 'nullable|numeric|min:0',
            'price_mode' => 'nullable|string|in:flat,per_km,percentage',
            'charge_behavior' => 'nullable|string|in:auto,optional',
            'booking_type_scope' => 'nullable|string|in:all,oneway,roundtrip,local,airport,airport_pickup,airport_drop,outstation',
            'route_type_scope' => 'nullable|string|in:all,route_table,configured_route,custom_distance,custom_route,specific_route,local_package,airport_hub',
            'route_fare_id' => 'nullable|integer|exists:route_fares,id',
            'is_active' => 'nullable|boolean',
        ]);

        $isPriceField = $request->boolean('is_price_field') || $validated['field_type'] === 'price';
        $baseKey = Str::slug($validated['label'], '_') ?: 'custom_field';
        $fieldKey = $baseKey;
        $suffix = 1;
        while (DynamicFormField::where('field_key', $fieldKey)->exists()) {
            $fieldKey = $baseKey.'_'.(++$suffix);
        }

        $routeScope = $validated['route_type_scope'] ?? 'all';
        $routeFareId = $routeScope === 'specific_route' ? ($validated['route_fare_id'] ?? null) : null;
        if ($routeScope === 'specific_route' && ! $routeFareId) {
            return response()->json([
                'success' => false,
                'message' => 'Select a specific route when Route Scope is set to Specific Route.',
                'errors' => ['route_fare_id' => ['Please select a specific route.']],
            ], 422);
        }

        $bookingScope = $validated['booking_type_scope'] ?? 'all';
        $appliesTo = $validated['applies_to'] ?? ($bookingScope !== 'all' ? $bookingScope : 'all');

        $field = DynamicFormField::create([
            'field_key' => $fieldKey,
            'label' => trim($validated['label']),
            'field_type' => $isPriceField && $validated['field_type'] === 'price'
                ? 'price'
                : $validated['field_type'],
            'is_price_field' => $isPriceField,
            'price_amount' => $isPriceField ? (float) ($validated['price_amount'] ?? 0) : 0.0,
            'price_mode' => $validated['price_mode'] ?? 'flat',
            'charge_behavior' => $validated['charge_behavior'] ?? 'auto',
            'booking_type_scope' => $bookingScope,
            'route_type_scope' => $routeScope,
            'route_fare_id' => $routeFareId,
            'applies_to' => $appliesTo,
            'conditional_logic' => $validated['conditional_logic']
                ?? ($isPriceField ? $this->buildConditionSummary($bookingScope, $routeScope, $validated['charge_behavior'] ?? 'auto') : 'All Bookings'),
            'is_required' => $request->boolean('is_required'),
            'order_index' => ((int) DynamicFormField::max('order_index')) + 1,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
        ]);

        $field->load('routeFare');

        return response()->json([
            'success' => true,
            'message' => $isPriceField
                ? 'Additional price field created!'
                : 'Dynamic field created!',
            'field' => $field,
        ]);
    }

    public function update(Request $request, $id)
    {
        $field = DynamicFormField::findOrFail($id);

        $validated = $request->validate([
            'label' => 'sometimes|required|string|max:255',
            'field_type' => 'sometimes|required|in:text,number,select,checkbox,price',
            'applies_to' => 'nullable|string|max:40',
            'conditional_logic' => 'nullable|string|max:120',
            'is_required' => 'nullable|boolean',
            'is_price_field' => 'nullable|boolean',
            'price_amount' => 'nullable|numeric|min:0',
            'price_mode' => 'nullable|string|in:flat,per_km,percentage',
            'charge_behavior' => 'nullable|string|in:auto,optional',
            'booking_type_scope' => 'nullable|string|in:all,oneway,roundtrip,local,airport,airport_pickup,airport_drop,outstation',
            'route_type_scope' => 'nullable|string|in:all,route_table,configured_route,custom_distance,custom_route,specific_route,local_package,airport_hub',
            'route_fare_id' => 'nullable|integer|exists:route_fares,id',
            'is_active' => 'nullable|boolean',
        ]);

        $routeScope = $validated['route_type_scope'] ?? $field->route_type_scope ?? 'all';
        $routeFareId = $routeScope === 'specific_route'
            ? ($validated['route_fare_id'] ?? $field->route_fare_id)
            : null;
        if ($routeScope === 'specific_route' && ! $routeFareId) {
            return response()->json([
                'success' => false,
                'message' => 'Select a specific route when Route Scope is set to Specific Route.',
                'errors' => ['route_fare_id' => ['Please select a specific route.']],
            ], 422);
        }

        if (isset($validated['label'])) {
            $field->label = trim($validated['label']);
        }
        if (isset($validated['field_type'])) {
            $field->field_type = $validated['field_type'];
        }
        if ($request->has('is_price_field')) {
            $field->is_price_field = $request->boolean('is_price_field');
        }
        if (array_key_exists('price_amount', $validated)) {
            $field->price_amount = (float) ($validated['price_amount'] ?? 0);
        }
        if (isset($validated['price_mode'])) {
            $field->price_mode = $validated['price_mode'];
        }
        if (isset($validated['charge_behavior'])) {
            $field->charge_behavior = $validated['charge_behavior'];
        }
        if (isset($validated['booking_type_scope'])) {
            $field->booking_type_scope = $validated['booking_type_scope'];
        }
        if (isset($validated['route_type_scope'])) {
            $field->route_type_scope = $routeScope;
        }
        $field->route_fare_id = $routeFareId;
        if (isset($validated['applies_to'])) {
            $field->applies_to = $validated['applies_to'];
        }
        if (isset($validated['conditional_logic'])) {
            $field->conditional_logic = $validated['conditional_logic'];
        }
        if ($request->has('is_required')) {
            $field->is_required = $request->boolean('is_required');
        }
        if ($request->has('is_active')) {
            $field->is_active = $request->boolean('is_active');
        }

        $field->save();
        $field->load('routeFare');

        return response()->json([
            'success' => true,
            'message' => 'Dynamic field updated!',
            'field' => $field,
        ]);
    }

    public function toggleStatus($id)
    {
        $field = DynamicFormField::findOrFail($id);
        $field->is_active = ! $field->is_active;
        $field->save();
        $field->load('routeFare');

        return response()->json([
            'success' => true,
            'message' => 'Field status set to '.($field->is_active ? 'Active' : 'Inactive'),
            'field' => $field,
        ]);
    }

    public function destroy($id)
    {
        $field = DynamicFormField::findOrFail($id);
        $field->delete();

        return response()->json([
            'success' => true,
            'message' => 'Dynamic field deleted!',
        ]);
    }

    private function buildConditionSummary(string $bookingScope, string $routeScope, string $behavior): string
    {
        $b = $bookingScope === 'all' ? 'All Booking Types' : ucwords(str_replace('_', ' ', $bookingScope));
        $r = $routeScope === 'all' ? 'All Routes' : ucwords(str_replace('_', ' ', $routeScope));
        $m = $behavior === 'optional' ? 'Optional Add-on' : 'Auto Surcharge';

        return "{$m} · {$b} · {$r}";
    }
}
