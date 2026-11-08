<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\DynamicFormField;
use Illuminate\Support\Str;

class DynamicFormController extends Controller
{
    public function index()
    {
        return response()->json(DynamicFormField::orderBy('order_index')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'label' => 'required|string|max:255',
            'field_type' => 'required|in:text,number,select,checkbox',
            'applies_to' => 'nullable|string',
            'conditional_logic' => 'nullable|string',
            'is_required' => 'nullable|boolean'
        ]);

        $fieldKey = Str::slug($validated['label'], '_');

        $field = DynamicFormField::create([
            'field_key' => $fieldKey,
            'label' => $validated['label'],
            'field_type' => $validated['field_type'],
            'applies_to' => $validated['applies_to'] ?? 'all',
            'conditional_logic' => $validated['conditional_logic'] ?? 'All Bookings',
            'is_required' => $request->boolean('is_required'),
            'order_index' => DynamicFormField::max('order_index') + 1,
            'is_active' => true
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Dynamic field created!',
            'field' => $field
        ]);
    }

    public function destroy($id)
    {
        $field = DynamicFormField::findOrFail($id);
        $field->delete();

        return response()->json([
            'success' => true,
            'message' => 'Dynamic field deleted!'
        ]);
    }
}
