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
     * @return array<int, array{reference_number: string, supplier: ?string, po_number: ?string, date: ?string}>
     */
    public function searchReferenceNumbers(string $query = ''): array
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

            return $this->extractReferenceNumbers($data);
        } catch (Throwable $e) {
            Log::error('Failed to fetch reference numbers from material-in API: '.$e->getMessage());

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

    /**
     * @param  array<int, mixed>  $data
     * @return array<int, array{reference_number: string, supplier: ?string, po_number: ?string, date: ?string}>
     */
    protected function extractReferenceNumbers(array $data): array
    {
        $references = [];

        foreach ($data as $item) {
            if (! is_array($item)) {
                continue;
            }

            $ref = $item['reference_number'] ?? null;
            if (! is_string($ref)) {
                continue;
            }

            $ref = trim($ref);
            if ($ref === '') {
                continue;
            }

            $key = strtolower($ref);
            if (! isset($references[$key])) {
                $references[$key] = [
                    'reference_number' => $ref,
                    'supplier' => ! empty($item['supplier']) && is_string($item['supplier']) ? trim($item['supplier']) : null,
                    'po_number' => ! empty($item['po_number']) && is_string($item['po_number']) ? trim($item['po_number']) : null,
                    'date' => ! empty($item['date']) && is_string($item['date']) ? trim($item['date']) : null,
                ];
            }
        }

        $list = array_values($references);
        usort($list, fn (array $a, array $b): int => strnatcasecmp($a['reference_number'], $b['reference_number']));

        return $list;
    }
}
