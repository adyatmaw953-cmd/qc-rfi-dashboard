<?php

namespace App\Http\Controllers;

use App\Contracts\VsmClient;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectMaterialController extends Controller
{
    public function __construct(
        protected VsmClient $vsmClient
    ) {}

    public function __invoke(Request $request, Project $project): JsonResponse
    {
        $materialDetails = $this->vsmClient->bomAndPoNumbersByProject($project->project_code);

        return response()->json([
            'project_id' => $project->id,
            'project_code' => $project->project_code,
            'project_name' => $project->project_name,
            'customer' => $project->customer,
            'bom_numbers' => $materialDetails['bom_numbers'] ?? [],
            'po_numbers' => $materialDetails['po_numbers'] ?? [],
        ]);
    }
}
