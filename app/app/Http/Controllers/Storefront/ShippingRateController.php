<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\ShippingRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShippingRateController extends Controller
{
    public function rates(Request $request, ShippingRateService $shippingRateService): JsonResponse
    {
        $validated = $request->validate([
            'province'     => ['nullable', 'string', 'max:100'],
            'city'         => ['nullable', 'string', 'max:100'],
            'district'     => ['nullable', 'string', 'max:100'],
            'village'      => ['nullable', 'string', 'max:150'],
            'postal_code'  => ['nullable', 'string', 'max:20'],
            'latitude'     => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'    => ['nullable', 'numeric', 'between:-180,180'],
            'weight_grams' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ]);

        $weightGrams = (int) ($validated['weight_grams'] ?? 500);
        $result = $shippingRateService->getAvailableRates($validated, $weightGrams);

        return response()->json($result);
    }

    public function geocode(Request $request, ShippingRateService $shippingRateService): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'max:200'],
        ]);

        $coords = $shippingRateService->geocodeRegion($validated['q']);

        if (!$coords) {
            return response()->json(['found' => false], 200);
        }

        return response()->json([
            'found'     => true,
            'latitude'  => $coords['latitude'],
            'longitude' => $coords['longitude'],
        ]);
    }
}

