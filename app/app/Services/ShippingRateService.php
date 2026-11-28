<?php

namespace App\Services;

use App\Adapters\Shipping\RajaOngkirAdapter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ShippingRateService
{
    public const LOCAL_COURIER_CODE = 'antamart';
    public const LOCAL_OPTION_CODE = 'antamart:local';
    public const LOCAL_MAX_DISTANCE_KM = 20.0;

    public function __construct(
        protected RajaOngkirAdapter $rajaOngkir
    ) {}

    /**
     * Calculate all available shipping options (Kurir AntaMart if <= 20 km + RajaOngkir expeditions).
     *
     * @param array<string, mixed> $destinationAddress
     * @param int $weightGrams
     * @return array{
     *     distance_km: float|null,
     *     local_available: bool,
     *     weight_grams: int,
     *     rajaongkir_status: string,
     *     options: array<int, array<string, mixed>>
     * }
     */
    public function getAvailableRates(array $destinationAddress, int $weightGrams = 500): array
    {
        $weightGrams = max(100, $weightGrams);

        $lat = isset($destinationAddress['latitude']) && is_numeric($destinationAddress['latitude'])
            ? (float) $destinationAddress['latitude']
            : null;
        $lng = isset($destinationAddress['longitude']) && is_numeric($destinationAddress['longitude'])
            ? (float) $destinationAddress['longitude']
            : null;

        $distanceKm = ($lat !== null && $lng !== null)
            ? $this->calculateDistanceKm($lat, $lng)
            : null;

        $options = [];
        $localOption = $distanceKm !== null ? $this->buildLocalCourierOption($distanceKm) : null;
        if ($localOption !== null) {
            $options[] = $localOption;
        }

        $expeditionResult = $this->rajaOngkir->getRates($destinationAddress, $weightGrams);
        foreach ($expeditionResult['options'] as $opt) {
            $options[] = $opt;
        }

        return [
            'distance_km'       => $distanceKm,
            'local_available'   => $localOption !== null,
            'weight_grams'      => $weightGrams,
            'rajaongkir_status' => $expeditionResult['status'],
            'options'           => $options,
        ];
    }

    /**
     * Authoritatively resolve a customer-selected shipping option code on the backend.
     *
     * @param string $selectedCode
     * @param array<string, mixed> $destinationAddress
     * @param int $weightGrams
     * @return array<string, mixed>|null
     */
    public function resolveSelectedOption(string $selectedCode, array $destinationAddress, int $weightGrams = 500): ?array
    {
        $selectedCode = trim($selectedCode);
        if ($selectedCode === '') {
            return null;
        }

        $rates = $this->getAvailableRates($destinationAddress, $weightGrams);
        foreach ($rates['options'] as $option) {
            if (($option['code'] ?? '') === $selectedCode) {
                return $option;
            }
        }

        return null;
    }

    /**
     * Build Kurir AntaMart local shipping option based on distance tier:
     * - 0 km - 5 km   = Rp 5.000
     * - >5 km - 10 km = Rp 10.000
     * - >10 km - 20 km = Rp 20.000
     * - >20 km        = null (unavailable)
     *
     * @return array<string, mixed>|null
     */
    public function buildLocalCourierOption(float $distanceKm): ?array
    {
        if ($distanceKm < 0 || $distanceKm > self::LOCAL_MAX_DISTANCE_KM) {
            return null;
        }

        if ($distanceKm <= 5.0) {
            $cost = 5000.0;
            $tier = 'Jarak 0–5 km';
            $etd = 'Hari ini (1–2 jam)';
        } elseif ($distanceKm <= 10.0) {
            $cost = 10000.0;
            $tier = 'Jarak >5–10 km';
            $etd = 'Hari ini (2–3 jam)';
        } else {
            $cost = 20000.0;
            $tier = 'Jarak >10–20 km';
            $etd = 'Hari ini (3–5 jam)';
        }

        return [
            'code'         => self::LOCAL_OPTION_CODE,
            'courier'      => self::LOCAL_COURIER_CODE,
            'courier_name' => 'Kurir AntaMart',
            'service'      => $tier,
            'description'  => 'Diantar langsung oleh kurir toko AntaMart (' . number_format($distanceKm, 1) . ' km)',
            'cost'         => $cost,
            'etd'          => $etd,
            'type'         => 'local',
            'distance_km'  => $distanceKm,
        ];
    }

    /**
     * Calculate distance (km) from AntaMart store to destination coordinates using Haversine formula.
     */
    public function calculateDistanceKm(float $destLat, float $destLng): float
    {
        $storeLat = (float) config('services.store.latitude', -1.606188);
        $storeLng = (float) config('services.store.longitude', 103.521922);

        $earthRadiusKm = 6371.0;
        $dLat = deg2rad($destLat - $storeLat);
        $dLng = deg2rad($destLng - $storeLng);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($storeLat)) * cos(deg2rad($destLat))
            * sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadiusKm * $c, 2);
    }

    /**
     * Geocode an Indonesian region query via Nominatim (OpenStreetMap) with caching.
     *
     * @return array{latitude: float, longitude: float}|null
     */
    public function geocodeRegion(string $query): ?array
    {
        $query = trim($query);
        if ($query === '') {
            return null;
        }

        $cacheKey = 'geocode.nominatim.' . md5(strtolower($query));

        return Cache::remember($cacheKey, now()->addDays(14), function () use ($query): ?array {
            try {
                $response = Http::timeout(6)
                    ->withHeaders([
                        'User-Agent' => 'AntaMartStorefront/1.0 (+https://antamart.local)',
                    ])
                    ->get('https://nominatim.openstreetmap.org/search', [
                        'q'            => $query . ', Indonesia',
                        'format'       => 'json',
                        'limit'        => 1,
                        'countrycodes' => 'id',
                    ]);

                if (!$response->successful()) {
                    return null;
                }

                $first = $response->json('0');
                if (!is_array($first) || !isset($first['lat'], $first['lon'])) {
                    return null;
                }

                return [
                    'latitude'  => round((float) $first['lat'], 7),
                    'longitude' => round((float) $first['lon'], 7),
                ];
            } catch (\Throwable) {
                return null;
            }
        });
    }
}

