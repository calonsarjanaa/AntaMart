<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class RegionController extends Controller
{
    private const BASE_URL = 'https://www.emsifa.com/api-wilayah-indonesia/api';
    private const CACHE_TTL_SECONDS = 2592000; // 30 days

    public function provinces(): JsonResponse
    {
        $provinces = Cache::remember('regions.provinces', self::CACHE_TTL_SECONDS, function (): array {
            $data = $this->fetchFromApi('/provinces.json');

            return $data !== [] ? $data : $this->fallbackProvinces();
        });

        return response()->json($provinces);
    }

    public function regencies(string $provinceId): JsonResponse
    {
        if (!preg_match('/^\d{2}$/', $provinceId)) {
            return response()->json([]);
        }

        $regencies = Cache::remember("regions.regencies.{$provinceId}", self::CACHE_TTL_SECONDS, function () use ($provinceId): array {
            return $this->fetchFromApi("/regencies/{$provinceId}.json");
        });

        return response()->json($regencies);
    }

    public function districts(string $regencyId): JsonResponse
    {
        if (!preg_match('/^\d{4}$/', $regencyId)) {
            return response()->json([]);
        }

        $districts = Cache::remember("regions.districts.{$regencyId}", self::CACHE_TTL_SECONDS, function () use ($regencyId): array {
            return $this->fetchFromApi("/districts/{$regencyId}.json");
        });

        return response()->json($districts);
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function fetchFromApi(string $path): array
    {
        try {
            $response = Http::timeout(8)->get(self::BASE_URL . $path);
            if (!$response->successful()) {
                return [];
            }

            $items = $response->json();
            if (!is_array($items)) {
                return [];
            }

            return array_values(array_map(fn ($item): array => [
                'id'   => (string) ($item['id'] ?? ''),
                'name' => $this->formatRegionName((string) ($item['name'] ?? '')),
            ], $items));
        } catch (\Throwable) {
            return [];
        }
    }

    private function formatRegionName(string $name): string
    {
        $title = mb_convert_case(mb_strtolower(trim($name), 'UTF-8'), MB_CASE_TITLE, 'UTF-8');

        return str_replace(['Dki ', 'Di ', 'Kab. '], ['DKI ', 'DI ', 'Kab. '], $title);
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function fallbackProvinces(): array
    {
        return [
            ['id' => '11', 'name' => 'Aceh'],
            ['id' => '12', 'name' => 'Sumatera Utara'],
            ['id' => '13', 'name' => 'Sumatera Barat'],
            ['id' => '14', 'name' => 'Riau'],
            ['id' => '15', 'name' => 'Jambi'],
            ['id' => '16', 'name' => 'Sumatera Selatan'],
            ['id' => '17', 'name' => 'Bengkulu'],
            ['id' => '18', 'name' => 'Lampung'],
            ['id' => '19', 'name' => 'Kepulauan Bangka Belitung'],
            ['id' => '21', 'name' => 'Kepulauan Riau'],
            ['id' => '31', 'name' => 'DKI Jakarta'],
            ['id' => '32', 'name' => 'Jawa Barat'],
            ['id' => '33', 'name' => 'Jawa Tengah'],
            ['id' => '34', 'name' => 'DI Yogyakarta'],
            ['id' => '35', 'name' => 'Jawa Timur'],
            ['id' => '36', 'name' => 'Banten'],
            ['id' => '51', 'name' => 'Bali'],
            ['id' => '52', 'name' => 'Nusa Tenggara Barat'],
            ['id' => '53', 'name' => 'Nusa Tenggara Timur'],
            ['id' => '61', 'name' => 'Kalimantan Barat'],
            ['id' => '62', 'name' => 'Kalimantan Tengah'],
            ['id' => '63', 'name' => 'Kalimantan Selatan'],
            ['id' => '64', 'name' => 'Kalimantan Timur'],
            ['id' => '65', 'name' => 'Kalimantan Utara'],
            ['id' => '71', 'name' => 'Sulawesi Utara'],
            ['id' => '72', 'name' => 'Sulawesi Tengah'],
            ['id' => '73', 'name' => 'Sulawesi Selatan'],
            ['id' => '74', 'name' => 'Sulawesi Tenggara'],
            ['id' => '75', 'name' => 'Gorontalo'],
            ['id' => '76', 'name' => 'Sulawesi Barat'],
            ['id' => '81', 'name' => 'Maluku'],
            ['id' => '82', 'name' => 'Maluku Utara'],
            ['id' => '91', 'name' => 'Papua Barat'],
            ['id' => '94', 'name' => 'Papua'],
        ];
    }
}

