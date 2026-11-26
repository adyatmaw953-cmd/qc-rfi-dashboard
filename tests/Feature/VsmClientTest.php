<?php

namespace Tests\Feature;

use App\Contracts\VsmClient;
use App\Models\Project;
use App\Models\User;
use App\Services\Vsm\FakeVsmClient;
use App\Services\Vsm\HttpVsmClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VsmClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_fake_driver_resolves_example_projects(): void
    {
        config(['vsm.driver' => 'fake']);

        $client = app(VsmClient::class);

        $this->assertInstanceOf(FakeVsmClient::class, $client);
        $this->assertSame(20001, $client->projects()[0]['external_id']);
        $this->assertSame('Proyek Contoh Utama', $client->projects()[0]['project_name']);
    }

    public function test_http_client_retries_and_sends_a_bearer_token(): void
    {
        config([
            'vsm.base_url' => 'https://vsm.test/api/',
            'vsm.token' => 'test-bearer-token',
        ]);

        $attempts = 0;
        Http::preventStrayRequests();
        Http::fake([
            'https://vsm.test/api/projects' => function (Request $request) use (&$attempts) {
                $attempts++;

                if ($attempts < 3) {
                    return Http::response(['message' => 'temporary failure'], 503);
                }

                return Http::response([
                    [
                        'external_id' => 321,
                        'project_code' => 'VSM-321',
                        'project_name' => 'Proyek HTTP',
                        'customer' => 'PT HTTP',
                    ],
                ]);
            },
        ]);

        $projects = (new HttpVsmClient)->projects();

        $this->assertSame('Proyek HTTP', $projects[0]['project_name']);
        $this->assertSame(3, $attempts);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://vsm.test/api/projects'
            && $request->hasHeader('Authorization', 'Bearer test-bearer-token'));
    }

    public function test_fake_driver_resolves_bom_and_po_numbers(): void
    {
        config(['vsm.driver' => 'fake']);

        $client = app(VsmClient::class);

        $details = $client->bomAndPoNumbersByProject('VSM-PRJ-001');

        $this->assertEquals(['BOM-001-A', 'BOM-001-B'], $details['bom_numbers']);
        $this->assertEquals(['PO-001-X'], $details['po_numbers']);
    }

    public function test_http_client_fetches_and_extracts_materials_by_project(): void
    {
        config([
            'vsm.base_url' => 'https://vsm.test/api',
            'vsm.token' => null,
        ]);
        Cache::forget('vsm_materials_'.md5('https://vsm.test/api'));

        Http::preventStrayRequests();
        Http::fake([
            'https://vsm.test/api/material' => Http::response([
                'success' => true,
                'data' => [
                    [
                        'project_code' => 'PRJ-101',
                        'bom_no' => 'BOM-101-1',
                        'po_no' => 'PO-999',
                    ],
                    [
                        'project_code' => 'PRJ-OTHER',
                        'bom_no' => 'BOM-OTHER',
                        'po_no' => 'PO-OTHER',
                    ],
                ],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 2,
                ],
            ], 200),
            'https://vsm.test/api/material?page=2' => Http::response([
                'success' => true,
                'data' => [
                    [
                        'project_code' => 'PRJ-101',
                        'bom_no' => 'BOM-101-1',
                        'po_no' => 'PO-888',
                    ],
                ],
                'meta' => [
                    'current_page' => 2,
                    'last_page' => 2,
                ],
            ], 200),
        ]);

        $client = new HttpVsmClient;
        $result = $client->bomAndPoNumbersByProject('PRJ-101');

        $this->assertEquals(['BOM-101-1'], $result['bom_numbers']);
        $this->assertEquals(['PO-888', 'PO-999'], $result['po_numbers']);
    }

    public function test_authenticated_user_can_get_project_materials(): void
    {
        config(['vsm.driver' => 'fake']);

        $user = User::factory()->create();
        $project = Project::factory()->create([
            'project_code' => 'VSM-PRJ-001',
            'project_name' => 'Proyek Contoh Utama',
            'customer' => 'PT Contoh Utama',
        ]);

        $response = $this->actingAs($user)->getJson(route('projects.materials', $project));

        $response->assertOk();
        $response->assertJson([
            'project_code' => 'VSM-PRJ-001',
            'bom_numbers' => ['BOM-001-A', 'BOM-001-B'],
            'po_numbers' => ['PO-001-X'],
        ]);
    }
}
