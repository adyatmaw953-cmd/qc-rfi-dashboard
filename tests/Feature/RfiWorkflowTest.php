<?php

namespace Tests\Feature;

use App\Enums\RfiResult;
use App\Models\Project;
use App\Models\QcFinding;
use App\Models\Rfi;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RfiWorkflowTest extends TestCase
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

    public function test_warehouse_can_create_category_a_but_not_category_b(): void
    {
        $warehouse = User::factory()->create(['role' => 'warehouse']);
        $project = Project::factory()->create();

        $this->actingAs($warehouse)
            ->post(route('rfi.store', ['kategori' => 'A']), $this->categoryAInput($project))
            ->assertRedirect(route('rfi.category', ['kategori' => 'A']));

        $rfi = Rfi::query()->firstOrFail();
        $this->assertSame('RFI-A-0001', $rfi->rfi_no);
        $this->assertSame('2026-09-29 16:59:00', $rfi->deadline_at->toDateTimeString());
        $this->assertSame($warehouse->id, $rfi->created_by);
        $this->assertSame('SJ-123', $rfi->details['surat_jalan_no']);

        $this->actingAs($warehouse)
            ->post(route('rfi.store', ['kategori' => 'B']), [
                'project_id' => $project->id,
                'date_inspect' => '2026-10-01',
                'details' => ['inspect_item' => 'Cutting', 'dimension' => '10 mm'],
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('rfis', 1);
    }

    public function test_production_can_create_categories_b_c_and_d_but_not_a_or_e(): void
    {
        $production = User::factory()->create(['role' => 'production']);
        $project = Project::factory()->create();
        $validInputs = [
            'B' => ['inspect_item' => 'Cutting', 'dimension' => '10 mm'],
            'C' => ['inspect_item' => 'Testing', 'test_type' => 'NDT', 'drawing_no' => 'DWG-C-001'],
            'D' => ['inspect_item' => 'Final Inspection', 'drawing_no' => 'DWG-D-001'],
        ];

        foreach ($validInputs as $category => $details) {
            $this->actingAs($production)
                ->post(route('rfi.store', ['kategori' => $category]), [
                    'project_id' => $project->id,
                    'date_inspect' => '2026-10-01',
                    'details' => $details,
                ])
                ->assertRedirect(route('rfi.category', ['kategori' => $category]));
        }

        foreach (['A', 'E', 'F'] as $category) {
            $this->actingAs($production)
                ->post(route('rfi.store', ['kategori' => $category]), [
                    'project_id' => $project->id,
                    'date_inspect' => '2026-10-01',
                    'scheduled_at' => '2026-10-02',
                    'details' => [],
                ])
                ->assertForbidden();
        }

        $this->assertDatabaseCount('rfis', 3);
    }

    public function test_admin_can_create_any_category_but_qc_qa_cannot_create_rfi(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $project = Project::factory()->create();
        $categoryInputs = [
            'A' => $this->categoryAInput($project),
            'B' => [
                'project_id' => $project->id,
                'date_inspect' => '2026-10-01',
                'details' => ['inspect_item' => 'Cutting', 'dimension' => '10 mm'],
            ],
            'C' => [
                'project_id' => $project->id,
                'date_inspect' => '2026-10-01',
                'details' => ['inspect_item' => 'Fit Up', 'drawing_no' => 'DWG-001'],
            ],
            'D' => [
                'project_id' => $project->id,
                'date_inspect' => '2026-10-01',
                'details' => ['inspect_item' => 'Final Inspection', 'drawing_no' => 'DWG-D-001'],
            ],
            'E' => [
                'project_id' => $project->id,
                'date_inspect' => '2026-10-01',
                'scheduled_at' => '2026-10-02',
                'details' => ['process' => 'Coating', 'drawing_no' => 'DWG-E-001'],
            ],
            'F' => [
                'project_id' => $project->id,
                'date_inspect' => '2026-10-01',
                'details' => ['jenis_inspeksi' => 'Welding inspection'],
            ],
        ];

        foreach ($categoryInputs as $category => $input) {
            $this->actingAs($admin)
                ->post(route('rfi.store', ['kategori' => $category]), $input)
                ->assertRedirect(route('rfi.category', ['kategori' => $category]));
        }

        $qc = User::factory()->create(['role' => 'qc_qa']);
        $this->actingAs($qc)
            ->post(route('rfi.store', ['kategori' => 'A']), $this->categoryAInput($project))
            ->assertForbidden();

        $this->assertDatabaseCount('rfis', 6);
    }

    public function test_ppic_can_create_category_f_inspection_by_customer(): void
    {
        $ppic = User::factory()->create(['role' => 'ppic']);
        $project = Project::factory()->create(['customer' => 'PT Sinar Energi']);

        // Must provide jenis_inspeksi
        $this->actingAs($ppic)
            ->post(route('rfi.store', ['kategori' => 'F']), [
                'project_id' => $project->id,
                'date_inspect' => '2026-10-01',
                'details' => [],
            ])
            ->assertSessionHasErrors('details.jenis_inspeksi');

        $this->assertDatabaseCount('rfis', 0);

        // Valid submission with standard jenis_inspeksi
        $this->post(route('rfi.store', ['kategori' => 'F']), [
            'project_id' => $project->id,
            'date_inspect' => '2026-10-01',
            'details' => ['jenis_inspeksi' => 'Welding inspection'],
        ])->assertRedirect(route('rfi.category', ['kategori' => 'F']));

        $rfi = Rfi::query()->firstOrFail();
        $this->assertSame('F', $rfi->category);
        $this->assertSame('Welding inspection', $rfi->details['jenis_inspeksi']);
        $this->assertSame('RFI-F-0001', $rfi->rfi_no);
    }

    public function test_category_f_additional_inspection_requires_additional_name(): void
    {
        $ppic = User::factory()->create(['role' => 'ppic']);
        $project = Project::factory()->create();

        // Additional without name should fail
        $this->actingAs($ppic)
            ->post(route('rfi.store', ['kategori' => 'F']), [
                'project_id' => $project->id,
                'date_inspect' => '2026-10-01',
                'details' => [
                    'jenis_inspeksi' => 'Additional',
                    'additional_name' => '',
                ],
            ])
            ->assertSessionHasErrors('details.additional_name');

        $this->assertDatabaseCount('rfis', 0);

        // Additional with name should succeed
        $this->post(route('rfi.store', ['kategori' => 'F']), [
            'project_id' => $project->id,
            'date_inspect' => '2026-10-01',
            'details' => [
                'jenis_inspeksi' => 'Additional',
                'additional_name' => 'Hydrotest',
            ],
        ])->assertRedirect(route('rfi.category', ['kategori' => 'F']));

        $rfi = Rfi::query()->firstOrFail();
        $this->assertSame('Additional', $rfi->details['jenis_inspeksi']);
        $this->assertSame('Hydrotest', $rfi->details['additional_name']);
    }

    public function test_category_f_create_form_renders_for_ppic(): void
    {
        $ppic = User::factory()->create(['role' => 'ppic']);

        $this->actingAs($ppic)
            ->get(route('rfi.create', ['kategori' => 'F']))
            ->assertOk()
            ->assertSee('Inspection by Customer')
            ->assertSee('Jenis inspeksi')
            ->assertSee('Welding inspection')
            ->assertSee('Additional');
    }

    public function test_production_cannot_create_category_f(): void
    {
        $production = User::factory()->create(['role' => 'production']);
        $project = Project::factory()->create();

        $this->actingAs($production)
            ->post(route('rfi.store', ['kategori' => 'F']), [
                'project_id' => $project->id,
                'date_inspect' => '2026-10-01',
                'details' => ['jenis_inspeksi' => 'Welding inspection'],
            ])
            ->assertForbidden();
    }

    public function test_testing_fabrication_requires_a_test_type(): void
    {
        $production = User::factory()->create(['role' => 'production']);
        $project = Project::factory()->create();

        $this->actingAs($production)
            ->post(route('rfi.store', ['kategori' => 'C']), [
                'project_id' => $project->id,
                'date_inspect' => '2026-10-01',
                'details' => ['inspect_item' => 'Testing'],
            ])
            ->assertSessionHasErrors('details.test_type');

        $this->assertDatabaseCount('rfis', 0);
    }

    public function test_ppic_must_provide_vendor_inspection_schedule(): void
    {
        $ppic = User::factory()->create(['role' => 'ppic']);
        $project = Project::factory()->create();
        $input = [
            'project_id' => $project->id,
            'date_inspect' => '2026-10-01',
            'details' => ['process' => 'Coating', 'drawing_no' => 'DWG-E-001'],
        ];

        $this->actingAs($ppic)
            ->post(route('rfi.store', ['kategori' => 'E']), $input)
            ->assertSessionHasErrors('scheduled_at');

        $this->assertDatabaseCount('rfis', 0);

        $this->post(route('rfi.store', ['kategori' => 'E']), [
            ...$input,
            'scheduled_at' => '2026-10-02',
        ])->assertRedirect(route('rfi.category', ['kategori' => 'E']));

        $rfi = Rfi::query()->firstOrFail();
        $this->assertSame('2026-10-02', $rfi->scheduled_at->toDateString());
        $this->assertSame('RFI-E-0001', $rfi->rfi_no);
    }

    public function test_qc_result_accepted_closes_rfi_and_records_review_metadata(): void
    {
        $qc = User::factory()->create(['role' => 'qc_qa']);
        $rfi = $this->makeRfi('A');

        $this->actingAs($qc)
            ->post(route('rfi.review', ['kategori' => 'A', 'rfi' => $rfi]), [
                'result' => RfiResult::Accepted->value,
            ])
            ->assertRedirect(route('rfi.category', ['kategori' => 'A']));

        $rfi->refresh();
        $this->assertSame('close', $rfi->status);
        $this->assertSame(RfiResult::Accepted, $rfi->result);
        $this->assertSame($qc->id, $rfi->reviewed_by);
        $this->assertSame('2026-09-28 13:00:00', $rfi->reviewed_at->toDateTimeString());
    }

    public function test_non_ok_qc_result_requires_note_and_sets_rfi_to_nc(): void
    {
        $qc = User::factory()->create(['role' => 'qc_qa']);
        $rfi = $this->makeRfi('B');
        $reviewUrl = route('rfi.review', ['kategori' => 'B', 'rfi' => $rfi]);

        $this->actingAs($qc)
            ->from(route('rfi.category', ['kategori' => 'B']))
            ->post($reviewUrl, ['result' => RfiResult::Repair->value])
            ->assertSessionHasErrors('qc_note');

        $this->assertSame('open', $rfi->fresh()->status);

        $this->post($reviewUrl, [
            'result' => RfiResult::Repair->value,
            'qc_note' => 'Perlu diperbaiki',
        ])->assertRedirect(route('rfi.category', ['kategori' => 'B']));

        $rfi->refresh();
        $this->assertSame('nc', $rfi->status);
        $this->assertSame(RfiResult::Repair, $rfi->result);
        $this->assertSame('Perlu diperbaiki', $rfi->qc_note);
    }

    public function test_qc_cannot_review_rfi_twice_or_overwrite_result(): void
    {
        $qc = User::factory()->create(['role' => 'qc_qa']);
        $rfi = $this->makeRfi('D');
        $reviewUrl = route('rfi.review', ['kategori' => 'D', 'rfi' => $rfi]);

        $this->actingAs($qc)
            ->post($reviewUrl, ['result' => RfiResult::Accepted->value])
            ->assertRedirect(route('rfi.category', ['kategori' => 'D']));

        $this->from(route('rfi.category', ['kategori' => 'D']))
            ->post($reviewUrl, [
                'result' => RfiResult::Reject->value,
                'qc_note' => 'Ditolak',
            ])
            ->assertSessionHasErrors('result');

        $rfi->refresh();
        $this->assertSame('close', $rfi->status);
        $this->assertSame(RfiResult::Accepted, $rfi->result);
        $this->assertNull($rfi->qc_note);
    }

    public function test_admin_can_give_first_qc_result(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $rfi = $this->makeRfi('B');

        $this->actingAs($admin)
            ->post(route('rfi.review', ['kategori' => 'B', 'rfi' => $rfi]), [
                'result' => RfiResult::UseAsIs->value,
                'qc_note' => 'Diterima dengan catatan',
            ])
            ->assertRedirect(route('rfi.category', ['kategori' => 'B']));

        $rfi->refresh();
        $this->assertSame('nc', $rfi->status);
        $this->assertSame(RfiResult::UseAsIs, $rfi->result);
        $this->assertSame($admin->id, $rfi->reviewed_by);
    }

    public function test_production_cannot_review_rfi(): void
    {
        $production = User::factory()->create(['role' => 'production']);
        $rfi = $this->makeRfi('B');

        $this->actingAs($production)
            ->post(route('rfi.review', ['kategori' => 'B', 'rfi' => $rfi]), [
                'result' => RfiResult::Accepted->value,
            ])
            ->assertForbidden();

        $this->assertSame('open', $rfi->fresh()->status);
    }

    public function test_rfi_status_cards_count_only_categories_visible_to_current_role(): void
    {
        $project = Project::factory()->create();
        $creator = User::factory()->create(['role' => 'warehouse']);
        $this->makeRfi('A', 'open', $project, $creator);
        $this->makeRfi('B', 'nc', $project, $creator);
        $this->makeRfi('E', 'close', $project, $creator);

        $warehouseResponse = $this->actingAs($creator)->get(route('rfi.index'))->assertOk();
        $warehouseResponse->assertViewHas('counts', [
            'open' => 1,
            'nc' => 0,
            'close' => 0,
        ]);

        $qc = User::factory()->create(['role' => 'qc_qa']);
        $this->actingAs($qc)->get(route('rfi.index'))
            ->assertViewHas('counts', [
                'open' => 1,
                'nc' => 1,
                'close' => 1,
            ]);

        $production = User::factory()->create(['role' => 'production']);
        $this->actingAs($production)->get(route('rfi.index'))
            ->assertViewHas('counts', [
                'open' => 0,
                'nc' => 1,
                'close' => 0,
            ]);

        $ppic = User::factory()->create(['role' => 'ppic']);
        $this->actingAs($ppic)->get(route('rfi.index'))
            ->assertViewHas('counts', [
                'open' => 0,
                'nc' => 0,
                'close' => 1,
            ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('rfi.index'))
            ->assertViewHas('counts', [
                'open' => 1,
                'nc' => 1,
                'close' => 1,
            ]);
    }

    public function test_category_list_shows_rfi_fields_deadline_status_and_status_filter(): void
    {
        $warehouse = User::factory()->create([
            'name' => 'Warehouse Requester',
            'role' => 'warehouse',
        ]);
        $project = Project::factory()->create([
            'project_code' => 'PRJ-RFI',
            'project_name' => 'RFI Project',
        ]);
        $openRfi = $this->makeRfi('A', 'open', $project, $warehouse);
        $openRfi->update([
            'rfi_no' => 'RFI-A-0001',
            'deadline_at' => Carbon::now('UTC')->addHours(12),
        ]);
        $ncRfi = $this->makeRfi('A', 'nc', $project, $warehouse);
        $ncRfi->update(['rfi_no' => 'RFI-A-0002']);

        $this->actingAs($warehouse)
            ->get(route('rfi.category', ['kategori' => 'A']))
            ->assertOk()
            ->assertSee('RFI-A-0001')
            ->assertSee('PRJ-RFI')
            ->assertSee('Warehouse Requester')
            ->assertSee('URGENT')
            ->assertSee('Buat RFI');

        $this->get(route('rfi.category', ['kategori' => 'A', 'status' => 'nc']))
            ->assertOk()
            ->assertSee('RFI-A-0002')
            ->assertDontSee('RFI-A-0001');
    }

    public function test_create_page_for_category_c_renders_conditional_fields(): void
    {
        $production = User::factory()->create(['role' => 'production']);

        $response = $this->actingAs($production)
            ->get(route('rfi.create', ['kategori' => 'C']))
            ->assertOk();

        $response->assertSee('Item inspeksi')
            ->assertSee('data-show-if-name="details[inspect_item]"', false)
            ->assertSee('data-show-if-value="Testing"', false)
            ->assertSee('Jenis pengujian');
    }

    public function test_empty_form_submission_renders_error_and_aria_invalid_under_project(): void
    {
        $warehouse = User::factory()->create(['role' => 'warehouse']);

        $response = $this->actingAs($warehouse)
            ->from(route('rfi.create', ['kategori' => 'A']))
            ->post(route('rfi.store', ['kategori' => 'A']), []);

        $response->assertRedirect(route('rfi.create', ['kategori' => 'A']));

        $follow = $this->followRedirects($response);
        $follow->assertSee('id="project_id-error"', false)
            ->assertSee('aria-invalid="true"', false);
    }

    public function test_submitting_category_e_without_scheduled_at_renders_error_on_jadwal_inspeksi(): void
    {
        $ppic = User::factory()->create(['role' => 'ppic']);
        $project = Project::factory()->create();

        $response = $this->actingAs($ppic)
            ->from(route('rfi.create', ['kategori' => 'E']))
            ->post(route('rfi.store', ['kategori' => 'E']), [
                'project_id' => $project->id,
                'date_inspect' => '2026-10-01',
                'details' => [
                    'process' => 'Painting',
                    'drawing_no' => 'DWG-E-001',
                ],
            ]);

        $response->assertRedirect(route('rfi.create', ['kategori' => 'E']));

        $follow = $this->followRedirects($response);
        $follow->assertSee('id="scheduled_at-error"', false)
            ->assertSee('Jadwal inspeksi');
    }

    public function test_category_page_status_filter_and_search_and_counts(): void
    {
        $warehouse = User::factory()->create(['role' => 'warehouse']);
        $projectAlpha = Project::factory()->create([
            'project_code' => 'ALPHA-001',
            'project_name' => 'Alpha Pressure Vessel',
        ]);
        $projectBeta = Project::factory()->create([
            'project_code' => 'BETA-002',
            'project_name' => 'Beta Storage Tank',
        ]);

        $rfiOpen = $this->makeRfi('A', 'open', $projectAlpha, $warehouse);
        $rfiOpen->update(['rfi_no' => 'RFI-A-0101']);

        $rfiNc = $this->makeRfi('A', 'nc', $projectBeta, $warehouse);
        $rfiNc->update(['rfi_no' => 'RFI-A-0102']);

        $rfiClose = $this->makeRfi('A', 'close', $projectAlpha, $warehouse);
        $rfiClose->update(['rfi_no' => 'RFI-A-0103']);

        // Check counts and unfiltered listing
        $response = $this->actingAs($warehouse)
            ->get(route('rfi.category', ['kategori' => 'A']))
            ->assertOk()
            ->assertViewHas('statusCounts', [
                'all' => 3,
                'open' => 1,
                'nc' => 1,
                'close' => 1,
            ]);

        $response->assertSee('RFI-A-0101')
            ->assertSee('RFI-A-0102')
            ->assertSee('RFI-A-0103');

        // Search by rfi_no
        $this->get(route('rfi.category', ['kategori' => 'A', 'q' => '0101']))
            ->assertOk()
            ->assertSee('RFI-A-0101')
            ->assertDontSee('RFI-A-0102');

        // Search by project name
        $this->get(route('rfi.category', ['kategori' => 'A', 'q' => 'Storage Tank']))
            ->assertOk()
            ->assertSee('RFI-A-0102')
            ->assertDontSee('RFI-A-0101');

        // Filter by status open
        $this->get(route('rfi.category', ['kategori' => 'A', 'status' => 'open']))
            ->assertOk()
            ->assertSee('RFI-A-0101')
            ->assertDontSee('RFI-A-0102')
            ->assertDontSee('RFI-A-0103');
    }

    public function test_category_page_deadline_chip_formatting(): void
    {
        $warehouse = User::factory()->create(['role' => 'warehouse']);
        $project = Project::factory()->create();

        // 1. Future deadline (2 days away)
        $rfiFuture = $this->makeRfi('A', 'open', $project, $warehouse);
        $rfiFuture->update([
            'rfi_no' => 'RFI-A-FUTURE',
            'deadline_at' => Carbon::now('UTC')->addDays(2),
        ]);

        // 2. Overdue deadline (1 day ago)
        $rfiOverdue = $this->makeRfi('A', 'open', $project, $warehouse);
        $rfiOverdue->update([
            'rfi_no' => 'RFI-A-OVERDUE',
            'deadline_at' => Carbon::now('UTC')->subDay(),
        ]);

        // 3. Close status
        $rfiClose = $this->makeRfi('A', 'close', $project, $warehouse);
        $rfiClose->update([
            'rfi_no' => 'RFI-A-CLOSED',
            'deadline_at' => Carbon::now('UTC')->addDay(),
        ]);

        $response = $this->actingAs($warehouse)
            ->get(route('rfi.category', ['kategori' => 'A']))
            ->assertOk();

        $response->assertSee('hari lagi')
            ->assertSee('Terlambat')
            ->assertSee('Selesai');
    }

    public function test_category_page_empty_state_and_filtered_empty_state(): void
    {
        $warehouse = User::factory()->create(['role' => 'warehouse']);

        // 1. Completely empty category
        $this->actingAs($warehouse)
            ->get(route('rfi.category', ['kategori' => 'A']))
            ->assertOk()
            ->assertSee('Belum ada RFI pada location ini.')
            ->assertSee('Buat RFI');

        // 2. Filtered with no matches
        $project = Project::factory()->create();
        $this->makeRfi('A', 'open', $project, $warehouse);

        $this->get(route('rfi.category', ['kategori' => 'A', 'q' => 'NONEXISTENT_QUERY']))
            ->assertOk()
            ->assertSee('Tidak ada RFI yang cocok')
            ->assertSee('Reset filter');
    }

    public function test_simpan_creates_draft_without_notification_and_without_deadline(): void
    {
        $warehouse = User::factory()->create(['role' => 'warehouse']);
        $project = Project::factory()->create();

        $this->actingAs($warehouse)
            ->post(route('rfi.store', ['kategori' => 'A']), [
                'project_id' => $project->id,
                'action' => 'draft',
            ])
            ->assertRedirect(route('rfi.category', ['kategori' => 'A']));

        $rfi = Rfi::query()->firstOrFail();
        $this->assertSame('draft', $rfi->status);
        $this->assertNull($rfi->submitted_at);
        $this->assertNull($rfi->deadline_at);
        $this->assertSame('RFI-A-0001', $rfi->rfi_no);
        $this->assertSame($warehouse->id, $rfi->created_by);
        $this->assertSame(0, DB::table('notifications')->count());
    }

    public function test_draft_is_not_visible_to_qc_qa_and_not_counted_in_status_cards(): void
    {
        $warehouse = User::factory()->create(['role' => 'warehouse']);
        $qc = User::factory()->create(['role' => 'qc_qa']);
        $project = Project::factory()->create();

        $draft = Rfi::query()->create([
            'project_id' => $project->id,
            'category' => 'A',
            'rfi_no' => 'RFI-A-0001',
            'status' => 'draft',
            'details' => [],
            'date_inspect' => '2026-10-01',
            'created_by' => $warehouse->id,
        ]);

        $openRfi = Rfi::query()->create([
            'project_id' => $project->id,
            'category' => 'A',
            'rfi_no' => 'RFI-A-0002',
            'status' => 'open',
            'date_inspect' => '2026-10-01',
            'details' => ['surat_jalan_no' => 'SJ-1', 'po_no' => 'PO-1', 'vendor' => 'V1'],
            'created_by' => $warehouse->id,
            'submitted_at' => Carbon::now('UTC'),
            'deadline_at' => Carbon::now('UTC')->addDay(),
        ]);

        // QC view
        $qcResponse = $this->actingAs($qc)->get(route('rfi.category', ['kategori' => 'A']));
        $qcResponse->assertOk()
            ->assertSee($openRfi->rfi_no)
            ->assertDontSee($draft->rfi_no)
            ->assertDontSee('Draft');

        // Cards check on index
        $indexResponse = $this->actingAs($qc)->get(route('rfi.index'));
        $indexResponse->assertOk();
        $counts = $indexResponse->viewData('counts');
        $this->assertSame(1, $counts['open']);

        // Warehouse view can see draft
        $whResponse = $this->actingAs($warehouse)->get(route('rfi.category', ['kategori' => 'A']));
        $whResponse->assertOk()
            ->assertSee($draft->rfi_no)
            ->assertSee('Draft');
    }

    public function test_kirim_ke_qc_validates_required_fields_and_submits_draft(): void
    {
        $warehouse = User::factory()->create(['role' => 'warehouse']);
        $qc = User::factory()->create(['role' => 'qc_qa']);
        $project = Project::factory()->create();

        $draft = Rfi::query()->create([
            'project_id' => $project->id,
            'category' => 'A',
            'rfi_no' => 'RFI-A-0001',
            'status' => 'draft',
            'details' => [],
            'date_inspect' => '2026-10-01',
            'created_by' => $warehouse->id,
        ]);

        // Validation failure when required fields are missing
        $this->actingAs($warehouse)
            ->put(route('rfi.update', ['kategori' => 'A', 'rfi' => $draft]), [
                'action' => 'submit',
                'project_id' => $project->id,
                'date_inspect' => '',
                'details' => [],
            ])
            ->assertSessionHasErrors(['date_inspect', 'details.surat_jalan_no', 'details.po_no', 'details.vendor']);

        $this->assertSame('draft', $draft->fresh()->status);

        // Validation success when required fields are filled
        $this->actingAs($warehouse)
            ->put(route('rfi.update', ['kategori' => 'A', 'rfi' => $draft]), [
                'action' => 'submit',
                'project_id' => $project->id,
                'date_inspect' => '2026-10-01',
                'details' => [
                    'surat_jalan_no' => 'SJ-888',
                    'po_no' => 'PO-999',
                    'vendor' => 'PT Baja Unggul',
                    'bom_no' => 'BOM-5',
                ],
            ])
            ->assertRedirect(route('rfi.category', ['kategori' => 'A']));

        $updated = $draft->fresh();
        $this->assertSame('open', $updated->status);
        $this->assertNotNull($updated->submitted_at);
        $this->assertNotNull($updated->deadline_at);
        $this->assertSame('SJ-888', $updated->details['surat_jalan_no']);

        // Notification created for QC
        $this->assertGreaterThan(0, DB::table('notifications')->count());
    }

    public function test_other_users_draft_cannot_be_viewed_edited_or_deleted_except_by_admin(): void
    {
        $warehouse1 = User::factory()->create(['role' => 'warehouse']);
        $warehouse2 = User::factory()->create(['role' => 'warehouse']);
        $admin = User::factory()->create(['role' => 'admin']);
        $project = Project::factory()->create();

        $draft = Rfi::query()->create([
            'project_id' => $project->id,
            'category' => 'A',
            'rfi_no' => 'RFI-A-0001',
            'status' => 'draft',
            'details' => [],
            'date_inspect' => '2026-10-01',
            'created_by' => $warehouse1->id,
        ]);

        // Warehouse 2 cannot edit, update, or delete
        $this->actingAs($warehouse2)
            ->get(route('rfi.edit', ['kategori' => 'A', 'rfi' => $draft]))
            ->assertForbidden();

        $this->actingAs($warehouse2)
            ->put(route('rfi.update', ['kategori' => 'A', 'rfi' => $draft]), [
                'action' => 'draft',
                'project_id' => $project->id,
            ])
            ->assertForbidden();

        $this->actingAs($warehouse2)
            ->delete(route('rfi.destroy', ['kategori' => 'A', 'rfi' => $draft]))
            ->assertForbidden();

        // Admin can edit, update, and delete
        $this->actingAs($admin)
            ->get(route('rfi.edit', ['kategori' => 'A', 'rfi' => $draft]))
            ->assertOk();

        $this->actingAs($admin)
            ->put(route('rfi.update', ['kategori' => 'A', 'rfi' => $draft]), [
                'action' => 'draft',
                'project_id' => $project->id,
                'note' => 'Catatan admin',
            ])
            ->assertRedirect(route('rfi.category', ['kategori' => 'A']));

        $this->assertSame('Catatan admin', $draft->fresh()->note);

        $this->actingAs($admin)
            ->delete(route('rfi.destroy', ['kategori' => 'A', 'rfi' => $draft]))
            ->assertRedirect(route('rfi.category', ['kategori' => 'A']));

        $this->assertDatabaseMissing('rfis', ['id' => $draft->id]);
    }

    public function test_submitted_rfi_cannot_be_edited(): void
    {
        $warehouse = User::factory()->create(['role' => 'warehouse']);
        $admin = User::factory()->create(['role' => 'admin']);
        $project = Project::factory()->create();

        $rfi = Rfi::query()->create([
            'project_id' => $project->id,
            'category' => 'A',
            'rfi_no' => 'RFI-A-0001',
            'status' => 'open',
            'date_inspect' => '2026-10-01',
            'details' => ['surat_jalan_no' => 'SJ-1', 'po_no' => 'PO-1', 'vendor' => 'V1'],
            'created_by' => $warehouse->id,
            'submitted_at' => Carbon::now('UTC'),
            'deadline_at' => Carbon::now('UTC')->addDay(),
        ]);

        $this->actingAs($warehouse)
            ->get(route('rfi.edit', ['kategori' => 'A', 'rfi' => $rfi]))
            ->assertForbidden();

        $this->actingAs($warehouse)
            ->put(route('rfi.update', ['kategori' => 'A', 'rfi' => $rfi]), ['project_id' => $project->id])
            ->assertForbidden();

        $this->actingAs($warehouse)
            ->delete(route('rfi.destroy', ['kategori' => 'A', 'rfi' => $rfi]))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('rfi.edit', ['kategori' => 'A', 'rfi' => $rfi]))
            ->assertForbidden();
    }

    public function test_categories_b_to_e_do_not_have_simpan_draft_button(): void
    {
        $production = User::factory()->create(['role' => 'production']);
        $warehouse = User::factory()->create(['role' => 'warehouse']);

        // Category B does not have Simpan button
        $responseB = $this->actingAs($production)->get(route('rfi.create', ['kategori' => 'B']));
        $responseB->assertOk()
            ->assertDontSee('value="draft"', false)
            ->assertDontSee('>Simpan<', false)
            ->assertSee('Kirim RFI');

        // Category A has Simpan and Kirim ke QC buttons
        $responseA = $this->actingAs($warehouse)->get(route('rfi.create', ['kategori' => 'A']));
        $responseA->assertOk()
            ->assertSee('value="draft"', false)
            ->assertSee('Simpan')
            ->assertSee('Kirim ke QC');
    }

    public function test_old_category_a_rfis_still_display_legacy_details(): void
    {
        $warehouse = User::factory()->create(['role' => 'warehouse']);
        $project = Project::factory()->create();

        $oldRfi = Rfi::query()->create([
            'project_id' => $project->id,
            'category' => 'A',
            'rfi_no' => 'RFI-A-9999',
            'status' => 'open',
            'date_inspect' => '2026-10-01',
            'details' => [
                'jenis_material' => 'Besi Siku',
                'spec' => 'ASTM A36',
                'quantity' => 25,
                'dimension' => '50x50x5',
            ],
            'created_by' => $warehouse->id,
            'submitted_at' => Carbon::now('UTC'),
            'deadline_at' => Carbon::now('UTC')->addDay(),
        ]);

        $response = $this->actingAs($warehouse)->get(route('rfi.category', ['kategori' => 'A']));
        $response->assertOk()
            ->assertSee('Detail lama:')
            ->assertSee('Jenis material: Besi Siku')
            ->assertSee('Spesifikasi: ASTM A36')
            ->assertSee('Jumlah: 25')
            ->assertSee('Dimensi: 50x50x5');
    }

    public function test_qc_can_view_category_a_detail_page_with_acc_and_finding_buttons(): void
    {
        $warehouse = User::factory()->create(['role' => 'warehouse']);
        $qc = User::factory()->create(['role' => 'qc_qa']);
        $project = Project::factory()->create(['project_code' => 'PRJ-MAT', 'project_name' => 'Material Hub']);

        $rfi = Rfi::query()->create([
            'project_id' => $project->id,
            'category' => 'A',
            'rfi_no' => 'RFI-A-0010',
            'status' => 'open',
            'date_inspect' => '2026-10-01',
            'note' => 'Cek pelat baja',
            'details' => [
                'surat_jalan_no' => 'SJ-777',
                'po_no' => 'PO-888',
                'vendor' => 'PT Krakatau Steel',
                'bom_no' => 'BOM-999',
            ],
            'created_by' => $warehouse->id,
            'submitted_at' => Carbon::now('UTC'),
            'deadline_at' => Carbon::now('UTC')->addDay(),
        ]);

        $response = $this->actingAs($qc)->get(route('rfi.show', ['kategori' => 'A', 'rfi' => $rfi]));
        $response->assertOk()
            ->assertSee('RFI-A-0010')
            ->assertSee('PRJ-MAT')
            ->assertSee('SJ-777')
            ->assertSee('PO-888')
            ->assertSee('PT Krakatau Steel')
            ->assertSee('BOM-999')
            ->assertSee('ACC')
            ->assertSee('Finding');
    }

    public function test_qc_clicking_acc_on_category_a_closes_rfi_and_notifies_warehouse(): void
    {
        $warehouse = User::factory()->create(['role' => 'warehouse']);
        $qc = User::factory()->create(['role' => 'qc_qa']);
        $project = Project::factory()->create();

        $rfi = Rfi::query()->create([
            'project_id' => $project->id,
            'category' => 'A',
            'rfi_no' => 'RFI-A-0011',
            'status' => 'open',
            'date_inspect' => '2026-10-01',
            'details' => ['surat_jalan_no' => 'SJ-1', 'po_no' => 'PO-1', 'vendor' => 'V1'],
            'created_by' => $warehouse->id,
            'submitted_at' => Carbon::now('UTC'),
            'deadline_at' => Carbon::now('UTC')->addDay(),
        ]);

        $this->actingAs($qc)
            ->post(route('rfi.review', ['kategori' => 'A', 'rfi' => $rfi]), [
                'result' => 'accepted',
            ])
            ->assertRedirect(route('rfi.category', ['kategori' => 'A']));

        $rfi->refresh();
        $this->assertSame('close', $rfi->status);
        $this->assertSame(RfiResult::Accepted, $rfi->result);
        $this->assertSame($qc->id, $rfi->reviewed_by);

        // Warehouse received rfi.reviewed_ok notification
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $warehouse->id,
        ]);
    }

    public function test_qc_clicking_finding_on_category_a_creates_qc_finding_and_redirects_to_qc_finding(): void
    {
        $warehouse = User::factory()->create(['role' => 'warehouse']);
        $qc = User::factory()->create(['role' => 'qc_qa']);
        $project = Project::factory()->create();

        $rfi = Rfi::query()->create([
            'project_id' => $project->id,
            'category' => 'A',
            'rfi_no' => 'RFI-A-0012',
            'status' => 'open',
            'date_inspect' => '2026-10-01',
            'details' => ['surat_jalan_no' => 'SJ-1', 'po_no' => 'PO-1', 'vendor' => 'V1'],
            'created_by' => $warehouse->id,
            'submitted_at' => Carbon::now('UTC'),
            'deadline_at' => Carbon::now('UTC')->addDay(),
        ]);

        $response = $this->actingAs($qc)->post(route('rfi.finding', ['kategori' => 'A', 'rfi' => $rfi]));

        $this->assertDatabaseMissing('qc_findings', ['rfi_id' => $rfi->id]);
        $this->assertSame('open', $rfi->fresh()->status);
        $this->assertNull($rfi->fresh()->result);

        $response->assertRedirect(route('qc-finding.project', [
            'project' => $project->id,
            'rfi_baru' => $rfi->id,
        ]));
    }

    public function test_qc_action_incoming_material_use_as_is(): void
    {
        $warehouse = User::factory()->create(['role' => 'warehouse']);
        $qc = User::factory()->create(['role' => 'qc_qa']);
        $project = Project::factory()->create();

        $rfi = Rfi::query()->create([
            'project_id' => $project->id,
            'category' => 'A',
            'rfi_no' => 'RFI-A-0013',
            'status' => 'nc',
            'date_inspect' => '2026-10-01',
            'details' => ['surat_jalan_no' => 'SJ-1', 'po_no' => 'PO-1', 'vendor' => 'V1'],
            'created_by' => $warehouse->id,
            'submitted_at' => Carbon::now('UTC'),
            'deadline_at' => Carbon::now('UTC')->addDay(),
        ]);

        $finding = QcFinding::query()->create([
            'rfi_id' => $rfi->id,
            'result' => RfiResult::Repair,
            'finding_list' => 'Incoming Material Finding',
            'date_of_finding' => '2026-09-28',
            'status' => 'open',
        ]);

        $response = $this->actingAs($qc)->post(route('qc-finding.incoming-action', ['finding' => $finding]), [
            'material' => 'Pipa Carbon Steel 4 inch',
            'reason_of_finding' => 'Ada karat tipis di permukaan',
            'result' => 'use_as_is',
        ]);

        $response->assertRedirect(route('qc-finding.project', ['project' => $project->id]));

        $finding->refresh();
        $this->assertSame('closed', $finding->status);
        $this->assertSame(RfiResult::UseAsIs, $finding->result);
        $this->assertSame('Ada karat tipis di permukaan', $finding->finding_list);
        $this->assertSame('Pipa Carbon Steel 4 inch', $finding->action_plan);

        $rfi->refresh();
        $this->assertSame('close', $rfi->status);
        $this->assertSame(RfiResult::UseAsIs, $rfi->result);
        $this->assertSame('Pipa Carbon Steel 4 inch', $rfi->details['material']);
    }

    public function test_qc_action_incoming_material_supports_multiple_materials(): void
    {
        $warehouse = User::factory()->create(['role' => 'warehouse']);
        $qc = User::factory()->create(['role' => 'qc_qa']);
        $project = Project::factory()->create();

        $rfi = Rfi::query()->create([
            'project_id' => $project->id,
            'category' => 'A',
            'rfi_no' => 'RFI-A-0020',
            'status' => 'nc',
            'date_inspect' => '2026-10-01',
            'details' => ['surat_jalan_no' => 'SJ-20', 'po_no' => 'PO-20', 'vendor' => 'V20'],
            'created_by' => $warehouse->id,
            'submitted_at' => Carbon::now('UTC'),
            'deadline_at' => Carbon::now('UTC')->addDay(),
        ]);

        $finding = QcFinding::query()->create([
            'rfi_id' => $rfi->id,
            'result' => RfiResult::Repair,
            'finding_list' => 'Incoming Material Finding',
            'date_of_finding' => '2026-09-28',
            'status' => 'open',
        ]);

        $response = $this->actingAs($qc)->post(route('qc-finding.incoming-action', ['finding' => $finding]), [
            'material' => ['Pipa Carbon Steel 4 inch', 'Flange 4 inch ANSI 150', 'Fitting Elbow 90'],
            'reason_of_finding' => 'Terdapat cacat visual pada batch material ini',
            'result' => 'use_as_is',
        ]);

        $response->assertRedirect(route('qc-finding.project', ['project' => $project->id]));

        $finding->refresh();
        $this->assertSame('closed', $finding->status);
        $this->assertSame('Pipa Carbon Steel 4 inch, Flange 4 inch ANSI 150, Fitting Elbow 90', $finding->action_plan);

        $rfi->refresh();
        $this->assertSame(['Pipa Carbon Steel 4 inch', 'Flange 4 inch ANSI 150', 'Fitting Elbow 90'], $rfi->details['material']);

        // Check project finding page renders the materials properly
        $pageResponse = $this->actingAs($qc)->get(route('qc-finding.project', ['project' => $project->id]));
        $pageResponse->assertOk()
            ->assertSee('Pipa Carbon Steel 4 inch')
            ->assertSee('Flange 4 inch ANSI 150')
            ->assertSee('Fitting Elbow 90');
    }

    public function test_qc_action_incoming_material_supports_table_rows_with_multiple_materials_and_reasons(): void
    {
        $warehouse = User::factory()->create(['role' => 'warehouse']);
        $qc = User::factory()->create(['role' => 'qc_qa']);
        $project = Project::factory()->create();

        $rfi = Rfi::query()->create([
            'project_id' => $project->id,
            'category' => 'A',
            'rfi_no' => 'RFI-A-0021',
            'status' => 'nc',
            'date_inspect' => '2026-10-01',
            'details' => ['surat_jalan_no' => 'SJ-21', 'po_no' => 'PO-21', 'vendor' => 'V21'],
            'created_by' => $warehouse->id,
            'submitted_at' => Carbon::now('UTC'),
            'deadline_at' => Carbon::now('UTC')->addDay(),
        ]);

        $finding = QcFinding::query()->create([
            'rfi_id' => $rfi->id,
            'result' => RfiResult::Repair,
            'finding_list' => 'Incoming Material Finding',
            'date_of_finding' => '2026-09-28',
            'status' => 'open',
        ]);

        $response = $this->actingAs($qc)->post(route('qc-finding.incoming-action', ['finding' => $finding]), [
            'items' => [
                [
                    'materials' => ['Pipa CS 4 inch', 'Flange 4 inch'],
                    'reason' => 'Ketebalan tidak sesuai',
                ],
                [
                    'materials' => ['Fitting Elbow 90'],
                    'reason' => 'Sudut potong miring',
                ],
            ],
            'result' => 'use_as_is',
        ]);

        $response->assertRedirect(route('qc-finding.project', ['project' => $project->id]));

        $finding->refresh();
        $this->assertSame('closed', $finding->status);
        $this->assertSame("1. Ketebalan tidak sesuai\n2. Sudut potong miring", $finding->finding_list);
        $this->assertSame('Pipa CS 4 inch, Flange 4 inch, Fitting Elbow 90', $finding->action_plan);

        $rfi->refresh();
        $this->assertSame('close', $rfi->status);
        $this->assertCount(2, $rfi->details['incoming_findings']);
        $this->assertSame(['Pipa CS 4 inch', 'Flange 4 inch'], $rfi->details['incoming_findings'][0]['materials']);
        $this->assertSame('Ketebalan tidak sesuai', $rfi->details['incoming_findings'][0]['reason']);

        // Check project finding page renders the table
        $pageResponse = $this->actingAs($qc)->get(route('qc-finding.project', ['project' => $project->id]));
        $pageResponse->assertOk()
            ->assertSee('Pipa CS 4 inch')
            ->assertSee('Ketebalan tidak sesuai')
            ->assertSee('Fitting Elbow 90')
            ->assertSee('Sudut potong miring');
    }

    public function test_qc_action_incoming_material_reject_sends_notification_to_warehouse(): void
    {
        $warehouse = User::factory()->create(['role' => 'warehouse']);
        $qc = User::factory()->create(['role' => 'qc_qa']);
        $project = Project::factory()->create();

        $rfi = Rfi::query()->create([
            'project_id' => $project->id,
            'category' => 'A',
            'rfi_no' => 'RFI-A-0014',
            'status' => 'nc',
            'date_inspect' => '2026-10-01',
            'details' => ['surat_jalan_no' => 'SJ-1', 'po_no' => 'PO-1', 'vendor' => 'V1'],
            'created_by' => $warehouse->id,
            'submitted_at' => Carbon::now('UTC'),
            'deadline_at' => Carbon::now('UTC')->addDay(),
        ]);

        $finding = QcFinding::query()->create([
            'rfi_id' => $rfi->id,
            'result' => RfiResult::Repair,
            'finding_list' => 'Incoming Material Finding',
            'date_of_finding' => '2026-09-28',
            'status' => 'open',
        ]);

        $initNotificationCount = DB::table('notifications')->count();

        $response = $this->actingAs($qc)->post(route('qc-finding.incoming-action', ['finding' => $finding]), [
            'material' => 'Plat Baja 12mm',
            'reason_of_finding' => 'Ketebalan tidak sesuai spesifikasi PO',
            'result' => 'reject',
        ]);

        $response->assertRedirect(route('qc-finding.project', ['project' => $project->id]));

        $finding->refresh();
        $this->assertSame('closed', $finding->status);
        $this->assertSame(RfiResult::Reject, $finding->result);

        $rfi->refresh();
        $this->assertSame('nc', $rfi->status);
        $this->assertSame(RfiResult::Reject, $rfi->result);

        // Warehouse receives reject notification
        $this->assertGreaterThan($initNotificationCount, DB::table('notifications')->count());
        $notification = DB::table('notifications')->where('notifiable_id', $warehouse->id)->latest('id')->first();
        $this->assertNotNull($notification);
        $data = json_decode($notification->data, true);
        $this->assertSame('finding.warehouse_rejected', $data['type']);
        $this->assertStringContainsString('Reject', $data['title']);
    }

    public function test_show_rfi_authorization_and_status_codes(): void
    {
        $warehouse = User::factory()->create(['role' => 'warehouse']);
        $qc = User::factory()->create(['role' => 'qc_qa']);
        $production = User::factory()->create(['role' => 'production']);
        $project = Project::factory()->create();

        $rfiA = $this->makeRfi('A', 'open', $project, $warehouse);
        $rfiB = $this->makeRfi('B', 'open', $project, $production);

        // 1. Authorized role returns 200
        $this->actingAs($warehouse)->get(route('rfi.show', ['kategori' => 'A', 'rfi' => $rfiA]))->assertOk();
        $this->actingAs($qc)->get(route('rfi.show', ['kategori' => 'A', 'rfi' => $rfiA]))->assertOk();
        $this->actingAs($production)->get(route('rfi.show', ['kategori' => 'B', 'rfi' => $rfiB]))->assertOk();

        // 2. 404 when category does not match RFI category
        $this->actingAs($qc)->get('/rfi/B/'.$rfiA->id)->assertNotFound();
        $this->actingAs($qc)->get('/rfi/A/'.$rfiB->id)->assertNotFound();

        // 3. 403 for role without category access
        $this->actingAs($warehouse)->get(route('rfi.show', ['kategori' => 'B', 'rfi' => $rfiB]))->assertForbidden();
    }

    public function test_tindakan_qc_card_shown_only_for_qc_admin_on_open_rfi(): void
    {
        $warehouse = User::factory()->create(['role' => 'warehouse']);
        $qc = User::factory()->create(['role' => 'qc_qa']);
        $project = Project::factory()->create();

        $rfi = $this->makeRfi('A', 'open', $project, $warehouse);

        // QC on open RFI: sees Tindakan QC, ACC and Finding, does NOT see Hasil QC
        $qcResponse = $this->actingAs($qc)->get(route('rfi.show', ['kategori' => 'A', 'rfi' => $rfi]));
        $qcResponse->assertOk()
            ->assertSee('id="tindakan-qc"', false)
            ->assertSee('Keputusan QC')
            ->assertSee('ACC')
            ->assertSee('Finding')
            ->assertDontSee('id="hasil-qc"', false);

        // Warehouse on open RFI: does NOT see Tindakan QC card
        $whResponse = $this->actingAs($warehouse)->get(route('rfi.show', ['kategori' => 'A', 'rfi' => $rfi]));
        $whResponse->assertOk()
            ->assertDontSee('id="tindakan-qc"', false)
            ->assertSee('Menunggu review dari tim QC / QA.');

        // When reviewed (closed), QC sees Hasil QC instead of Tindakan QC
        $rfi->update(['status' => 'close', 'result' => RfiResult::Accepted, 'reviewed_by' => $qc->id, 'reviewed_at' => Carbon::now('UTC')]);
        $closedResponse = $this->actingAs($qc)->get(route('rfi.show', ['kategori' => 'A', 'rfi' => $rfi]));
        $closedResponse->assertOk()
            ->assertDontSee('id="tindakan-qc"', false)
            ->assertSee('Hasil QC')
            ->assertSee('Accepted');
    }

    public function test_post_rfi_review_from_new_form_acc_and_finding(): void
    {
        $warehouse = User::factory()->create(['role' => 'warehouse']);
        $qc = User::factory()->create(['role' => 'qc_qa']);
        $project = Project::factory()->create();

        // 1. Review ACC
        $rfiAcc = $this->makeRfi('A', 'open', $project, $warehouse);
        $this->actingAs($qc)
            ->post(route('rfi.review', ['kategori' => 'A', 'rfi' => $rfiAcc]), [
                'result' => 'accepted',
            ])
            ->assertRedirect(route('rfi.category', ['kategori' => 'A']));

        $rfiAcc->refresh();
        $this->assertSame('close', $rfiAcc->status);
        $this->assertSame(RfiResult::Accepted, $rfiAcc->result);

        // 2. Review Finding (Repair) on category A is rejected (422)
        $rfiFindingA = $this->makeRfi('A', 'open', $project, $warehouse);
        $this->actingAs($qc)
            ->post(route('rfi.review', ['kategori' => 'A', 'rfi' => $rfiFindingA]), [
                'result' => 'repair',
                'qc_note' => 'Dimensi plat kurang 2 mm dari spesifikasi',
            ])
            ->assertStatus(422);

        // 3. Review Finding (Repair) on category B is accepted
        $production = User::factory()->create(['role' => 'production']);
        $rfiFindingB = $this->makeRfi('B', 'open', $project, $production);
        $this->actingAs($qc)
            ->post(route('rfi.review', ['kategori' => 'B', 'rfi' => $rfiFindingB]), [
                'result' => 'repair',
                'qc_note' => 'Dimensi plat kurang 2 mm dari spesifikasi',
            ])
            ->assertRedirect(route('rfi.category', ['kategori' => 'B']));

        $rfiFindingB->refresh();
        $this->assertSame('nc', $rfiFindingB->status);
        $this->assertSame(RfiResult::Repair, $rfiFindingB->result);
        $this->assertNotNull($rfiFindingB->qcFinding);
        $this->assertSame('Dimensi plat kurang 2 mm dari spesifikasi', $rfiFindingB->qcFinding->finding_list);
    }

    public function test_category_a_review_rejects_all_non_accepted_results_while_other_categories_accept_them(): void
    {
        $qc = User::factory()->create(['role' => 'qc_qa']);
        $warehouse = User::factory()->create(['role' => 'warehouse']);
        $production = User::factory()->create(['role' => 'production']);
        $project = Project::factory()->create();

        // Category A with Reject -> 422
        $rfiA1 = $this->makeRfi('A', 'open', $project, $warehouse);
        $this->actingAs($qc)->post(route('rfi.review', ['kategori' => 'A', 'rfi' => $rfiA1]), [
            'result' => 'reject',
            'qc_note' => 'Kualitas buruk',
        ])->assertStatus(422);

        // Category A with Use as is -> 422
        $rfiA2 = $this->makeRfi('A', 'open', $project, $warehouse);
        $this->actingAs($qc)->post(route('rfi.review', ['kategori' => 'A', 'rfi' => $rfiA2]), [
            'result' => 'use_as_is',
            'qc_note' => 'Ada goresan',
        ])->assertStatus(422);

        // Categories B to E with non-accepted results -> accepted (redirect)
        foreach (['B', 'C', 'D', 'E'] as $cat) {
            $rfi = $this->makeRfi($cat, 'open', $project, $production);
            $this->actingAs($qc)->post(route('rfi.review', ['kategori' => $cat, 'rfi' => $rfi]), [
                'result' => 'repair',
                'qc_note' => "Cat {$cat} perlu perbaikan",
            ])->assertRedirect(route('rfi.category', ['kategori' => $cat]));
        }
    }

    public function test_fabrication_form_has_general_fields_and_no_detail_section(): void
    {
        $production = User::factory()->create(['role' => 'production']);
        $project = Project::factory()->create();

        // 1. Check create form
        $res = $this->actingAs($production)->get(route('rfi.create', ['kategori' => 'C']));
        $res->assertOk()
            ->assertSee('Informasi umum')
            ->assertDontSee('Detail Fabrication')
            ->assertSee('Drawing No')
            ->assertDontSee('Drawing No dari PPIC')
            ->assertSee('Fit Up')
            ->assertSee('Welding')
            ->assertSee('Before Blasting')
            ->assertSee('Testing')
            ->assertDontSee('Pre-Assembly')
            ->assertSee('Leak Test')
            ->assertDontSee('Lift');

        // 2. Submit with Welding
        $this->actingAs($production)
            ->post(route('rfi.store', ['kategori' => 'C']), [
                'project_id' => $project->id,
                'date_inspect' => '2026-10-01',
                'details' => [
                    'drawing_no' => 'DWG-WELD-99',
                    'inspect_item' => 'Welding',
                ],
            ])
            ->assertRedirect(route('rfi.category', ['kategori' => 'C']));

        $rfiWelding = Rfi::where('category', 'C')->latest('id')->first();
        $this->assertNotNull($rfiWelding);
        $this->assertSame('DWG-WELD-99', $rfiWelding->details['drawing_no']);
        $this->assertSame('Welding', $rfiWelding->details['inspect_item']);

        // 3. Submit with Testing + Leak Test
        $this->actingAs($production)
            ->post(route('rfi.store', ['kategori' => 'C']), [
                'project_id' => $project->id,
                'date_inspect' => '2026-10-01',
                'details' => [
                    'drawing_no' => 'DWG-LEAK-01',
                    'inspect_item' => 'Testing',
                    'test_type' => 'Leak Test',
                ],
            ])
            ->assertRedirect(route('rfi.category', ['kategori' => 'C']));

        $rfiLeak = Rfi::where('category', 'C')->latest('id')->first();
        $this->assertNotNull($rfiLeak);
        $this->assertSame('Leak Test', $rfiLeak->details['test_type']);

        // 4. Missing Drawing No should fail
        $this->actingAs($production)
            ->post(route('rfi.store', ['kategori' => 'C']), [
                'project_id' => $project->id,
                'date_inspect' => '2026-10-01',
                'details' => [
                    'drawing_no' => '',
                    'inspect_item' => 'Fit Up',
                ],
            ])
            ->assertSessionHasErrors('details.drawing_no');

        // 5. Show page verifies details in Informasi Umum
        $showRes = $this->actingAs($production)->get(route('rfi.show', ['kategori' => 'C', 'rfi' => $rfiLeak]));
        $showRes->assertOk()
            ->assertSee('DWG-LEAK-01')
            ->assertSee('Testing')
            ->assertSee('Leak Test');
    }

    public function test_rfi_category_b_preparation_fields_and_submission(): void
    {
        $production = User::factory()->create(['role' => 'production']);
        $project = Project::factory()->create();

        // Check create form has Item Name, MTR no., Drawing no
        $res = $this->actingAs($production)->get(route('rfi.create', ['kategori' => 'B']));
        $res->assertOk()
            ->assertSee('Item Name')
            ->assertSee('MTR no.')
            ->assertSee('Drawing no')
            ->assertDontSee('Dimensi');

        // Submit Category B
        $postRes = $this->actingAs($production)->post(route('rfi.store', ['kategori' => 'B']), [
            'project_id' => $project->id,
            'date_inspect' => '2026-10-01',
            'details' => [
                'item_name' => 'Steel Plate Grade A',
                'mtr_no' => 'MTR-998877',
                'drawing_no' => 'DWG-PREP-01',
                'inspect_item' => 'Cutting',
            ],
        ]);
        $postRes->assertRedirect(route('rfi.category', ['kategori' => 'B']));

        $rfi = Rfi::where('category', 'B')->latest('id')->first();
        $this->assertNotNull($rfi);
        $this->assertSame('Steel Plate Grade A', $rfi->details['item_name']);
        $this->assertSame('MTR-998877', $rfi->details['mtr_no']);
        $this->assertSame('DWG-PREP-01', $rfi->details['drawing_no']);

        // Check show page
        $showRes = $this->actingAs($production)->get(route('rfi.show', ['kategori' => 'B', 'rfi' => $rfi]));
        $showRes->assertOk()
            ->assertSee('Steel Plate Grade A')
            ->assertSee('MTR-998877')
            ->assertSee('DWG-PREP-01');
    }

    public function test_rfi_category_c_fabrication_testing_supports_multiple_and_custom_tests(): void
    {
        $production = User::factory()->create(['role' => 'production']);
        $project = Project::factory()->create();

        $postRes = $this->actingAs($production)->post(route('rfi.store', ['kategori' => 'C']), [
            'project_id' => $project->id,
            'date_inspect' => '2026-10-01',
            'details' => [
                'drawing_no' => 'DWG-FAB-100',
                'inspect_item' => 'Testing',
                'test_type' => ['Leak Test', 'Hydro', 'Ultrasonic Special'],
            ],
        ]);
        $postRes->assertRedirect(route('rfi.category', ['kategori' => 'C']));

        $rfi = Rfi::where('category', 'C')->latest('id')->first();
        $this->assertNotNull($rfi);
        $this->assertSame(['Leak Test', 'Hydro', 'Ultrasonic Special'], $rfi->details['test_type']);

        $showRes = $this->actingAs($production)->get(route('rfi.show', ['kategori' => 'C', 'rfi' => $rfi]));
        $showRes->assertOk()
            ->assertSee('Leak Test')
            ->assertSee('Hydro')
            ->assertSee('Ultrasonic Special');
    }

    private function categoryAInput(Project $project): array
    {
        return [
            'project_id' => $project->id,
            'date_inspect' => '2026-10-01',
            'note' => 'Penerimaan material',
            'details' => [
                'surat_jalan_no' => 'SJ-123',
                'po_no' => 'PO-456',
                'vendor' => 'PT Baja Perkasa',
                'bom_no' => 'BOM-001',
            ],
        ];
    }

    private function makeRfi(
        string $category,
        string $status = 'open',
        ?Project $project = null,
        ?User $creator = null
    ): Rfi {
        $project ??= Project::factory()->create();
        $creator ??= User::factory()->create(['role' => 'production']);

        return Rfi::query()->create([
            'project_id' => $project->id,
            'category' => $category,
            'details' => ['inspect_item' => 'Cutting'],
            'date_inspect' => '2026-10-01',
            'status' => $status,
            'created_by' => $creator->id,
            'submitted_at' => Carbon::now('UTC'),
            'deadline_at' => Carbon::now('UTC')->addDay(),
        ]);
    }
}
