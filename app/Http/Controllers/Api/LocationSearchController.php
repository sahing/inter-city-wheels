<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LocationDetectionService;
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

    /**
     * Detect initial pickup location via browser GPS coordinates or network IP.
     */
    public function detect(Request $request, LocationDetectionService $detector)
    {
        $validated = $request->validate([
            'mode' => 'nullable|string|in:auto,gps,network',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
        ]);

        $mode = $validated['mode'] ?? 'auto';
        $hasCoords = isset($validated['lat'], $validated['lng']);

        if ($mode === 'gps' && ! $hasCoords) {
            return response()->json([
                'success' => false,
                'code' => 'coordinates_required',
                'message' => 'Latitude and longitude are required for GPS detection.',
            ], 422);
        }

        $detected = ($mode === 'gps' || ($mode === 'auto' && $hasCoords))
            ? $detector->detectFromCoordinates((float) $validated['lat'], (float) $validated['lng'])
            : $detector->detectFromNetwork($request->ip());

        if ($detected === null) {
            return response()->json([
                'success' => false,
                'code' => 'location_undetected',
                'message' => $mode === 'gps'
                    ? 'Could not match your GPS coordinates to a nearby city or area.'
                    : 'Could not detect your approximate location from the network.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'source' => $detected['source'],
            'location' => $detected,
        ]);
    }
}
