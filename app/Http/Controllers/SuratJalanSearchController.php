<?php

namespace App\Http\Controllers;

use App\Contracts\MaterialInClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuratJalanSearchController extends Controller
{
    public function __construct(
        protected MaterialInClient $materialInClient
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $search = (string) $request->query('search', '');

        $references = $this->materialInClient->searchReferenceNumbers($search);

        return response()->json($references);
    }
}
