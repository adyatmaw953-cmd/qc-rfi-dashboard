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
}
