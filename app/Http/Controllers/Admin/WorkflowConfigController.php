<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WorkflowStage;
use Illuminate\Http\Request;

class WorkflowConfigController extends Controller
{
    public function index()
    {
        return response()->json(WorkflowStage::orderBy('order_index')->get());
    }

    public function update(Request $request, $id)
    {
        $stage = WorkflowStage::findOrFail($id);
        $stage->update($request->only('stage_name', 'description', 'is_active', 'order_index'));

        return response()->json([
            'success' => true,
            'stage' => $stage,
        ]);
    }
}
