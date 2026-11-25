<?php

namespace App\Services\MaterialIn;

use App\Contracts\MaterialInClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class HttpMaterialInClient implements MaterialInClient
{
    /**
     * @return array<int, string>
     */
    public function searchSuppliers(string $query = ''): array
    {
        $baseUrl = (string) config('material_in.base_url', 'http://103.126.117.169/api/qc/material-ins');
        $timeout = (int) config('material_in.timeout', 5);

        try {
            $response = Http::acceptJson()
                ->timeout($timeout)
                ->retry(2, 100)
                ->get($baseUrl, [
                    'search' => $query,
                    'per_page' => 100,
                ]);

            if (! $response->successful()) {
                Log::warning('Material-in API returned unsuccessful status', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [];
            }

            $json = $response->json();
            $data = $json['data'] ?? [];

            if (! is_array($data)) {
                return [];
            }

            return $this->extractUniqueSuppliers($data);
        } catch (Throwable $e) {
            Log::error('Failed to fetch suppliers from material-in API: '.$e->getMessage());

            return [];
        }
    }

    /**
     * @param  array<int, mixed>  $data
     * @return array<int, string>
     */
    protected function extractUniqueSuppliers(array $data): array
    {
        $suppliers = [];

        foreach ($data as $item) {
            if (! is_array($item)) {
                continue;
            }

            $supplier = $item['supplier'] ?? null;
            if (! is_string($supplier)) {
                continue;
            }

            $supplier = trim($supplier);
            if ($supplier === '') {
                continue;
            }

            $key = strtolower($supplier);
            if (! isset($suppliers[$key])) {
                $suppliers[$key] = $supplier;
            }
        }

        $list = array_values($suppliers);
        sort($list, SORT_NATURAL | SORT_FLAG_CASE);

        return $list;
    }
}
