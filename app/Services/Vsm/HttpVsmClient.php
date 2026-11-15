<?php

namespace App\Services\Vsm;

use App\Contracts\VsmClient;
use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

class HttpVsmClient implements VsmClient
{
    public function projects(): array
    {
        $baseUrl = rtrim((string) config('vsm.base_url'), '/');
        $projects = Http::withToken((string) config('vsm.token'))
            ->acceptJson()
            ->timeout(5)
            ->retry(3, 0)
            ->get($baseUrl.'/projects')
            ->throw()
            ->json();

        if (! is_array($projects) || ! array_is_list($projects)) {
            throw new UnexpectedValueException('VSM projects response must be a JSON list.');
        }

        return $projects;
    }
}
