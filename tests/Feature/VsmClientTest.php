<?php

namespace Tests\Feature;

use App\Contracts\VsmClient;
use App\Services\Vsm\FakeVsmClient;
use App\Services\Vsm\HttpVsmClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VsmClientTest extends TestCase
{
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
}
