<?php

namespace App\Adapters\Shipping;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RajaOngkirAdapter
{
    public function isConfigured(): bool
    {
        return !empty(config('services.rajaongkir.api_key'));
    }

    /**
     * Fetch domestic expedition rates from RajaOngkir Komerce API V2.
     *
     * @param array<string, mixed> $destinationAddress
     * @param int $weightGrams
     * @return array{status: string, options: array<int, array<string, mixed>>}
     */
    public function getRates(array $destinationAddress, int $weightGrams): array
    {
        if (!$this->isConfigured()) {
            return ['status' => 'unconfigured', 'options' => []];
        }

        $originId = $this->resolveOriginId();
        $destinationId = $this->resolveDestinationId($destinationAddress);

        if (!$originId || !$destinationId) {
            return ['status' => 'destination_not_found', 'options' => []];
        }

        $weight = max(100, $weightGrams);
        $couriers = (string) config('services.rajaongkir.couriers', 'jne:jnt:sicepat:pos:tiki:anteraja');
        $cacheKey = "rajaongkir.cost.{$originId}.{$destinationId}.{$weight}." . md5($couriers);

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return ['status' => 'ok', 'options' => $cached];
        }

        try {
            $response = Http::timeout(10)
                ->asForm()
                ->withHeaders([
                    'key' => (string) config('services.rajaongkir.api_key'),
                ])
                ->post($this->baseUrl() . '/calculate/domestic-cost', [
                    'origin'      => $originId,
                    'destination' => $destinationId,
                    'weight'      => $weight,
                    'courier'     => $couriers,
                ]);

            if (!$response->successful()) {
                Log::warning('RajaOngkir calculate domestic cost failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                return ['status' => 'unavailable', 'options' => []];
            }

            $rows = $response->json('data');
            if (!is_array($rows)) {
                return ['status' => 'unavailable', 'options' => []];
            }

            $options = [];
            $seen = [];

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $courierCode = strtolower(trim((string) ($row['code'] ?? '')));
                $service = trim((string) ($row['service'] ?? ''));
                $cost = (float) ($row['cost'] ?? 0);

                if ($courierCode === '' || $service === '' || $cost <= 0) {
                    continue;
                }

                $optionCode = "{$courierCode}:{$service}";
                if (isset($seen[$optionCode])) {
                    continue;
                }
                $seen[$optionCode] = true;

                $etd = trim((string) ($row['etd'] ?? ''));
                if ($etd !== '' && !str_contains(strtolower($etd), 'hari') && !str_contains(strtolower($etd), 'day')) {
                    $etd .= ' hari';
                }

                $options[] = [
                    'code'         => $optionCode,
                    'courier'      => $courierCode,
                    'courier_name' => (string) ($row['name'] ?? strtoupper($courierCode)),
                    'service'      => $service,
                    'description'  => (string) ($row['description'] ?? ''),
                    'cost'         => $cost,
                    'etd'          => $etd !== '' ? $etd : '2-5 hari',
                    'type'         => 'expedition',
                    'distance_km'  => null,
                ];
            }

            usort($options, fn (array $a, array $b): int => $a['cost'] <=> $b['cost']);

            if ($options !== []) {
                Cache::put($cacheKey, $options, now()->addMinutes(30));
            }

            return ['status' => 'ok', 'options' => $options];
        } catch (\Throwable $e) {
            Log::warning('RajaOngkir request exception', ['message' => $e->getMessage()]);

            return ['status' => 'unavailable', 'options' => []];
        }
    }

    public function resolveOriginId(): ?int
    {
        $configured = config('services.rajaongkir.origin_id');
        if (is_numeric($configured) && (int) $configured > 0) {
            return (int) $configured;
        }

        $postalCode = trim((string) config('services.store.origin_postal_code', '36361'));
        $search = trim((string) config('services.store.origin_search', 'Mendalo Darat Jambi Luar Kota'));

        return Cache::remember("rajaongkir.origin.{$postalCode}", now()->addDays(30), function () use ($postalCode, $search): ?int {
            $id = $this->searchDestinationId($postalCode, ['district' => 'Jambi Luar Kota', 'city' => 'Muaro Jambi']);
            if ($id) {
                return $id;
            }

            return $this->searchDestinationId($search, ['district' => 'Jambi Luar Kota', 'city' => 'Muaro Jambi']);
        });
    }

    /**
     * @param array<string, mixed> $address
     */
    public function resolveDestinationId(array $address): ?int
    {
        $district = $this->cleanRegionTerm((string) ($address['district'] ?? ''));
        $city = $this->cleanRegionTerm((string) ($address['city'] ?? ''));
        $province = $this->cleanRegionTerm((string) ($address['province'] ?? ''));
        $postalCode = trim((string) ($address['postal_code'] ?? ''));

        $queries = array_values(array_filter([
            $postalCode !== '' ? $postalCode : null,
            trim("{$district} {$city}") ?: null,
            $district ?: null,
            $city ?: null,
        ]));

        if ($queries === []) {
            return null;
        }

        $cacheKey = 'rajaongkir.dest.' . md5(strtolower(implode('|', [$district, $city, $province, $postalCode])));

        return Cache::remember($cacheKey, now()->addDays(7), function () use ($queries, $district, $city, $province): ?int {
            foreach ($queries as $query) {
                $id = $this->searchDestinationId($query, [
                    'district' => $district,
                    'city'     => $city,
                    'province' => $province,
                ]);
                if ($id) {
                    return $id;
                }
            }

            return null;
        });
    }

    /**
     * @param array<string, string> $hints
     */
    private function searchDestinationId(string $query, array $hints = []): ?int
    {
        if (trim($query) === '') {
            return null;
        }

        try {
            $response = Http::timeout(8)
                ->withHeaders([
                    'key' => (string) config('services.rajaongkir.api_key'),
                ])
                ->get($this->baseUrl() . '/destination/domestic-destination', [
                    'search' => $query,
                    'limit'  => 10,
                    'offset' => 0,
                ]);

            if (!$response->successful()) {
                return null;
            }

            $items = $response->json('data');
            if (!is_array($items) || $items === []) {
                return null;
            }

            $bestId = null;
            $bestScore = -1;

            foreach ($items as $item) {
                if (!is_array($item) || empty($item['id'])) {
                    continue;
                }

                $score = 0;
                $label = strtolower((string) ($item['label'] ?? ''));
                $itemDistrict = strtolower((string) ($item['district_name'] ?? ''));
                $itemCity = strtolower((string) ($item['city_name'] ?? ''));
                $itemProvince = strtolower((string) ($item['province_name'] ?? ''));

                if (!empty($hints['district'])) {
                    $h = strtolower($hints['district']);
                    if ($itemDistrict === $h) {
                        $score += 5;
                    } elseif (str_contains($itemDistrict, $h) || str_contains($label, $h)) {
                        $score += 3;
                    }
                }
                if (!empty($hints['city'])) {
                    $h = strtolower($hints['city']);
                    if ($itemCity === $h || str_contains($itemCity, $h)) {
                        $score += 4;
                    } elseif (str_contains($label, $h)) {
                        $score += 2;
                    }
                }
                if (!empty($hints['province'])) {
                    $h = strtolower($hints['province']);
                    if (str_contains($itemProvince, $h) || str_contains($label, $h)) {
                        $score += 1;
                    }
                }

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestId = (int) $item['id'];
                }
            }

            return $bestId;
        } catch (\Throwable) {
            return null;
        }
    }

    private function cleanRegionTerm(string $value): string
    {
        $cleaned = preg_replace('/^(kab\.|kabupaten|kota|kec\.|kecamatan)\s+/i', '', trim($value));

        return trim((string) $cleaned);
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.rajaongkir.base_url', 'https://rajaongkir.komerce.id/api/v1'), '/');
    }
}

