<?php

namespace Tests\Feature;

use App\Enums\MdrStatus;
use App\Exports\MdrDetailExport;
use App\Exports\MdrListExport;
use App\Models\Mdr;
use App\Models\MdrReportStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class MdrChecklistTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateTestDatabase();
        $this->withoutVite();
    }

    public function test_new_mdr_pages_use_all_sixteen_reports_from_config(): void
    {
        $qc = User::factory()->create(['role' => 'qc_qa']);
        $project = Project::factory()->create([
            'project_name' => 'MDR New Project',
            'customer' => 'MDR Customer',
        ]);

        $this->actingAs($qc)
            ->get(route('mdr-checklist.create'))
            ->assertOk()
            ->assertSee('data-customer="MDR Customer"', false);

        $response = $this->get(route('mdr-checklist.create'));
        $response->assertViewHas('reports', config('mudalaya.mdr_reports'));

        foreach (config('mudalaya.mdr_reports') as $reportLabel) {
            $response->assertSee($reportLabel);
        }

        $this->assertSame(16, count(config('mudalaya.mdr_reports')));
        $this->assertDatabaseMissing('mdrs', ['project_id' => $project->id]);
    }

    public function test_creating_mdr_creates_all_configured_report_statuses_as_open(): void
    {
        $qc = User::factory()->create(['role' => 'qc_qa']);
        $project = Project::factory()->create();
        $statuses = array_fill_keys(array_keys(config('mudalaya.mdr_reports')), MdrStatus::Open->value);

        $this->actingAs($qc)
            ->post(route('mdr-checklist.store'), [
                'project_id' => $project->id,
                'unit_no' => 'Unit 1',
                'statuses' => [
                    ...$statuses,
                    'unconfigured_report' => 'invalid',
                ],
            ])
            ->assertRedirect();

        $mdr = Mdr::query()->where('project_id', $project->id)->firstOrFail();
        $this->assertSame('Unit 1', $mdr->unit_no);
        $this->assertSame($qc->id, $mdr->created_by);
        $this->assertDatabaseCount('mdr_report_statuses', 16);
        $this->assertSame(16, $mdr->reportStatuses()->where('status', MdrStatus::Open->value)->count());
        $this->assertSame(MdrStatus::Open, $mdr->overall_status);
    }

    public function test_project_with_existing_mdr_is_still_visible_in_form_dropdown(): void
    {
        $qc = User::factory()->create(['role' => 'qc_qa']);
        $project = Project::factory()->create(['project_name' => 'Existing MDR Project']);
        $mdr = Mdr::factory()->for($project)->create(['unit_no' => 'Unit 1']);
        $this->createDefaultStatuses($mdr);

        $this->actingAs($qc)
            ->get(route('mdr-checklist.create'))
            ->assertOk()
            ->assertSee('Existing MDR Project');
    }

    public function test_two_different_units_on_same_project_are_allowed(): void
    {
        $qc = User::factory()->create(['role' => 'qc_qa']);
        $project = Project::factory()->create();
        $statuses = array_fill_keys(array_keys(config('mudalaya.mdr_reports')), 'open');

        $this->actingAs($qc)
            ->post(route('mdr-checklist.store'), [
                'project_id' => $project->id,
                'unit_no' => 'Unit 1',
                'statuses' => $statuses,
            ])
            ->assertRedirect();

        $this->post(route('mdr-checklist.store'), [
            'project_id' => $project->id,
            'unit_no' => 'Unit 2',
            'statuses' => $statuses,
        ])->assertRedirect();

        $this->assertDatabaseCount('mdrs', 2);
        $this->assertDatabaseHas('mdrs', ['project_id' => $project->id, 'unit_no' => 'Unit 1']);
        $this->assertDatabaseHas('mdrs', ['project_id' => $project->id, 'unit_no' => 'Unit 2']);
    }

    public function test_same_unit_on_same_project_is_rejected_by_validation(): void
    {
        $qc = User::factory()->create(['role' => 'qc_qa']);
        $project = Project::factory()->create();
        $mdr = Mdr::factory()->for($project)->create(['unit_no' => 'Unit 1']);
        $this->createDefaultStatuses($mdr);
        $statuses = array_fill_keys(array_keys(config('mudalaya.mdr_reports')), 'open');

        $this->actingAs($qc)
            ->post(route('mdr-checklist.store'), [
                'project_id' => $project->id,
                'unit_no' => 'Unit 1',
                'statuses' => $statuses,
            ])
            ->assertSessionHasErrors('unit_no');

        $this->assertDatabaseCount('mdrs', 1);
    }

    public function test_status_outside_enum_is_rejected(): void
    {
        $qc = User::factory()->create(['role' => 'qc_qa']);
        $project = Project::factory()->create();
        $statuses = array_fill_keys(array_keys(config('mudalaya.mdr_reports')), 'open');
        $firstReportKey = array_key_first($statuses);
        $statuses[$firstReportKey] = 'pending';

        $this->actingAs($qc)
            ->post(route('mdr-checklist.store'), [
                'project_id' => $project->id,
                'unit_no' => 'Unit 1',
                'statuses' => $statuses,
            ])
            ->assertSessionHasErrors("statuses.{$firstReportKey}");

        $this->assertDatabaseCount('mdrs', 0);
    }

    public function test_updated_report_status_records_user_and_overall_status_requires_all_reports_complete(): void
    {
        $qc = User::factory()->create(['role' => 'qc_qa']);
        $project = Project::factory()->create();
        $mdr = Mdr::factory()->for($project)->create();
        $this->createDefaultStatuses($mdr);
        $reportKeys = array_keys(config('mudalaya.mdr_reports'));
        $firstReportKey = $reportKeys[0];
        $updatedReportKey = $reportKeys[1];
        $statuses = array_fill_keys($reportKeys, MdrStatus::Close->value);
        $statuses[$reportKeys[array_key_last($reportKeys)]] = MdrStatus::NotApplicable->value;
        $statuses[$firstReportKey] = MdrStatus::Open->value;

        $this->actingAs($qc)
            ->put(route('mdr-checklist.update', ['mdr' => $mdr]), [
                'statuses' => [
                    ...$statuses,
                    'unconfigured_report' => 'not-a-status',
                ],
            ])
            ->assertRedirect(route('mdr-checklist.show', ['mdr' => $mdr]));

        $this->assertSame(MdrStatus::Open, $mdr->fresh()->overall_status);
        $this->assertDatabaseHas('mdr_report_statuses', [
            'mdr_id' => $mdr->id,
            'report_key' => $updatedReportKey,
            'status' => MdrStatus::Close->value,
            'updated_by' => $qc->id,
        ]);

        $statuses[$firstReportKey] = MdrStatus::Close->value;

        $this->put(route('mdr-checklist.update', ['mdr' => $mdr]), [
            'statuses' => $statuses,
        ])->assertRedirect();

        $this->assertSame(MdrStatus::Close, $mdr->fresh()->overall_status);
        $this->assertSame(16, $mdr->reportStatuses()->whereIn('status', ['close', 'na'])->count());
    }

    public function test_listing_shows_correct_progress_and_filters_open_or_closed_status(): void
    {
        $qc = User::factory()->create(['role' => 'qc_qa']);
        $openProject = Project::factory()->create(['project_name' => 'Open MDR Project']);
        $openMdr = Mdr::factory()->for($openProject)->create();
        $this->createDefaultStatuses($openMdr);
        $closeProject = Project::factory()->create(['project_name' => 'Closed MDR Project']);
        $closedMdr = Mdr::factory()->for($closeProject)->create();
        $this->createDefaultStatuses($closedMdr, MdrStatus::Close->value);

        $this->actingAs($qc)
            ->get(route('mdr-checklist.index'))
            ->assertOk()
            ->assertSee('0 dari 16')
            ->assertSee('Open MDR Project')
            ->assertSee('16 dari 16')
            ->assertSee('Closed MDR Project');

        $this->get(route('mdr-checklist.index', ['status' => 'open']))
            ->assertSee('Open MDR Project')
            ->assertDontSee('Closed MDR Project');

        $this->get(route('mdr-checklist.index', ['status' => 'close']))
            ->assertSee('Closed MDR Project')
            ->assertDontSee('Open MDR Project');
    }

    public function test_listing_paginates_mdrs_in_pages_of_fifteen(): void
    {
        $qc = User::factory()->create(['role' => 'qc_qa']);

        for ($index = 0; $index < 16; $index++) {
            $project = Project::factory()->create([
                'project_name' => sprintf('Pagination Project %02d', $index),
            ]);

            Mdr::factory()->for($project)->create();
        }

        $this->actingAs($qc)
            ->get(route('mdr-checklist.index'))
            ->assertSee('Pagination Project 15')
            ->assertDontSee('Pagination Project 00')
            ->assertSee('page=2');

        $this->get(route('mdr-checklist.index', ['page' => 2]))
            ->assertSee('Pagination Project 00')
            ->assertDontSee('Pagination Project 15');
    }

    public function test_future_config_report_is_created_when_mdr_detail_is_opened(): void
    {
        $reports = config('mudalaya.mdr_reports');
        $futureKey = 'additional_test_report';
        $reports[$futureKey] = 'Additional Test Report';
        config(['mudalaya.mdr_reports' => $reports]);

        $qc = User::factory()->create(['role' => 'qc_qa']);
        $mdr = Mdr::factory()->create();
        $this->createDefaultStatuses($mdr);

        $this->actingAs($qc)
            ->get(route('mdr-checklist.show', ['mdr' => $mdr]))
            ->assertOk()
            ->assertSee('Additional Test Report');

        $this->assertDatabaseHas('mdr_report_statuses', [
            'mdr_id' => $mdr->id,
            'report_key' => $futureKey,
            'status' => MdrStatus::Open->value,
        ]);
    }

    public function test_listing_searches_by_project_name_and_unit_no(): void
    {
        $qc = User::factory()->create(['role' => 'qc_qa']);
        $projectAlpha = Project::factory()->create(['project_name' => 'Alpha Heavy Project']);
        $projectBeta = Project::factory()->create(['project_name' => 'Beta Vessel Project']);

        $mdr1 = Mdr::factory()->for($projectAlpha)->create(['unit_no' => 'Tank-99']);
        $this->createDefaultStatuses($mdr1);

        $mdr2 = Mdr::factory()->for($projectBeta)->create(['unit_no' => 'Boiler-01']);
        $this->createDefaultStatuses($mdr2);

        $this->actingAs($qc)
            ->get(route('mdr-checklist.index', ['search' => 'Alpha']))
            ->assertSee('Alpha Heavy Project')
            ->assertSee('Tank-99')
            ->assertDontSee('Beta Vessel Project');

        $this->get(route('mdr-checklist.index', ['search' => 'Boiler-01']))
            ->assertSee('Beta Vessel Project')
            ->assertSee('Boiler-01')
            ->assertDontSee('Alpha Heavy Project');
    }

    public function test_detail_page_displays_project_code_name_and_unit_no(): void
    {
        $qc = User::factory()->create(['role' => 'qc_qa']);
        $project = Project::factory()->create([
            'project_code' => 'PRJ-001',
            'project_name' => 'Tangki Solar',
        ]);
        $mdr = Mdr::factory()->for($project)->create(['unit_no' => 'Unit 1']);
        $this->createDefaultStatuses($mdr);

        $this->actingAs($qc)
            ->get(route('mdr-checklist.show', ['mdr' => $mdr]))
            ->assertOk()
            ->assertSee('PRJ-001 - Tangki Solar, Unit 1');
    }

    public function test_existing_mdr_in_database_gets_unit_one_by_default(): void
    {
        $project = Project::factory()->create();
        $user = User::factory()->create();

        $id = DB::table('mdrs')->insertGetId([
            'project_id' => $project->id,
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $mdr = Mdr::query()->findOrFail($id);
        $this->assertSame('Unit 1', $mdr->unit_no);
    }

    public function test_mdr_routes_are_restricted_to_configured_roles(): void
    {
        foreach (['production', 'warehouse', 'ppic'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)
                ->get(route('mdr-checklist.index'))
                ->assertForbidden();
        }

        foreach (['qc_qa', 'admin'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)
                ->get(route('mdr-checklist.index'))
                ->assertOk();
        }
    }

    public function test_mdr_totals_and_status_counts_aggregation(): void
    {
        $qc = User::factory()->create(['role' => 'qc_qa']);

        // 2 Open, 1 Close
        $p1 = Project::factory()->create(['project_name' => 'Solar Alpha']);
        $p2 = Project::factory()->create(['project_name' => 'Solar Beta']);
        $p3 = Project::factory()->create(['project_name' => 'Vessel Gamma']);

        $mdr1 = Mdr::factory()->for($p1)->create(['unit_no' => 'Unit-A']);
        $this->createDefaultStatuses($mdr1, 'open');

        $mdr2 = Mdr::factory()->for($p2)->create(['unit_no' => 'Unit-B']);
        $this->createDefaultStatuses($mdr2, 'open');

        $mdr3 = Mdr::factory()->for($p3)->create(['unit_no' => 'Unit-C']);
        $this->createDefaultStatuses($mdr3, 'close');

        // Total without filter
        $response = $this->actingAs($qc)->get(route('mdr-checklist.index'));
        $response->assertOk();

        $totals = $response->viewData('mdrTotals');
        $this->assertSame(3, $totals['total']);
        $this->assertSame(2, $totals['open']);
        $this->assertSame(1, $totals['close']);

        $counts = $response->viewData('statusCounts');
        $this->assertSame(3, $counts['all']);
        $this->assertSame(2, $counts['open']);
        $this->assertSame(1, $counts['close']);

        // Searching "Solar" matches p1 and p2 (both open)
        $searchResponse = $this->get(route('mdr-checklist.index', ['search' => 'Solar', 'status' => 'open']));
        $searchCounts = $searchResponse->viewData('statusCounts');
        $this->assertSame(2, $searchCounts['all']);
        $this->assertSame(2, $searchCounts['open']);
        $this->assertSame(0, $searchCounts['close']);

        // $mdrTotals remains global
        $searchTotals = $searchResponse->viewData('mdrTotals');
        $this->assertSame(3, $searchTotals['total']);
        $this->assertSame(2, $searchTotals['open']);
        $this->assertSame(1, $searchTotals['close']);
    }

    public function test_filter_search_and_pagination_preserve_query_strings(): void
    {
        $qc = User::factory()->create(['role' => 'qc_qa']);

        for ($i = 0; $i < 20; $i++) {
            $p = Project::factory()->create(['project_name' => 'FilterPreserve '.$i]);
            $m = Mdr::factory()->for($p)->create(['unit_no' => 'Unit-'.$i]);
            $this->createDefaultStatuses($m, 'open');
        }

        $res = $this->actingAs($qc)->get(route('mdr-checklist.index', [
            'status' => 'open',
            'search' => 'FilterPreserve',
        ]));

        $res->assertOk()
            ->assertSee('status=open')
            ->assertSee('search=FilterPreserve')
            ->assertSee('page=2');
    }

    public function test_empty_state_unfiltered_versus_filtered(): void
    {
        $qc = User::factory()->create(['role' => 'qc_qa']);

        // 1. Completely empty database
        $this->actingAs($qc)
            ->get(route('mdr-checklist.index'))
            ->assertOk()
            ->assertSee('Belum ada MDR.')
            ->assertDontSee('Tidak ada MDR yang cocok');

        // 2. Create one MDR
        $p = Project::factory()->create(['project_name' => 'Some Project']);
        $m = Mdr::factory()->for($p)->create(['unit_no' => 'U1']);
        $this->createDefaultStatuses($m, 'open');

        // Search with non-matching query
        $this->get(route('mdr-checklist.index', ['search' => 'NonExistentProject']))
            ->assertOk()
            ->assertSee('Tidak ada MDR yang cocok')
            ->assertSee('Reset filter')
            ->assertDontSee('Belum ada MDR.');

        // Filter with non-matching status
        $this->get(route('mdr-checklist.index', ['status' => 'close']))
            ->assertOk()
            ->assertSee('Tidak ada MDR yang cocok')
            ->assertSee('Reset filter')
            ->assertDontSee('Belum ada MDR.');
    }

    public function test_new_mdr_form_defaults_all_report_statuses_to_open_and_retains_data_customer(): void
    {
        $qc = User::factory()->create(['role' => 'qc_qa']);
        $project = Project::factory()->create([
            'project_name' => 'Alpha Vessel',
            'customer' => 'PT Shell Indonesia',
        ]);

        $response = $this->actingAs($qc)
            ->get(route('mdr-checklist.create'))
            ->assertOk();

        // data-customer attribute present
        $response->assertSee('data-customer="PT Shell Indonesia"', false);

        // All 16 reports default to open checked
        foreach (array_keys(config('mudalaya.mdr_reports')) as $reportKey) {
            $response->assertSee('name="statuses['.$reportKey.']" value="open" checked', false);
        }

        // Summary bar initially shows 0 completed
        $response->assertSee('0 dari 16 report selesai', false);
        $response->assertSee('id="mdr-summary-chip-open"', false);
    }

    public function test_new_mdr_form_renders_radios_selected_according_to_old_input(): void
    {
        $qc = User::factory()->create(['role' => 'qc_qa']);
        $project = Project::factory()->create(['customer' => 'Client XYZ']);

        $response = $this->actingAs($qc)
            ->withSession([
                '_old_input' => [
                    'project_id' => $project->id,
                    'unit_no' => 'Unit-Test',
                    'statuses' => [
                        'certificate_of_conformance' => 'close',
                        'wps' => 'na',
                        'welder_cert' => 'open',
                    ],
                ],
            ])
            ->get(route('mdr-checklist.create'))
            ->assertOk();

        // Check specific checked radios based on old input
        $response->assertSee('name="statuses[certificate_of_conformance]" value="close" checked', false);
        $response->assertSee('name="statuses[wps]" value="na" checked', false);
        $response->assertSee('name="statuses[welder_cert]" value="open" checked', false);

        // Summary bar reflects 2 completed reports from old input
        $response->assertSee('2 dari 16 report selesai', false);
    }

    public function test_show_detail_page_renders_redesigned_cards_and_radio_statuses_from_database(): void
    {
        $qc = User::factory()->create(['role' => 'qc_qa']);
        $project = Project::factory()->create([
            'project_code' => 'PRJ-888',
            'project_name' => 'Boiler Unit Alpha',
            'customer' => 'PT Pertamina',
        ]);
        $mdr = Mdr::factory()->for($project)->create(['unit_no' => 'Unit 1']);
        $this->createDefaultStatuses($mdr, 'open');

        // Set 4 reports to close/na
        $mdr->reportStatuses()->where('report_key', 'certificate_of_conformance')->update(['status' => 'close']);
        $mdr->reportStatuses()->where('report_key', 'certificate_of_manufacture')->update(['status' => 'close']);
        $mdr->reportStatuses()->where('report_key', 'design_drawing_approval')->update(['status' => 'close']);
        $mdr->reportStatuses()->where('report_key', 'wps')->update(['status' => 'na']);

        $response = $this->actingAs($qc)
            ->get(route('mdr-checklist.show', ['mdr' => $mdr]))
            ->assertOk();

        // Header and Project Card info
        $response->assertSee('PRJ-888 - Boiler Unit Alpha, Unit 1');
        $response->assertSee('PT Pertamina');

        // Status radios reflect DB
        $response->assertSee('name="statuses[certificate_of_conformance]" value="close" checked', false);
        $response->assertSee('name="statuses[wps]" value="na" checked', false);
        $response->assertSee('name="statuses[welder_cert]" value="open" checked', false);

        // Summary bar reflects 4 completed reports
        $response->assertSee('4 dari 16 report selesai', false);

        // Bulk buttons and submit button
        $response->assertSee('data-bulk="open"', false);
        $response->assertSee('Simpan perubahan');
    }

    public function test_mdr_create_and_show_with_serial_numbers_and_custom_reports(): void
    {
        $qc = User::factory()->create(['role' => 'qc_qa']);
        $project = Project::factory()->create(['project_name' => 'Boiler Vessel 500']);

        // 1. Create page shows Owner and Manufacture Serial No inputs, and doesn't show Nomor Unit input
        $createRes = $this->actingAs($qc)->get(route('mdr-checklist.create'));
        $createRes->assertOk()
            ->assertSee('Owner Serial No')
            ->assertSee('Manufaktur Serial No')
            ->assertSee('+ Tambah Report');

        // 2. Submit MDR with Serial Numbers and Custom Report
        $statuses = array_fill_keys(array_keys(config('mudalaya.mdr_reports')), 'open');
        $postRes = $this->actingAs($qc)->post(route('mdr-checklist.store'), [
            'project_id' => $project->id,
            'owner_serial_no' => 'OWNER-SN-888',
            'manufacture_serial_no' => 'MFG-SN-999',
            'statuses' => $statuses,
            'custom_reports' => [
                [
                    'name' => 'Custom Fatigue Analysis',
                    'status' => 'close',
                ],
            ],
        ]);
        $postRes->assertRedirect();

        $mdr = Mdr::where('owner_serial_no', 'OWNER-SN-888')->first();
        $this->assertNotNull($mdr);
        $this->assertSame('MFG-SN-999', $mdr->manufacture_serial_no);

        // Custom report saved in statuses
        $customStatus = $mdr->reportStatuses()->where('report_title', 'Custom Fatigue Analysis')->first();
        $this->assertNotNull($customStatus);
        $this->assertSame(MdrStatus::Close, $customStatus->status);

        // 3. Show page displays serial numbers and custom report
        $showRes = $this->actingAs($qc)->get(route('mdr-checklist.show', ['mdr' => $mdr]));
        $showRes->assertOk()
            ->assertSee('OWNER-SN-888')
            ->assertSee('MFG-SN-999')
            ->assertSee('Custom Fatigue Analysis');
    }

    public function test_qc_or_admin_can_delete_mdr_and_cascades_statuses(): void
    {
        $qc = User::factory()->create(['role' => 'qc_qa']);
        $project = Project::factory()->create(['project_name' => 'Boiler Test']);
        $mdr = Mdr::factory()->for($project)->create(['owner_serial_no' => 'OSN-DEL-01']);
        $this->createDefaultStatuses($mdr);

        $this->assertDatabaseHas('mdrs', ['id' => $mdr->id]);
        $this->assertDatabaseCount('mdr_report_statuses', 16);

        $response = $this->actingAs($qc)->delete(route('mdr-checklist.destroy', ['mdr' => $mdr]));
        $response->assertRedirect(route('mdr-checklist.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('mdrs', ['id' => $mdr->id]);
        $this->assertDatabaseCount('mdr_report_statuses', 0);
    }

    public function test_admin_and_qc_qa_can_export_mdr_list_and_detail(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $qc = User::factory()->create(['role' => 'qc_qa']);
        $project = Project::factory()->create([
            'project_code' => 'PRJ-EXP',
            'project_name' => 'Export Project',
            'customer' => 'Customer Indo',
        ]);
        $mdr = Mdr::factory()->for($project)->create(['unit_no' => 'Unit 101']);
        $this->createDefaultStatuses($mdr);

        $date = now()->format('Ymd');
        $expectedListFile = "mdr-{$date}.xlsx";
        $expectedDetailFile = "mdr-prj-exp-unit-101-{$date}.xlsx";

        // Admin export list
        Excel::fake();
        $this->actingAs($admin)
            ->get(route('mdr-checklist.export'))
            ->assertOk();
        Excel::assertDownloaded($expectedListFile, function (MdrListExport $export): bool {
            return true;
        });

        // Admin export detail
        Excel::fake();
        $this->actingAs($admin)
            ->get(route('mdr-checklist.export-show', ['mdr' => $mdr]))
            ->assertOk();
        Excel::assertDownloaded($expectedDetailFile, function (MdrDetailExport $export): bool {
            return true;
        });

        // QC/QA export list
        Excel::fake();
        $this->actingAs($qc)
            ->get(route('mdr-checklist.export'))
            ->assertOk();
        Excel::assertDownloaded($expectedListFile, function (MdrListExport $export): bool {
            return true;
        });

        // QC/QA export detail
        Excel::fake();
        $this->actingAs($qc)
            ->get(route('mdr-checklist.export-show', ['mdr' => $mdr]))
            ->assertOk();
        Excel::assertDownloaded($expectedDetailFile, function (MdrDetailExport $export): bool {
            return true;
        });
    }

    public function test_other_roles_cannot_export_mdr_list_or_detail(): void
    {
        $project = Project::factory()->create();
        $mdr = Mdr::factory()->for($project)->create(['unit_no' => 'Unit X']);
        $this->createDefaultStatuses($mdr);

        $forbiddenRoles = ['production', 'warehouse', 'ppic'];

        foreach ($forbiddenRoles as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)
                ->get(route('mdr-checklist.export'))
                ->assertForbidden();

            $this->actingAs($user)
                ->get(route('mdr-checklist.export-show', ['mdr' => $mdr]))
                ->assertForbidden();
        }
    }

    public function test_mdr_list_export_respects_status_and_search_filters(): void
    {
        $qc = User::factory()->create(['role' => 'qc_qa']);
        $projectA = Project::factory()->create([
            'project_code' => 'PRJ-OPEN',
            'project_name' => 'Open Project',
        ]);
        $projectB = Project::factory()->create([
            'project_code' => 'PRJ-CLOSE',
            'project_name' => 'Close Project',
        ]);

        $openMdr = Mdr::factory()->for($projectA)->create(['unit_no' => 'UNIT-OPEN-1']);
        $this->createDefaultStatuses($openMdr, 'open');

        $closeMdr = Mdr::factory()->for($projectB)->create(['unit_no' => 'UNIT-CLOSE-1']);
        $this->createDefaultStatuses($closeMdr, 'close');

        // Filter status=close
        Excel::fake();
        $this->actingAs($qc)
            ->get(route('mdr-checklist.export', ['status' => 'close']))
            ->assertOk();
        Excel::assertDownloaded('mdr-'.now()->format('Ymd').'.xlsx', function (MdrListExport $export): bool {
            $collection = $export->collection();
            $this->assertCount(1, $collection);
            $this->assertSame('PRJ-CLOSE', $collection->first()[1]);
            $this->assertSame('16 dari 16', $collection->first()[7]);
            $this->assertSame('Close', $collection->first()[8]);

            return true;
        });

        // Filter status=open
        Excel::fake();
        $this->actingAs($qc)
            ->get(route('mdr-checklist.export', ['status' => 'open']))
            ->assertOk();
        Excel::assertDownloaded('mdr-'.now()->format('Ymd').'.xlsx', function (MdrListExport $export): bool {
            $collection = $export->collection();
            $this->assertCount(1, $collection);
            $this->assertSame('PRJ-OPEN', $collection->first()[1]);
            $this->assertSame('0 dari 16', $collection->first()[7]);
            $this->assertSame('Open', $collection->first()[8]);

            return true;
        });

        // Filter search
        Excel::fake();
        $this->actingAs($qc)
            ->get(route('mdr-checklist.export', ['search' => 'UNIT-CLOSE-1']))
            ->assertOk();
        Excel::assertDownloaded('mdr-'.now()->format('Ymd').'.xlsx', function (MdrListExport $export): bool {
            $collection = $export->collection();
            $this->assertCount(1, $collection);
            $this->assertSame('UNIT-CLOSE-1', $collection->first()[4]);

            return true;
        });
    }

    public function test_mdr_detail_export_includes_custom_reports_and_has_no_side_effects(): void
    {
        $qc = User::factory()->create(['role' => 'qc_qa']);
        $project = Project::factory()->create([
            'project_code' => 'PRJ-CUSTOM',
            'project_name' => 'Vessel Unit Alpha',
            'customer' => 'Customer Pertamina',
        ]);
        $mdr = Mdr::factory()->for($project)->create([
            'unit_no' => 'UNIT-CST-99',
            'owner_serial_no' => 'OSN-999',
            'manufacture_serial_no' => 'MFG-999',
        ]);

        // Do not create default 16 standard reports in database to test "baris belum ada = Open" and no side-effects
        $mdr->reportStatuses()->create([
            'report_key' => 'custom_1_additional_ndt',
            'report_title' => 'Additional NDT Inspection',
            'status' => MdrStatus::Close->value,
        ]);

        $this->assertDatabaseCount('mdr_report_statuses', 1);

        Excel::fake();
        $expectedFileName = 'mdr-prj-custom-unit-cst-99-'.now()->format('Ymd').'.xlsx';

        $this->actingAs($qc)
            ->get(route('mdr-checklist.export-show', ['mdr' => $mdr]))
            ->assertOk();

        // Verify no side-effects in database (firstOrCreate was NOT called)
        $this->assertDatabaseCount('mdr_report_statuses', 1);

        Excel::assertDownloaded($expectedFileName, function (MdrDetailExport $export): bool {
            $data = $export->array();

            // Info block (first 7 rows)
            $this->assertSame(['Project Code - Name', 'PRJ-CUSTOM - Vessel Unit Alpha'], $data[0]);
            $this->assertSame(['Customer', 'Customer Pertamina'], $data[1]);
            $this->assertSame(['Unit No', 'UNIT-CST-99'], $data[2]);
            $this->assertSame(['Owner Serial No', 'OSN-999'], $data[3]);
            $this->assertSame(['Manufacture Serial No', 'MFG-999'], $data[4]);
            $this->assertSame(['Status', 'Open'], $data[5]);
            $this->assertSame(['Progres', '1 dari 17'], $data[6]);
            $this->assertSame([], $data[7]);
            $this->assertSame(['No', 'Report', 'Status'], $data[8]);

            // Standard reports: 16 items (rows index 9 to 24)
            for ($i = 9; $i <= 24; $i++) {
                $this->assertSame('Open', $data[$i][2]);
            }

            // Custom report: 17th item (row index 25)
            $this->assertSame([17, 'Additional NDT Inspection', 'Close'], $data[25]);

            return true;
        });
    }

    private function createDefaultStatuses(Mdr $mdr, string $status = 'open'): void
    {
        foreach (array_keys(config('mudalaya.mdr_reports')) as $reportKey) {
            MdrReportStatus::factory()->for($mdr)->create([
                'report_key' => $reportKey,
                'status' => $status,
            ]);
        }
    }
}
