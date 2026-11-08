<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\RouteFare;

class RouteCalculationController extends Controller
{
    public function calculate(Request $request)
    {
        $from = $request->query('from', 'Pune');
        $to = $request->query('to', 'Goa');

        $route = RouteFare::where('from_city', $from)->where('to_city', $to)->first();

        if (!$route) {
            $route = (object)[
                'from_city' => $from,
                'to_city' => $to,
                'distance_km' => 450,
                'estimated_time' => '9h 15m',
                'base_toll' => 480
            ];
        }

        return response()->json([
            'success' => true,
            'route' => $route,
            'fleet_estimates' => [
                'sedan' => round($route->distance_km * 12.5 + $route->base_toll),
                'prime' => round($route->distance_km * 15.0 + $route->base_toll),
                'suv' => round($route->distance_km * 18.0 + $route->base_toll),
                'luxury' => round($route->distance_km * 32.0 + $route->base_toll),
            ]
        ]);
    }
}
