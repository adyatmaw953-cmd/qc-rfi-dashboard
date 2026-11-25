<?php

namespace App\Http\Controllers;

use App\Contracts\MaterialInClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierSearchController extends Controller
{
    public function __construct(
        protected MaterialInClient $materialInClient
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $search = (string) $request->query('search', '');

        $suppliers = $this->materialInClient->searchSuppliers($search);

        return response()->json($suppliers);
    }
}
