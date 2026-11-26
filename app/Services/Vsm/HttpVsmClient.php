<?php

namespace App\Services\Vsm;

use App\Contracts\VsmClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use UnexpectedValueException;

class HttpVsmClient implements VsmClient
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function projects(): array
    {
        $baseUrl = rtrim((string) config('vsm.base_url', 'http://103.126.117.169:8083/api/vsm'), '/');
        $timeout = (int) config('vsm.timeout', 6);

        $request = Http::acceptJson()
            ->timeout($timeout)
            ->retry(3, 0);

        if ($token = config('vsm.token')) {
            $request = $request->withToken((string) $token);
        }

        $response = $request->get($baseUrl.'/projects')->throw()->json();

        $projects = isset($response['data']) && is_array($response['data'])
            ? $response['data']
            : $response;

        if (! is_array($projects) || ! array_is_list($projects)) {
            throw new UnexpectedValueException('VSM projects response must be a JSON list.');
        }

        return $projects;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function materials(): array
    {
        $baseUrl = rtrim((string) config('vsm.base_url', 'http://103.126.117.169:8083/api/vsm'), '/');
        $timeout = (int) config('vsm.timeout', 8);
        $cacheKey = 'vsm_materials_'.md5($baseUrl);

        return Cache::remember($cacheKey, 300, function () use ($baseUrl, $timeout): array {
            $all = [];
            $page = 1;
            $maxPages = 30;

            try {
                do {
                    $request = Http::acceptJson()
                        ->timeout($timeout)
                        ->retry(2, 100);

                    if ($token = config('vsm.token')) {
                        $request = $request->withToken((string) $token);
                    }

                    $url = $page === 1 ? $baseUrl.'/material' : $baseUrl.'/material?page='.$page;
                    $response = $request->get($url)->throw()->json();

                    $materials = isset($response['data']) && is_array($response['data'])
                        ? $response['data']
                        : $response;

                    if (! is_array($materials) || empty($materials)) {
                        break;
                    }

                    $all = array_merge($all, $materials);
                    $lastPage = (int) ($response['meta']['last_page'] ?? 1);
                    $page++;
                } while ($page <= $lastPage && $page <= $maxPages);
            } catch (\Throwable $e) {
                Log::warning('Failed to fetch VSM materials: '.$e->getMessage());

                return $all;
            }

            return $all;
        });
    }

    /**
     * @return array{bom_numbers: array<int, string>, po_numbers: array<int, string>}
     */
    public function bomAndPoNumbersByProject(string $projectCode): array
    {
        $materials = $this->materials();
        $boms = [];
        $pos = [];
        $allBoms = [];
        $allPos = [];

        $cleanProjectCode = preg_replace('/[^a-zA-Z0-9]/', '', $projectCode);

        foreach ($materials as $item) {
            if (! is_array($item)) {
                continue;
            }

            $bom = ! empty($item['bom_no']) && is_string($item['bom_no']) ? trim($item['bom_no']) : null;
            $po = ! empty($item['po_no']) && is_string($item['po_no']) ? trim($item['po_no']) : null;

            if ($bom !== null && $bom !== '' && ! in_array($bom, $allBoms, true)) {
                $allBoms[] = $bom;
            }
            if ($po !== null && $po !== '' && ! in_array($po, $allPos, true)) {
                $allPos[] = $po;
            }

            $code = (string) ($item['project_code'] ?? '');
            $cleanCode = preg_replace('/[^a-zA-Z0-9]/', '', $code);

            if ($cleanProjectCode !== '' && strcasecmp($cleanCode, $cleanProjectCode) === 0) {
                if ($bom !== null && $bom !== '' && ! in_array($bom, $boms, true)) {
                    $boms[] = $bom;
                }

                if ($po !== null && $po !== '' && ! in_array($po, $pos, true)) {
                    $pos[] = $po;
                }
            }
        }

        $finalBoms = ! empty($boms) ? $boms : $allBoms;
        $finalPos = ! empty($pos) ? $pos : $allPos;

        sort($finalBoms, SORT_NATURAL | SORT_FLAG_CASE);
        sort($finalPos, SORT_NATURAL | SORT_FLAG_CASE);

        return [
            'bom_numbers' => $finalBoms,
            'po_numbers' => $finalPos,
        ];
    }
}
