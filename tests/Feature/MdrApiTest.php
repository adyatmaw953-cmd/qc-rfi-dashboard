<?php

namespace Tests\Feature;

use App\Enums\MdrStatus;
use App\Models\Mdr;
use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Tests\TestCase;

class MdrApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateTestDatabase();
        $this->withoutVite();
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $project = Project::factory()->create();
        $mdr = Mdr::factory()->for($project)->create(['unit_no' => 'Unit-401']);

        $this->getJson('/api/v1/mdrs')->assertUnauthorized();
        $this->getJson("/api/v1/mdrs/{$mdr->id}")->assertUnauthorized();
    }

    public function test_token_without_ability_returns_403(): void
    {
        $user = User::factory()->create(['role' => 'ppic']);
        $token = $user->createToken('wrong-ability', ['other:read'])->plainTextToken;

        $project = Project::factory()->create();
        $mdr = Mdr::factory()->for($project)->create(['unit_no' => 'Unit-403']);

        $this->getJson('/api/v1/mdrs', ['Authorization' => "Bearer {$token}"])
            ->assertForbidden();

        $this->getJson("/api/v1/mdrs/{$mdr->id}", ['Authorization' => "Bearer {$token}"])
            ->assertForbidden();
    }

    public function test_token_with_mdr_read_ability_returns_200_and_exact_structure(): void
    {
        $user = User::factory()->create(['role' => 'ppic']);
        $token = $user->createToken('valid-token', ['mdr:read'])->plainTextToken;

        $project = Project::factory()->create([
            'project_code' => 'PRJ-API-01',
            'project_name' => 'API Pipeline Test',
            'customer' => 'Client Pertamina',
        ]);

        $mdr = Mdr::factory()->for($project)->create([
            'unit_no' => 'U-909',
            'owner_serial_no' => 'OSN-909',
            'manufacture_serial_no' => 'MFG-909',
        ]);

        // 1 custom report
        $mdr->reportStatuses()->create([
            'report_key' => 'custom_1_xray',
            'report_title' => 'X-Ray Test',
            'status' => MdrStatus::Close->value,
        ]);

        // List endpoint
        $resList = $this->getJson('/api/v1/mdrs', ['Authorization' => "Bearer {$token}"]);
        $resList->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'project_code',
                        'project_name',
                        'customer',
                        'unit_no',
                        'owner_serial_no',
                        'manufacture_serial_no',
                        'status',
                        'progress' => ['done', 'total'],
                        'reports' => [
                            '*' => ['key', 'name', 'status'],
                        ],
                        'updated_at',
                    ],
                ],
                'links',
                'meta' => ['current_page', 'per_page', 'total'],
            ]);

        // Show endpoint
        $resShow = $this->getJson("/api/v1/mdrs/{$mdr->id}", ['Authorization' => "Bearer {$token}"]);
        $resShow->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $mdr->id,
                    'project_code' => 'PRJ-API-01',
                    'project_name' => 'API Pipeline Test',
                    'customer' => 'Client Pertamina',
                    'unit_no' => 'U-909',
                    'owner_serial_no' => 'OSN-909',
                    'manufacture_serial_no' => 'MFG-909',
                    'status' => 'open',
                    'progress' => [
                        'done' => 1,
                        'total' => 17, // 16 standard + 1 custom
                    ],
                ],
            ]);

        // Verify custom report is included in reports list
        $reports = $resShow->json('data.reports');
        $this->assertCount(17, $reports);
        $this->assertSame('custom_1_xray', $reports[16]['key']);
        $this->assertSame('X-Ray Test', $reports[16]['name']);
        $this->assertSame('close', $reports[16]['status']);
    }

    public function test_fields_outside_spec_are_not_present(): void
    {
        $user = User::factory()->create(['role' => 'ppic']);
        $token = $user->createToken('valid-token', ['mdr:read'])->plainTextToken;

        $project = Project::factory()->create();
        $mdr = Mdr::factory()->for($project)->create(['unit_no' => 'U-Sanitize']);

        $res = $this->getJson("/api/v1/mdrs/{$mdr->id}", ['Authorization' => "Bearer {$token}"]);
        $res->assertOk();

        $data = $res->json('data');
        $expectedKeys = [
            'id',
            'project_code',
            'project_name',
            'customer',
            'unit_no',
            'owner_serial_no',
            'manufacture_serial_no',
            'status',
            'progress',
            'reports',
            'updated_at',
        ];

        sort($expectedKeys);
        $actualKeys = array_keys($data);
        sort($actualKeys);

        $this->assertSame($expectedKeys, $actualKeys);

        // Explicit negative assertions for sensitive or internal data
        $this->assertArrayNotHasKey('email', $data);
        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('created_by', $data);
        $this->assertArrayNotHasKey('created_at', $data);
        $this->assertArrayNotHasKey('project_id', $data);
        $this->assertArrayNotHasKey('remember_token', $data);
        $this->assertArrayNotHasKey('user', $data);
    }

    public function test_status_filter_works_for_open_and_close(): void
    {
        $user = User::factory()->create(['role' => 'ppic']);
        $token = $user->createToken('valid-token', ['mdr:read'])->plainTextToken;

        $project = Project::factory()->create();

        // 1. Open MDR
        $openMdr = Mdr::factory()->for($project)->create(['unit_no' => 'UNIT-OPEN']);
        foreach (array_keys(config('mudalaya.mdr_reports')) as $key) {
            $openMdr->reportStatuses()->create([
                'report_key' => $key,
                'status' => MdrStatus::Open->value,
            ]);
        }

        // 2. Close MDR
        $closeMdr = Mdr::factory()->for($project)->create(['unit_no' => 'UNIT-CLOSE']);
        foreach (array_keys(config('mudalaya.mdr_reports')) as $key) {
            $closeMdr->reportStatuses()->create([
                'report_key' => $key,
                'status' => MdrStatus::Close->value,
            ]);
        }

        // Filter status=close
        $resClose = $this->getJson('/api/v1/mdrs?status=close', ['Authorization' => "Bearer {$token}"]);
        $resClose->assertOk();
        $this->assertCount(1, $resClose->json('data'));
        $this->assertSame('UNIT-CLOSE', $resClose->json('data.0.unit_no'));
        $this->assertSame('close', $resClose->json('data.0.status'));

        // Filter status=open
        $resOpen = $this->getJson('/api/v1/mdrs?status=open', ['Authorization' => "Bearer {$token}"]);
        $resOpen->assertOk();
        $this->assertCount(1, $resOpen->json('data'));
        $this->assertSame('UNIT-OPEN', $resOpen->json('data.0.unit_no'));
        $this->assertSame('open', $resOpen->json('data.0.status'));
    }

    public function test_updated_since_filter_works(): void
    {
        $user = User::factory()->create(['role' => 'ppic']);
        $token = $user->createToken('valid-token', ['mdr:read'])->plainTextToken;

        $project = Project::factory()->create();

        // MDR 1 updated 10 days ago
        $oldMdr = Mdr::factory()->for($project)->create(['unit_no' => 'OLD-MDR']);
        Mdr::query()->whereKey($oldMdr->id)->update([
            'updated_at' => Carbon::now()->subDays(10),
        ]);

        // MDR 2 updated today
        $newMdr = Mdr::factory()->for($project)->create(['unit_no' => 'NEW-MDR']);
        Mdr::query()->whereKey($newMdr->id)->update([
            'updated_at' => Carbon::now(),
        ]);

        $filterTimestamp = Carbon::now()->subDays(3)->toIso8601String();
        $encodedTimestamp = urlencode($filterTimestamp);

        $res = $this->getJson("/api/v1/mdrs?updated_since={$encodedTimestamp}", [
            'Authorization' => "Bearer {$token}",
        ]);
        $res->assertOk();
        $this->assertCount(1, $res->json('data'));
        $this->assertSame('NEW-MDR', $res->json('data.0.unit_no'));
    }

    public function test_pagination_defaults_to_50_and_capped_at_200(): void
    {
        $user = User::factory()->create(['role' => 'ppic']);
        $token = $user->createToken('valid-token', ['mdr:read'])->plainTextToken;

        $project = Project::factory()->create();
        Mdr::factory()->for($project)->create();

        // Default pagination
        $resDefault = $this->getJson('/api/v1/mdrs', ['Authorization' => "Bearer {$token}"]);
        $resDefault->assertOk();
        $this->assertSame(50, $resDefault->json('meta.per_page'));

        // Max pagination capped at 200
        $resCapped = $this->getJson('/api/v1/mdrs?per_page=500', ['Authorization' => "Bearer {$token}"]);
        $resCapped->assertOk();
        $this->assertSame(200, $resCapped->json('meta.per_page'));
    }

    public function test_artisan_api_token_command_creates_user_and_token(): void
    {
        $this->artisan('api:token', ['nama' => 'PPIC Integration Bot'])
            ->expectsOutputToContain("Token API untuk integrasi 'PPIC Integration Bot'")
            ->assertSuccessful();

        $user = User::query()->where('name', 'PPIC Integration Bot')->first();
        $this->assertNotNull($user);
        $this->assertSame('ppic-integration-bot-api@mudalaya.internal', $user->email);
        $this->assertSame('ppic', $user->role);
        $this->assertTrue($user->is_active);

        // Token exists and has mdr:read ability
        $this->assertCount(1, $user->tokens);
        $this->assertTrue($user->tokens->first()->can('mdr:read'));
        $this->assertFalse($user->tokens->first()->can('other:ability'));
    }
}
