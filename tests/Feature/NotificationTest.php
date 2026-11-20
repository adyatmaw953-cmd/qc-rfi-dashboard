<?php

namespace Tests\Feature;

use App\Enums\RfiResult;
use App\Models\FindingSubmission;
use App\Models\Project;
use App\Models\QcFinding;
use App\Models\Rfi;
use App\Models\User;
use App\Notifications\FindingCreated;
use App\Notifications\RfiNc;
use App\Services\NotificationDispatcher;
use App\Support\NotificationHelper;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateTestDatabase();
        $this->withoutVite();
        Http::preventStrayRequests();
        Http::fake([
            'https://date.nager.at/api/v3/PublicHolidays/*/ID' => Http::response([], 200),
        ]);
        Cache::flush();
        Carbon::setTestNow(Carbon::parse('2026-09-28 13:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_rfi_submitted_event_notification(): void
    {
        $warehouse = User::factory()->create(['role' => 'warehouse']);
        $qcqa = User::factory()->create(['role' => 'qc_qa']);
        $admin = User::factory()->create(['role' => 'admin']);
        $production = User::factory()->create(['role' => 'production']);
        $project = Project::factory()->create();

        $this->actingAs($warehouse)
            ->post(route('rfi.store', ['kategori' => 'A']), [
                'project_id' => $project->id,
                'date_inspect' => '2026-10-01',
                'details' => [
                    'jenis_material' => 'Steel Plate',
                    'spec' => 'ASTM A36',
                    'quantity' => 10,
                    'dimension' => '10x20',
                ],
            ])
            ->assertRedirect(route('rfi.category', ['kategori' => 'A']));

        $rfi = Rfi::query()->firstOrFail();

        // qc_qa & admin should receive notification
        $this->assertSame(1, $qcqa->notifications()->count());
        $this->assertSame(1, $admin->notifications()->count());

        // warehouse (actor) should NOT receive
        $this->assertSame(0, $warehouse->notifications()->count());

        // production is not in rfi.submitted roles
        $this->assertSame(0, $production->notifications()->count());

        $notification = $qcqa->notifications()->first();
        $this->assertSame('rfi.submitted', $notification->data['type']);
        $this->assertSame($rfi->id, $notification->data['rfi_id']);
        $this->assertSame($rfi->rfi_no, $notification->data['rfi_no']);
        $this->assertSame('A', $notification->data['category']);
        $this->assertSame($project->project_name, $notification->data['project_name']);
        $this->assertSame($warehouse->name, $notification->data['actor_name']);
        $this->assertSame($warehouse->role, $notification->data['actor_role']);
        $this->assertSame(route('rfi.category', ['kategori' => 'A']), $notification->data['url']);
    }

    public function test_rfi_reviewed_ok_event_notification(): void
    {
        $warehouse = User::factory()->create(['role' => 'warehouse']);
        $qcqa = User::factory()->create(['role' => 'qc_qa']);
        $project = Project::factory()->create();

        $rfi = Rfi::factory()->create([
            'project_id' => $project->id,
            'category' => 'A',
            'created_by' => $warehouse->id,
            'status' => 'open',
            'result' => null,
        ]);

        $this->actingAs($qcqa)
            ->post(route('rfi.review', ['kategori' => 'A', 'rfi' => $rfi]), [
                'result' => RfiResult::Accepted->value,
                'qc_note' => 'All dimensions OK',
            ])
            ->assertRedirect(route('rfi.category', ['kategori' => 'A']));

        // Warehouse creator receives reviewed_ok
        $this->assertSame(1, $warehouse->notifications()->count());
        $notification = $warehouse->notifications()->first();
        $this->assertSame('rfi.reviewed_ok', $notification->data['type']);
        $this->assertSame($rfi->id, $notification->data['rfi_id']);
        $this->assertSame(route('rfi.category', ['kategori' => 'A']), $notification->data['url']);

        // QC/QA actor does NOT receive
        $this->assertSame(0, $qcqa->notifications()->count());
    }

    public function test_rfi_reviewed_nc_notifies_production_and_non_production_creator(): void
    {
        $adminCreator = User::factory()->create(['role' => 'admin']);
        $production = User::factory()->create(['role' => 'production']);
        $qcqa = User::factory()->create(['role' => 'qc_qa']);
        $project = Project::factory()->create();

        $rfi = Rfi::factory()->create([
            'project_id' => $project->id,
            'category' => 'B',
            'created_by' => $adminCreator->id,
            'status' => 'open',
            'result' => null,
        ]);

        $this->actingAs($qcqa)
            ->post(route('rfi.review', ['kategori' => 'B', 'rfi' => $rfi]), [
                'result' => RfiResult::Repair->value,
                'qc_note' => 'Material thickness is out of spec',
            ])
            ->assertRedirect(route('rfi.category', ['kategori' => 'B']));

        $qcFinding = QcFinding::query()->where('rfi_id', $rfi->id)->firstOrFail();

        // Production user receives FindingCreated ("isi action plan")
        $this->assertSame(1, $production->notifications()->count());
        $prodNotification = $production->notifications()->first();
        $this->assertSame(FindingCreated::class, $prodNotification->type);
        $this->assertSame('rfi.reviewed_nc', $prodNotification->data['type']);
        $this->assertSame(route('qc-finding.project', ['project' => $project->id]), $prodNotification->data['url']);
        $this->assertSame($qcFinding->id, $prodNotification->data['finding_id']);

        // Non-production creator receives RfiNc
        $this->assertSame(1, $adminCreator->notifications()->count());
        $whNotification = $adminCreator->notifications()->first();
        $this->assertSame(RfiNc::class, $whNotification->type);
        $this->assertSame('rfi.reviewed_nc', $whNotification->data['type']);
        $this->assertSame(route('rfi.category', ['kategori' => 'B']), $whNotification->data['url']);
        $this->assertSame($qcFinding->id, $whNotification->data['finding_id']);

        // Actor does not receive
        $this->assertSame(0, $qcqa->notifications()->count());
    }

    public function test_warehouse_finding_use_as_is_sends_exactly_one_notification_to_warehouse_and_never_to_actor(): void
    {
        $warehouse = User::factory()->create(['role' => 'warehouse']);
        $qcqa = User::factory()->create(['role' => 'qc_qa']);
        $project = Project::factory()->create();

        $rfi = Rfi::query()->create([
            'project_id' => $project->id,
            'category' => 'A',
            'rfi_no' => 'RFI-A-7701',
            'created_by' => $warehouse->id,
            'status' => 'open',
            'date_inspect' => '2026-10-01',
            'details' => ['material' => 'Pipa'],
        ]);

        $this->actingAs($qcqa)->post(route('qc-finding.incoming.store', ['rfi' => $rfi->id]), [
            'material' => 'Pipa',
            'reason_of_finding' => 'Goresan ringan',
            'result' => 'use_as_is',
        ])->assertRedirect();

        // Warehouse receives exactly 1 notification
        $this->assertSame(1, $warehouse->notifications()->count());
        $notification = $warehouse->notifications()->first();
        $this->assertSame('finding.warehouse_use_as_is', $notification->data['type']);
        $this->assertStringContainsString('Use as is', $notification->data['title']);
        $this->assertStringContainsString('RFI-A-7701', $notification->data['body']);

        // Actor (QC) does not receive any
        $this->assertSame(0, $qcqa->notifications()->count());
    }

    public function test_rfi_reviewed_nc_dedupes_when_creator_is_production(): void
    {
        $productionCreator = User::factory()->create(['role' => 'production']);
        $qcqa = User::factory()->create(['role' => 'qc_qa']);
        $project = Project::factory()->create();

        $rfi = Rfi::factory()->create([
            'project_id' => $project->id,
            'category' => 'B',
            'created_by' => $productionCreator->id,
            'status' => 'open',
            'result' => null,
        ]);

        $this->actingAs($qcqa)
            ->post(route('rfi.review', ['kategori' => 'B', 'rfi' => $rfi]), [
                'result' => RfiResult::Repair->value,
                'qc_note' => 'Cutting edge rough',
            ]);

        // Production creator only receives 1 notification (FindingCreated), NOT 2!
        $this->assertSame(1, $productionCreator->notifications()->count());
        $notif = $productionCreator->notifications()->first();
        $this->assertSame(FindingCreated::class, $notif->type);
    }

    public function test_finding_reinspect_submitted_notification(): void
    {
        Storage::fake('local');

        $production = User::factory()->create(['role' => 'production']);
        $qcqa = User::factory()->create(['role' => 'qc_qa']);
        $admin = User::factory()->create(['role' => 'admin']);
        $project = Project::factory()->create();

        $rfi = Rfi::factory()->create([
            'project_id' => $project->id,
            'category' => 'B',
            'status' => 'nc',
            'result' => RfiResult::Repair,
        ]);

        $finding = QcFinding::factory()->create([
            'rfi_id' => $rfi->id,
            'status' => 'open',
            'action_plan' => 'Will recut with CNC machine',
        ]);

        $file = UploadedFile::fake()->create('evidence.pdf', 500, 'application/pdf');

        $this->actingAs($production)
            ->post(route('qc-finding.submissions.store', ['finding' => $finding]), [
                'note' => 'Evidence attached for review',
                'evidence' => [$file],
            ])
            ->assertRedirect(route('qc-finding.project', ['project' => $project->id]));

        // qc_qa & admin receive notification
        $this->assertSame(1, $qcqa->notifications()->count());
        $this->assertSame(1, $admin->notifications()->count());

        // Submitter (production) does not receive
        $this->assertSame(0, $production->notifications()->count());

        $notification = $qcqa->notifications()->first();
        $this->assertSame('finding.reinspect_submitted', $notification->data['type']);
        $this->assertSame($finding->id, $notification->data['finding_id']);
        $this->assertSame(route('qc-finding.project', ['project' => $project->id]), $notification->data['url']);
    }

    public function test_finding_verified_ok_notification_and_category_permissions(): void
    {
        $warehouseUser = User::factory()->create(['role' => 'warehouse']); // view_categories: ['A']
        $ppicUser = User::factory()->create(['role' => 'ppic']);           // view_categories: ['E']
        $prodSubmitter = User::factory()->create(['role' => 'production']);
        $prodActionPlan = User::factory()->create(['role' => 'production']);
        $qcqa = User::factory()->create(['role' => 'qc_qa']);
        $project = Project::factory()->create();

        // Category C Finding (Fabrication)
        $rfi = Rfi::factory()->create([
            'project_id' => $project->id,
            'category' => 'C',
            'created_by' => $warehouseUser->id,
            'status' => 'nc',
        ]);

        $finding = QcFinding::factory()->create([
            'rfi_id' => $rfi->id,
            'status' => 'waiting_verification',
            'action_plan_by' => $prodActionPlan->id,
            'action_plan' => 'Repair completed',
        ]);

        $submission = FindingSubmission::factory()->create([
            'qc_finding_id' => $finding->id,
            'round_no' => 1,
            'submitted_by' => $prodSubmitter->id,
            'verification_result' => null,
        ]);

        $this->actingAs($qcqa)
            ->post(route('qc-finding.verify', ['finding' => $finding]), [
                'result' => 'ok',
                'verification_note' => 'Verified good',
            ])
            ->assertRedirect(route('qc-finding.project', ['project' => $project->id]));

        // Submitter and Action Plan creator receive notification
        $this->assertSame(1, $prodSubmitter->notifications()->count());
        $this->assertSame(1, $prodActionPlan->notifications()->count());

        // Warehouse is rfi.created_by, but category C is NOT in Warehouse view_categories ['A'],
        // so Warehouse MUST NOT receive this notification!
        $this->assertSame(0, $warehouseUser->notifications()->count());

        // PPIC also only has ['E'], so must NOT receive category C
        $this->assertSame(0, $ppicUser->notifications()->count());

        // Verifier (qcqa actor) does not receive
        $this->assertSame(0, $qcqa->notifications()->count());
    }

    public function test_finding_verified_rejected_notification(): void
    {
        $prodSubmitter = User::factory()->create(['role' => 'production']);
        $prodActionPlan = User::factory()->create(['role' => 'production']);
        $otherProd = User::factory()->create(['role' => 'production']);
        $qcqa = User::factory()->create(['role' => 'qc_qa']);
        $project = Project::factory()->create();

        $rfi = Rfi::factory()->create([
            'project_id' => $project->id,
            'category' => 'B',
            'status' => 'nc',
        ]);

        $finding = QcFinding::factory()->create([
            'rfi_id' => $rfi->id,
            'status' => 'waiting_verification',
            'action_plan_by' => $prodActionPlan->id,
            'action_plan' => 'Repair completed',
        ]);

        FindingSubmission::factory()->create([
            'qc_finding_id' => $finding->id,
            'round_no' => 1,
            'submitted_by' => $prodSubmitter->id,
            'verification_result' => null,
        ]);

        $this->actingAs($qcqa)
            ->post(route('qc-finding.verify', ['finding' => $finding]), [
                'result' => 'not_ok',
                'verification_note' => 'Still defect found',
            ])
            ->assertRedirect(route('qc-finding.project', ['project' => $project->id]));

        // Production roles & accounts receive finding.verified_rejected
        $this->assertSame(1, $prodSubmitter->notifications()->count());
        $this->assertSame(1, $prodActionPlan->notifications()->count());
        $this->assertSame(1, $otherProd->notifications()->count());

        $notif = $prodSubmitter->notifications()->first();
        $this->assertSame('finding.verified_rejected', $notif->data['type']);

        // Verifier (actor) does not receive
        $this->assertSame(0, $qcqa->notifications()->count());
    }

    public function test_finding_due_date_changed_notification_only_when_changed(): void
    {
        $production = User::factory()->create(['role' => 'production']);
        $qcqa = User::factory()->create(['role' => 'qc_qa']);
        $project = Project::factory()->create();

        $rfi = Rfi::factory()->create([
            'project_id' => $project->id,
            'category' => 'B',
            'status' => 'nc',
        ]);

        $finding = QcFinding::factory()->create([
            'rfi_id' => $rfi->id,
            'status' => 'open',
            'due_date' => '2026-10-10',
            'action_plan_by' => $production->id,
        ]);

        // Submit same due_date -> condition did not change -> no notification
        $this->actingAs($qcqa)
            ->post(route('qc-finding.due-date.update', ['finding' => $finding]), [
                'due_date' => '2026-10-10',
            ])
            ->assertRedirect(route('qc-finding.project', ['project' => $project->id]));

        $this->assertSame(0, $production->notifications()->count());

        // Submit new due_date -> condition changed -> notification sent
        $this->actingAs($qcqa)
            ->post(route('qc-finding.due-date.update', ['finding' => $finding]), [
                'due_date' => '2026-10-15',
            ])
            ->assertRedirect(route('qc-finding.project', ['project' => $project->id]));

        $this->assertSame(1, $production->notifications()->count());
        $notif = $production->notifications()->first();
        $this->assertSame('finding.due_date_changed', $notif->data['type']);
        $this->assertSame($finding->id, $notif->data['finding_id']);

        // Actor does not receive
        $this->assertSame(0, $qcqa->notifications()->count());
    }

    public function test_no_notification_on_rejected_or_failed_actions(): void
    {
        $qcqa = User::factory()->create(['role' => 'qc_qa']);
        $warehouse = User::factory()->create(['role' => 'warehouse']);
        $project = Project::factory()->create();

        // RFI already closed
        $rfi = Rfi::factory()->create([
            'project_id' => $project->id,
            'category' => 'B',
            'created_by' => $warehouse->id,
            'status' => 'close',
            'result' => RfiResult::Accepted,
        ]);

        // Review attempt should fail with validation exception
        $this->actingAs($qcqa)
            ->post(route('rfi.review', ['kategori' => 'B', 'rfi' => $rfi]), [
                'result' => RfiResult::Repair->value,
                'qc_note' => 'Cannot review closed',
            ])
            ->assertSessionHasErrors('result');

        $this->assertSame(0, DatabaseNotification::query()->count());
    }

    public function test_user_only_sees_own_notifications_and_other_user_notification_returns_404(): void
    {
        $user1 = User::factory()->create(['role' => 'production']);
        $user2 = User::factory()->create(['role' => 'warehouse']);

        // Create notification for user 2
        $notif2 = DatabaseNotification::query()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\Notifications\RfiSubmitted',
            'notifiable_type' => User::class,
            'notifiable_id' => $user2->id,
            'data' => [
                'type' => 'rfi.submitted',
                'title' => 'RFI User 2',
                'body' => 'Notice for user 2',
                'url' => route('notifications.index'),
            ],
            'read_at' => null,
        ]);

        // User 1 sees empty list
        $this->actingAs($user1)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Belum ada notifikasi.')
            ->assertDontSee('RFI User 2');

        // User 1 trying to read user 2's notification returns 404
        $this->actingAs($user1)
            ->post(route('notifications.read', ['id' => $notif2->id]))
            ->assertNotFound();
    }

    public function test_mark_as_read_and_mark_all_as_read(): void
    {
        $user = User::factory()->create(['role' => 'production']);
        $targetUrl = route('rfi.category', ['kategori' => 'B']);

        $notif1 = DatabaseNotification::query()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\Notifications\RfiSubmitted',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => [
                'type' => 'rfi.submitted',
                'title' => 'Notif 1',
                'body' => 'Body 1',
                'url' => $targetUrl,
            ],
            'read_at' => null,
        ]);

        $notif2 = DatabaseNotification::query()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\Notifications\RfiSubmitted',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => [
                'type' => 'rfi.submitted',
                'title' => 'Notif 2',
                'body' => 'Body 2',
                'url' => $targetUrl,
            ],
            'read_at' => null,
        ]);

        // Mark single as read -> redirects to target url
        $this->actingAs($user)
            ->post(route('notifications.read', ['id' => $notif1->id]))
            ->assertRedirect($targetUrl);

        $this->assertNotNull($notif1->fresh()->read_at);
        $this->assertNull($notif2->fresh()->read_at);

        // Mark all as read
        $this->actingAs($user)
            ->post(route('notifications.read-all'))
            ->assertRedirect();

        $this->assertNotNull($notif2->fresh()->read_at);
        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_filter_unread_notifications(): void
    {
        $user = User::factory()->create(['role' => 'production']);

        DatabaseNotification::query()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\Notifications\RfiSubmitted',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => ['type' => 'rfi.submitted', 'title' => 'Unread Notice', 'body' => '...'],
            'read_at' => null,
        ]);

        DatabaseNotification::query()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\Notifications\RfiSubmitted',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => ['type' => 'rfi.submitted', 'title' => 'Already Read Notice', 'body' => '...'],
            'read_at' => now(),
        ]);

        // All filter
        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Unread Notice')
            ->assertSee('Already Read Notice');

        // Unread filter
        $this->actingAs($user)
            ->get(route('notifications.index', ['filter' => 'unread']))
            ->assertOk()
            ->assertSee('Unread Notice')
            ->assertDontSee('Already Read Notice');
    }

    public function test_badge_counts_in_header_layout(): void
    {
        $user = User::factory()->create(['role' => 'production']);

        // 0 unread: badge hidden
        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('aria-label="Notifikasi, 0 belum dibaca"', false)
            ->assertDontSee('bg-[#B4470F]');

        // 3 unread: badge shows 3
        for ($i = 0; $i < 3; $i++) {
            DatabaseNotification::query()->create([
                'id' => (string) Str::uuid(),
                'type' => 'App\Notifications\RfiSubmitted',
                'notifiable_type' => User::class,
                'notifiable_id' => $user->id,
                'data' => ['type' => 'rfi.submitted', 'title' => 'Title '.$i],
                'read_at' => null,
            ]);
        }

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('aria-label="Notifikasi, 3 belum dibaca"', false)
            ->assertSee('3');

        // 11 unread: badge shows 9+
        for ($i = 0; $i < 8; $i++) {
            DatabaseNotification::query()->create([
                'id' => (string) Str::uuid(),
                'type' => 'App\Notifications\RfiSubmitted',
                'notifiable_type' => User::class,
                'notifiable_id' => $user->id,
                'data' => ['type' => 'rfi.submitted', 'title' => 'Extra '.$i],
                'read_at' => null,
            ]);
        }

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('aria-label="Notifikasi, 11 belum dibaca"', false)
            ->assertSee('9+');
    }

    public function test_notification_failure_does_not_break_main_action(): void
    {
        Exceptions::fake();

        // Create a dispatcher mock or spy that throws
        $mockDispatcher = $this->mock(NotificationDispatcher::class);
        $mockDispatcher->shouldReceive('rfiSubmitted')
            ->andThrow(new Exception('Simulated notification dispatch failure'));

        $warehouse = User::factory()->create(['role' => 'warehouse']);
        $project = Project::factory()->create();

        // Storing RFI must succeed despite notification failure
        $this->actingAs($warehouse)
            ->post(route('rfi.store', ['kategori' => 'A']), [
                'project_id' => $project->id,
                'date_inspect' => '2026-10-01',
                'details' => [
                    'jenis_material' => 'Steel',
                    'spec' => 'ASTM',
                    'quantity' => 5,
                    'dimension' => '10x10',
                ],
            ])
            ->assertRedirect(route('rfi.category', ['kategori' => 'A']))
            ->assertSessionHas('success', 'RFI berhasil dibuat.');

        $this->assertDatabaseHas('rfis', [
            'category' => 'A',
            'created_by' => $warehouse->id,
        ]);

        Exceptions::assertReported(fn (Exception $e) => $e->getMessage() === 'Simulated notification dispatch failure');
    }

    public function test_notification_event_keys_have_valid_icons_and_no_raw_type_in_chips(): void
    {
        $user = User::factory()->create(['role' => 'qc_qa']);

        $eventKeys = [
            'rfi.submitted' => 'Perlu review',
            'rfi.reviewed_ok' => 'Close',
            'rfi.reviewed_nc' => 'NC',
            'finding.reinspect_submitted' => 'Re-Inspect',
            'finding.verified_ok' => 'Close',
            'finding.verified_rejected' => 'Ditolak',
            'finding.due_date_changed' => 'Due date',
        ];

        foreach ($eventKeys as $key => $expectedChipLabel) {
            $meta = NotificationHelper::meta($key);
            $this->assertSame($expectedChipLabel, $meta['chip']['label']);
            $this->assertNotSame($key, $meta['chip']['label'], "Chip label should not be raw event key {$key}");
            $this->assertNotEmpty($meta['icon']);

            // Insert real notification to test rendering in view
            DatabaseNotification::query()->create([
                'id' => (string) Str::uuid(),
                'type' => 'App\Notifications\GenericNotification',
                'notifiable_type' => User::class,
                'notifiable_id' => $user->id,
                'data' => [
                    'type' => $key,
                    'title' => "Test for {$key}",
                    'body' => 'Contoh deskripsi notifikasi.',
                    'rfi_no' => 'RFI-2026-001',
                ],
                'created_at' => now(),
            ]);
        }

        $response = $this->actingAs($user)->get(route('notifications.index'))->assertOk();
        foreach ($eventKeys as $key => $expectedChipLabel) {
            $response->assertSee($expectedChipLabel);
            $response->assertDontSee(">{$key}<", false);
        }
    }

    public function test_create_rfi_button_in_notification_header_appears_for_authorized_roles(): void
    {
        $warehouse = User::factory()->create(['role' => 'warehouse']);
        $qc = User::factory()->create(['role' => 'qc_qa']);

        // Warehouse is authorized to submit category A -> should see "+ Buat RFI"
        $this->actingAs($warehouse)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('+ Buat RFI');

        // QC is review role, not submit role -> should NOT see "+ Buat RFI"
        $this->actingAs($qc)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertDontSee('+ Buat RFI');
    }

    public function test_compact_indonesian_relative_time_formatting(): void
    {
        $now = Carbon::parse('2026-10-31 15:00:00', 'Asia/Jakarta');
        Carbon::setTestNow($now);

        $justNow = $now->copy()->subSeconds(30);
        $this->assertSame('Baru saja', NotificationHelper::formatTime($justNow));

        $minutesAgo = $now->copy()->subMinutes(15);
        $this->assertSame('15 mnt lalu', NotificationHelper::formatTime($minutesAgo));

        $hoursAgo = $now->copy()->subHours(4);
        $this->assertSame('4 jam lalu', NotificationHelper::formatTime($hoursAgo));

        $yesterday = $now->copy()->subDay()->setTime(10, 0, 0);
        $this->assertSame('Kemarin', NotificationHelper::formatTime($yesterday));

        $threeDaysAgo = $now->copy()->subDays(3);
        $this->assertSame('3 hari lalu', NotificationHelper::formatTime($threeDaysAgo));

        $tenDaysAgo = $now->copy()->subDays(10);
        $this->assertSame('21 Okt 2026', NotificationHelper::formatTime($tenDaysAgo));
    }
}
