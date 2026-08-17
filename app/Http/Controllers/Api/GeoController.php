<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NominatimService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Proxy público de geocoding (Nominatim vía backend).
 */
class GeoController extends Controller
{
    public function __construct(
        private readonly NominatimService $nominatim,
    ) {}

    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'max:200'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:8'],
        ]);

        return response()->json([
            'data' => $this->nominatim->search(
                $data['q'],
                (int) ($data['limit'] ?? 5),
            ),
        ]);
    }

    public function reverse(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $place = $this->nominatim->reverse((float) $data['lat'], (float) $data['lng']);

        return response()->json([
            'data' => $place,
        ]);
    }
}
