<?php

namespace Tests\Feature;

use App\Models\Mdr;
use App\Models\MdrReportStatus;
use App\Models\QcFinding;
use App\Models\Rfi;
use App\Models\User;
use App\Services\DashboardCharts;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class DashboardChartsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateTestDatabase();
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    private function freezeAt(string $jakartaDatetime): void
    {
        $frozen = CarbonImmutable::parse($jakartaDatetime, 'Asia/Jakarta');
        Carbon::setTestNow($frozen);
        CarbonImmutable::setTestNow($frozen);
    }

    private function makeRfi(string $category, string $status, string $submittedAtUtc): Rfi
    {
        return Rfi::factory()->create([
            'category' => $category,
            'status' => $status,
            'submitted_at' => $submittedAtUtc,
        ]);
    }

    private function makeFinding(string $category, string $dateOfFinding, ?string $dateOfClose = null): QcFinding
    {
        $rfi = Rfi::factory()->create(['category' => $category]);

        return QcFinding::factory()->create([
            'rfi_id' => $rfi->id,
            'date_of_finding' => $dateOfFinding,
            'date_of_close' => $dateOfClose,
            'status' => $dateOfClose !== null ? 'closed' : 'open',
        ]);
    }

    private function charts(): DashboardCharts
    {
        return app(DashboardCharts::class);
    }

    // -------------------------------------------------------------------------
    // 1. Label bulan dan urutan benar (6 bulan terakhir termasuk bulan ini)
    // -------------------------------------------------------------------------

    public function test_rfi_chart_has_six_month_labels_ending_this_month(): void
    {
        $this->freezeAt('2026-10-15 10:00:00');

        $result = $this->charts()->rfiByMonth(['A', 'B', 'C', 'D', 'E']);

        $this->assertCount(6, $result['months']);
        $this->assertStringContainsString('Mei', $result['months'][0]);
        $this->assertStringContainsString('Okt', $result['months'][5]);
    }

    // -------------------------------------------------------------------------
    // 2. Pengelompokan memakai zona waktu Asia/Jakarta
    //    Data jam 23:30 UTC pada 2026-09-30 = 06:30 WIB 2026-10-01 → Oktober
    // -------------------------------------------------------------------------

    public function test_rfi_submitted_at_23_30_utc_end_of_september_counts_in_october_jakarta(): void
    {
        $this->freezeAt('2026-10-15 10:00:00');

        // 2026-09-30 23:30 UTC => 2026-10-01 06:30 WIB
        $this->makeRfi('A', 'open', '2026-09-30 23:30:00');

        $result = $this->charts()->rfiByMonth(['A']);

        // Month index 5 = Oktober 2026, index 4 = September 2026
        $openByMonth = $result['series']['Open'];
        $this->assertSame(1, $openByMonth[5], 'Harus masuk Oktober (Jakarta)');
        $this->assertSame(0, $openByMonth[4], 'Tidak boleh masuk September');
    }

    // -------------------------------------------------------------------------
    // 3. Seri RFI: Open, NC, Close terhitung dengan benar
    // -------------------------------------------------------------------------

    public function test_rfi_series_counts_open_nc_close_correctly(): void
    {
        $this->freezeAt('2026-10-15 10:00:00');

        // Oktober WIB: semua masuk bulan ini
        $this->makeRfi('B', 'open', '2026-10-01 00:00:00');
        $this->makeRfi('B', 'open', '2026-10-02 00:00:00');
        $this->makeRfi('B', 'nc', '2026-10-03 00:00:00');
        $this->makeRfi('B', 'close', '2026-10-04 00:00:00');

        $result = $this->charts()->rfiByMonth(['B']);

        $this->assertSame(2, $result['series']['Open'][5]);
        $this->assertSame(1, $result['series']['NC'][5]);
        $this->assertSame(1, $result['series']['Close'][5]);
    }

    // -------------------------------------------------------------------------
    // 4. Penyaringan kategori per role: warehouse hanya melihat kategori A
    // -------------------------------------------------------------------------

    public function test_warehouse_only_sees_category_a_rfi(): void
    {
        $this->freezeAt('2026-10-15 10:00:00');

        $warehouseCategories = collect(config('mudalaya.rfi_categories'))
            ->filter(fn ($cat) => in_array('warehouse', $cat['roles'], true))
            ->keys()
            ->all();

        // Kategori A (warehouse bisa lihat) + Kategori B (tidak boleh)
        $this->makeRfi('A', 'open', '2026-10-01 00:00:00');
        $this->makeRfi('B', 'open', '2026-10-01 00:00:00');

        $result = $this->charts()->rfiByMonth($warehouseCategories);

        $totalOpen = array_sum($result['series']['Open']);
        $this->assertSame(1, $totalOpen, 'Warehouse hanya melihat kategori A');
    }

    // -------------------------------------------------------------------------
    // 5. Finding created vs closed
    // -------------------------------------------------------------------------

    public function test_finding_created_vs_closed_chart(): void
    {
        $this->freezeAt('2026-10-15 10:00:00');

        $this->makeFinding('A', '2026-10-01', '2026-10-05');
        $this->makeFinding('A', '2026-10-02', null);
        $this->makeFinding('A', '2026-09-15', null);

        $result = $this->charts()->findingCreatedVsClosed(['A']);

        // Sep = index 4, Okt = index 5
        $this->assertSame(1, $result['series']['Dibuat'][4]);
        $this->assertSame(2, $result['series']['Dibuat'][5]);
        $this->assertSame(0, $result['series']['Ditutup'][4]);
        $this->assertSame(1, $result['series']['Ditutup'][5]);
    }

    // -------------------------------------------------------------------------
    // 6. Finding status by month
    // -------------------------------------------------------------------------

    public function test_finding_status_by_month_closed_vs_open(): void
    {
        $this->freezeAt('2026-10-15 10:00:00');

        $this->makeFinding('B', '2026-10-01', '2026-10-10');  // closed
        $this->makeFinding('B', '2026-10-02', null);           // open

        $result = $this->charts()->findingStatusByMonth(['B']);

        $this->assertSame(1, $result['series']['Closed'][5]);
        $this->assertSame(1, $result['series']['Belum Closed'][5]);
    }

    // -------------------------------------------------------------------------
    // 7. MDR by month - tidak muncul untuk role production
    // -------------------------------------------------------------------------

    public function test_mdr_chart_not_visible_for_production_role(): void
    {
        $user = User::factory()->create(['role' => 'production']);

        $this->actingAs($user)
            ->get(route('mdr-checklist.index'))
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // 8. Data MDR per bulan
    // -------------------------------------------------------------------------

    public function test_mdr_by_month_open_and_close(): void
    {
        $this->freezeAt('2026-10-15 10:00:00');

        $reportKeys = array_keys(config('mudalaya.mdr_reports'));

        // MDR Open: semua laporan masih open
        $openMdr = Mdr::factory()->create(['created_at' => '2026-10-01 00:00:00']);
        foreach ($reportKeys as $key) {
            MdrReportStatus::factory()->for($openMdr)->create([
                'report_key' => $key,
                'status' => 'open',
            ]);
        }

        // MDR Close: semua laporan sudah close
        $closedMdr = Mdr::factory()->create(['created_at' => '2026-10-02 00:00:00']);
        foreach ($reportKeys as $key) {
            MdrReportStatus::factory()->for($closedMdr)->create([
                'report_key' => $key,
                'status' => 'close',
            ]);
        }

        $result = $this->charts()->mdrByMonth();

        $this->assertSame(1, $result['series']['Open'][5]);
        $this->assertSame(1, $result['series']['Close'][5]);
    }

    // -------------------------------------------------------------------------
    // 9. Tampilan kosong: teks "Belum ada data" saat semua nilai nol
    // -------------------------------------------------------------------------

    public function test_empty_state_shows_belum_ada_data(): void
    {
        $this->freezeAt('2026-10-15 10:00:00');

        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Belum ada data');
    }

    // -------------------------------------------------------------------------
    // 10. Halaman dashboard render tanpa error untuk semua role yang berhak
    // -------------------------------------------------------------------------

    public function test_dashboard_renders_for_all_allowed_roles(): void
    {
        $roles = ['admin', 'production', 'qc_qa', 'warehouse', 'ppic'];

        foreach ($roles as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)
                ->get(route('dashboard'))
                ->assertOk();
        }
    }

    // -------------------------------------------------------------------------
    // 11. /qc-finding render tanpa error untuk semua role yang berhak
    // -------------------------------------------------------------------------

    public function test_qc_finding_index_renders_for_allowed_roles(): void
    {
        $roles = config('mudalaya.modules.qc_finding.roles', []);

        foreach ($roles as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)
                ->get(route('qc-finding.index'))
                ->assertOk();
        }
    }

    // -------------------------------------------------------------------------
    // 12. /mdr-checklist render tanpa error untuk semua role yang berhak
    // -------------------------------------------------------------------------

    public function test_mdr_checklist_index_renders_for_allowed_roles(): void
    {
        $roles = config('mudalaya.modules.mdr.roles', []);

        foreach ($roles as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)
                ->get(route('mdr-checklist.index'))
                ->assertOk();
        }
    }

    // -------------------------------------------------------------------------
    // 13. Tidak ada tag <script src="..."> dari domain luar
    // -------------------------------------------------------------------------

    public function test_dashboard_has_no_external_script_tags(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();

        // Pastikan tidak ada <script src="http..."> dari CDN luar
        $this->assertMatchesRegularExpression(
            '/^(?!.*<script[^>]+src=["\']https?:\/\/).*$/s',
            $response->getContent(),
            'Halaman dashboard tidak boleh memuat script dari sumber luar'
        );
    }

    // -------------------------------------------------------------------------
    // 14. Data hanya untuk 6 bulan terakhir (data lebih tua diabaikan)
    // -------------------------------------------------------------------------

    public function test_data_older_than_6_months_is_excluded(): void
    {
        $this->freezeAt('2026-10-15 10:00:00');

        // Data 7 bulan lalu (April 2026 WIB), harus diabaikan
        $this->makeRfi('A', 'open', '2026-03-31 17:00:00'); // WIB = 2026-04-01 00:00 → April (5 bulan = threshold)

        // Lebih jauh lagi: Februari 2026, harus pasti diabaikan
        $this->makeRfi('A', 'open', '2026-01-15 00:00:00');

        $result = $this->charts()->rfiByMonth(['A']);

        $totalOpen = array_sum($result['series']['Open']);
        $this->assertSame(0, $totalOpen);
    }

    // -------------------------------------------------------------------------
    // 15. BUG 1: Temuan dibuat 8 bulan lalu dan ditutup bulan ini
    // -------------------------------------------------------------------------

    public function test_finding_created_eight_months_ago_and_closed_this_month_is_counted_in_closed_only(): void
    {
        $this->freezeAt('2026-10-15 10:00:00');

        // 8 bulan lalu dari Oktober 2026 = Februari 2026
        $this->makeFinding('A', '2026-02-10', '2026-10-05');

        $result = $this->charts()->findingCreatedVsClosed(['A']);

        // Seri Dibuat di semua 6 bulan (Mei - Okt) harus 0
        $this->assertSame(0, array_sum($result['series']['Dibuat']), 'Temuan lama tidak boleh terhitung di Dibuat mana pun');

        // Seri Ditutup di bulan ini (Okt, index 5) harus 1
        $this->assertSame(1, $result['series']['Ditutup'][5], 'Harus terhitung di Ditutup bulan ini');
        $this->assertSame(1, array_sum($result['series']['Ditutup']));
    }

    // -------------------------------------------------------------------------
    // 16. BUG 2: date_of_finding memakai zona waktu Asia/Jakarta
    // -------------------------------------------------------------------------

    public function test_rfi_non_ok_review_uses_jakarta_timezone_for_date_of_finding(): void
    {
        // 2026-10-31 18:00:00 UTC = 2026-11-01 01:00:00 WIB
        $frozenUtc = CarbonImmutable::parse('2026-10-31 18:00:00', 'UTC');
        Carbon::setTestNow($frozenUtc);
        CarbonImmutable::setTestNow($frozenUtc);

        $qc = User::factory()->create(['role' => 'qc_qa']);
        $rfi = Rfi::factory()->create([
            'category' => 'B',
            'status' => 'open',
            'result' => null,
            'qc_note' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);

        $this->actingAs($qc)
            ->post(route('rfi.review', ['kategori' => 'B', 'rfi' => $rfi]), [
                'result' => 'repair',
                'qc_note' => 'Perlu perbaikan pengelasan.',
            ])
            ->assertRedirect(route('rfi.category', ['kategori' => 'B']));

        $finding = QcFinding::query()->where('rfi_id', $rfi->id)->firstOrFail();
        $this->assertSame('2026-11-01', $finding->date_of_finding->toDateString());
    }

    // -------------------------------------------------------------------------
    // 17. BUG 2: date_of_close saat verifikasi submission memakai zona waktu Asia/Jakarta
    // -------------------------------------------------------------------------

    public function test_submission_verification_ok_uses_jakarta_timezone_for_date_of_close(): void
    {
        // 2026-10-31 18:00:00 UTC = 2026-11-01 01:00:00 WIB
        $frozenUtc = CarbonImmutable::parse('2026-10-31 18:00:00', 'UTC');
        Carbon::setTestNow($frozenUtc);
        CarbonImmutable::setTestNow($frozenUtc);

        $qc = User::factory()->create(['role' => 'qc_qa']);
        $finding = $this->makeFinding('B', '2026-10-15');
        $production = User::factory()->create(['role' => 'production']);

        $finding->submissions()->create([
            'round_no' => 1,
            'note' => 'Sudah diperbaiki',
            'submitted_by' => $production->id,
            'submitted_at' => Carbon::now('UTC'),
        ]);
        $finding->update(['status' => 'waiting_verification']);

        $this->actingAs($qc)
            ->post(route('qc-finding.verify', ['finding' => $finding]), [
                'result' => 'ok',
            ])
            ->assertRedirect(route('qc-finding.project', ['project' => $finding->rfi->project_id]));

        $finding->refresh();
        $this->assertSame('closed', $finding->status);
        $this->assertSame('2026-11-01', $finding->date_of_close->toDateString());
    }

    // -------------------------------------------------------------------------
    // 18. BUG 3: buildMonths() tidak overflow di tanggal 29-31
    // -------------------------------------------------------------------------

    public function test_build_months_does_not_overflow_on_day_31(): void
    {
        // 31 Maret (Maret -> Februari overflow risk jika subMonths sebelum startOfMonth)
        $this->freezeAt('2026-03-31 10:00:00');
        $chartMarch = $this->charts()->rfiByMonth(['A']);
        $monthsMarch = $chartMarch['months'];

        $this->assertCount(6, $monthsMarch);
        $this->assertSame(array_values(array_unique($monthsMarch)), $monthsMarch, 'Label 6 bulan pada 31 Maret harus unik dan berurutan');
        $this->assertStringContainsString('Okt 2025', $monthsMarch[0]);
        $this->assertStringContainsString('Nov 2025', $monthsMarch[1]);
        $this->assertStringContainsString('Des 2025', $monthsMarch[2]);
        $this->assertStringContainsString('Jan 2026', $monthsMarch[3]);
        $this->assertStringContainsString('Feb 2026', $monthsMarch[4]);
        $this->assertStringContainsString('Mar 2026', $monthsMarch[5]);

        // 31 Oktober
        $this->freezeAt('2026-10-31 10:00:00');
        $chartOct = $this->charts()->rfiByMonth(['A']);
        $monthsOct = $chartOct['months'];

        $this->assertCount(6, $monthsOct);
        $this->assertSame(array_values(array_unique($monthsOct)), $monthsOct, 'Label 6 bulan pada 31 Oktober harus unik dan berurutan');
        $this->assertStringContainsString('Mei 2026', $monthsOct[0]);
        $this->assertStringContainsString('Jun 2026', $monthsOct[1]);
        $this->assertStringContainsString('Jul 2026', $monthsOct[2]);
        $this->assertStringContainsString('Agt 2026', $monthsOct[3]);
        $this->assertStringContainsString('Sep 2026', $monthsOct[4]);
        $this->assertStringContainsString('Okt 2026', $monthsOct[5]);
    }
}
