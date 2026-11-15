<?php

namespace Tests\Feature;

use App\Contracts\VsmClient;
use App\Models\Project;
use App\Services\Vsm\FakeVsmClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncVsmProjectsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateTestDatabase();
    }

    public function test_fake_client_upserts_projects_without_removing_existing_rows(): void
    {
        config(['vsm.driver' => 'fake']);

        $existingProject = Project::factory()->create([
            'external_id' => 20001,
            'project_code' => 'OLD-CODE',
            'project_name' => 'Nama Lama',
        ]);
        $unlistedProject = Project::factory()->create([
            'external_id' => 29999,
        ]);

        $this->assertInstanceOf(FakeVsmClient::class, app(VsmClient::class));
        $this->artisan('vsm:sync-projects')
            ->expectsOutput('Berhasil menyinkronkan 2 proyek dari VSM.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('projects', [
            'id' => $existingProject->id,
            'external_id' => 20001,
            'project_code' => 'VSM-PRJ-001',
            'project_name' => 'Proyek Contoh Utama',
            'customer' => 'PT Contoh Utama',
        ]);
        $this->assertDatabaseHas('projects', [
            'external_id' => 20002,
            'project_code' => 'VSM-PRJ-002',
            'project_name' => 'Proyek Contoh Kedua',
            'customer' => null,
        ]);
        $this->assertDatabaseHas('projects', [
            'id' => $unlistedProject->id,
            'external_id' => 29999,
        ]);
        $this->assertDatabaseCount('projects', 3);
    }

    public function test_http_failure_reports_an_error_and_preserves_existing_projects(): void
    {
        config([
            'vsm.driver' => 'http',
            'vsm.base_url' => 'https://vsm.test/api',
            'vsm.token' => 'test-token',
        ]);

        $existingProject = Project::factory()->create([
            'external_id' => 30100,
            'project_name' => 'Tetap Tersimpan',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'https://vsm.test/api/projects' => Http::response(['message' => 'VSM unavailable'], 503),
        ]);

        $this->artisan('vsm:sync-projects')
            ->expectsOutputToContain('Gagal menyinkronkan proyek dari VSM:')
            ->assertExitCode(1);

        Http::assertSentCount(3);
        $this->assertDatabaseHas('projects', [
            'id' => $existingProject->id,
            'external_id' => 30100,
            'project_name' => 'Tetap Tersimpan',
        ]);
        $this->assertDatabaseCount('projects', 1);
    }
}
