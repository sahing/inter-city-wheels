<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RegionalLocationService;
use Illuminate\Http\Request;

class LocationSearchController extends Controller
{
    /**
     * Search both registered service locations and regional desired locations.
     */
    public function search(Request $request)
    {
        $query = (string) $request->query('q', '');
        $locations = RegionalLocationService::search($query);

        return response()->json([
            'success' => true,
            'query' => $query,
            'count' => count($locations),
            'locations' => $locations,
        ]);
    }
}
