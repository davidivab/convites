<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * Proxy a Nominatim (OpenStreetMap) con cache y User-Agent propio.
 *
 * Política: https://operations.osmfoundation.org/policies/nominatim/
 * - Máx. ~1 req/s (throttle en rutas)
 * - Sin autocomplete letra a letra
 * - Siempre vía este servicio (nunca desde el browser)
 */
class NominatimService
{
    /**
     * @return list<array{label: string, lat: float, lng: float, barrio: ?string, municipio: ?string}>
     */
    public function search(string $query, int $limit = 5): array
    {
        $query = trim($query);

        if (mb_strlen($query) < 3) {
            throw ValidationException::withMessages([
                'q' => ['Escribí al menos 3 caracteres para buscar.'],
            ]);
        }

        $limit = max(1, min(8, $limit));
        $cacheKey = 'nominatim:search:'.md5(mb_strtolower($query).'|'.$limit);

        return Cache::remember($cacheKey, now()->addDay(), function () use ($query, $limit) {
            $response = $this->client()->get('/search', [
                'q' => $query,
                'format' => 'json',
                'addressdetails' => 1,
                'limit' => $limit,
                'countrycodes' => 'co',
                'viewbox' => config('services.nominatim.viewbox'),
                'bounded' => 1,
            ])->throw();

            /** @var list<array<string, mixed>> $rows */
            $rows = $response->json() ?? [];

            return collect($rows)
                ->map(fn (array $row) => $this->normalize($row))
                ->filter()
                ->values()
                ->all();
        });
    }

    /**
     * @return array{label: string, lat: float, lng: float, barrio: ?string, municipio: ?string}|null
     */
    public function reverse(float $lat, float $lng): ?array
    {
        $cacheKey = sprintf('nominatim:reverse:%.5f:%.5f', $lat, $lng);

        return Cache::remember($cacheKey, now()->addDay(), function () use ($lat, $lng) {
            try {
                $response = $this->client()->get('/reverse', [
                    'lat' => $lat,
                    'lon' => $lng,
                    'format' => 'json',
                    'addressdetails' => 1,
                    'zoom' => 18,
                ])->throw();
            } catch (RequestException) {
                return null;
            }

            /** @var array<string, mixed>|null $row */
            $row = $response->json();

            if (! is_array($row) || isset($row['error'])) {
                return null;
            }

            return $this->normalize($row);
        });
    }

    private function client(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.nominatim.base_url'), '/'))
            ->timeout(8)
            ->withHeaders([
                'User-Agent' => (string) config('services.nominatim.user_agent'),
                'Accept-Language' => 'es',
            ]);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{label: string, lat: float, lng: float, barrio: ?string, municipio: ?string}|null
     */
    private function normalize(array $row): ?array
    {
        if (! isset($row['lat'], $row['lon'])) {
            return null;
        }

        /** @var array<string, mixed> $address */
        $address = is_array($row['address'] ?? null) ? $row['address'] : [];

        $barrio = $address['suburb']
            ?? $address['neighbourhood']
            ?? $address['quarter']
            ?? $address['village']
            ?? $address['hamlet']
            ?? null;

        $municipio = $address['city']
            ?? $address['town']
            ?? $address['municipality']
            ?? $address['county']
            ?? null;

        return [
            'label' => (string) ($row['display_name'] ?? 'Punto en el mapa'),
            'lat' => (float) $row['lat'],
            'lng' => (float) $row['lon'],
            'barrio' => is_string($barrio) ? $barrio : null,
            'municipio' => is_string($municipio) ? $municipio : null,
        ];
    }
}
