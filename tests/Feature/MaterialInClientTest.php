<?php

namespace Tests\Feature;

use App\Contracts\MaterialInClient;
use App\Models\User;
use App\Services\MaterialIn\FakeMaterialInClient;
use App\Services\MaterialIn\HttpMaterialInClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MaterialInClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_fake_driver_resolves_and_filters_suppliers(): void
    {
        config(['material_in.driver' => 'fake']);

        $client = app(MaterialInClient::class);

        $this->assertInstanceOf(FakeMaterialInClient::class, $client);

        $all = $client->searchSuppliers();
        $this->assertContains('SDC Cibinong', $all);
        $this->assertContains('Samator', $all);

        $filtered = $client->searchSuppliers('cibinong');
        $this->assertEquals(['SDC Cibinong'], $filtered);
    }

    public function test_http_client_fetches_and_deduplicates_suppliers(): void
    {
        config([
            'material_in.base_url' => 'http://test-material-in.local/api/qc/material-ins',
            'material_in.timeout' => 5,
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'http://test-material-in.local/api/qc/material-ins*' => Http::response([
                'success' => true,
                'data' => [
                    ['id' => 1, 'supplier' => 'sdc cibinong'],
                    ['id' => 2, 'supplier' => 'SDC Cibinong'],
                    ['id' => 3, 'supplier' => 'TB Buana Jaya'],
                    ['id' => 4, 'supplier' => null],
                    ['id' => 5, 'supplier' => '   '],
                ],
            ], 200),
        ]);

        $client = new HttpMaterialInClient;
        $suppliers = $client->searchSuppliers('cibinong');

        $this->assertCount(2, $suppliers);
        $this->assertContains('sdc cibinong', $suppliers);
        $this->assertContains('TB Buana Jaya', $suppliers);

        Http::assertSent(function (Request $request): bool {
            return str_starts_with($request->url(), 'http://test-material-in.local/api/qc/material-ins')
                && $request['search'] === 'cibinong'
                && (int) $request['per_page'] === 100;
        });
    }

    public function test_http_client_handles_failures_gracefully(): void
    {
        config([
            'material_in.base_url' => 'http://test-material-in.local/api/qc/material-ins',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'http://test-material-in.local/api/qc/material-ins*' => Http::response('Server Error', 500),
        ]);

        $client = new HttpMaterialInClient;
        $suppliers = $client->searchSuppliers('error');

        $this->assertSame([], $suppliers);
    }

    public function test_guest_cannot_access_supplier_search_endpoint(): void
    {
        $response = $this->getJson(route('suppliers.search', ['search' => 'sdc']));

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_can_search_suppliers(): void
    {
        config(['material_in.driver' => 'fake']);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson(route('suppliers.search', ['search' => 'cibinong']));

        $response->assertOk();
        $response->assertJson(['SDC Cibinong']);
    }

    public function test_fake_driver_resolves_and_filters_reference_numbers(): void
    {
        config(['material_in.driver' => 'fake']);

        $client = app(MaterialInClient::class);

        $all = $client->searchReferenceNumbers();
        $this->assertCount(3, $all);
        $this->assertSame('IN-20261120-4AD5', $all[0]['reference_number']);

        $filtered = $client->searchReferenceNumbers('Samator');
        $this->assertCount(1, $filtered);
        $this->assertSame('IN-20261117-33C0', $filtered[0]['reference_number']);
    }

    public function test_http_client_fetches_and_deduplicates_reference_numbers(): void
    {
        config([
            'material_in.base_url' => 'http://test-material-in.local/api/qc/material-ins',
            'material_in.timeout' => 5,
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'http://test-material-in.local/api/qc/material-ins*' => Http::response([
                'success' => true,
                'data' => [
                    [
                        'id' => 1,
                        'reference_number' => 'IN-001',
                        'supplier' => 'Vendor A',
                        'po_number' => 'PO-1',
                        'date' => '2026-11-20',
                    ],
                    [
                        'id' => 2,
                        'reference_number' => 'IN-001',
                        'supplier' => 'Vendor A',
                        'po_number' => 'PO-1',
                        'date' => '2026-11-20',
                    ],
                    [
                        'id' => 3,
                        'reference_number' => 'IN-002',
                        'supplier' => 'Vendor B',
                        'po_number' => null,
                        'date' => '2026-11-21',
                    ],
                    [
                        'id' => 4,
                        'reference_number' => null,
                    ],
                ],
            ], 200),
        ]);

        $client = new HttpMaterialInClient;
        $refs = $client->searchReferenceNumbers('IN-');

        $this->assertCount(2, $refs);
        $this->assertSame('IN-001', $refs[0]['reference_number']);
        $this->assertSame('Vendor A', $refs[0]['supplier']);
        $this->assertSame('IN-002', $refs[1]['reference_number']);

        Http::assertSent(function (Request $request): bool {
            return str_starts_with($request->url(), 'http://test-material-in.local/api/qc/material-ins')
                && $request['search'] === 'IN-'
                && (int) $request['per_page'] === 100;
        });
    }

    public function test_guest_cannot_access_surat_jalan_search_endpoint(): void
    {
        $response = $this->getJson(route('surat-jalan.search', ['search' => 'IN-']));

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_can_search_surat_jalan(): void
    {
        config(['material_in.driver' => 'fake']);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson(route('surat-jalan.search', ['search' => 'IN-20261120-4AD5']));

        $response->assertOk();
        $response->assertJsonFragment(['reference_number' => 'IN-20261120-4AD5']);
    }
}
