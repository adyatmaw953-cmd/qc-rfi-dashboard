<?php

namespace Tests\Feature;

use App\Models\Ncr;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class DashboardNcrRedesignTest extends TestCase
{
    use RefreshDatabase;

    public function test_donut_chart_renders_total_zero_with_gray_ring(): void
    {
        $html = Blade::render('<x-donut-chart :items="[]" :total="0" center-label="NCR" />');

        $this->assertStringContainsString('0', $html);
        $this->assertStringContainsString('NCR', $html);
        $this->assertStringContainsString('stroke="#EEEBE1"', $html);
        $this->assertStringContainsString('role="img"', $html);
    }

    public function test_donut_and_legend_render_one_item_with_zero_and_correct_percentages(): void
    {
        // 28 Project and 16 Internal = 44 total (28/44 = 63.636...% -> "63,6%")
        $items = [
            ['label' => 'Project', 'value' => 28, 'color' => '#366F3A'],
            ['label' => 'Internal', 'value' => 16, 'color' => '#D9CBA2'],
            ['label' => 'Zero Item', 'value' => 0, 'color' => '#CCCCCC'],
        ];

        $donutHtml = Blade::render('<x-donut-chart :items="$items" :total="44" center-label="NCR" />', ['items' => $items]);
        $this->assertStringContainsString('44', $donutHtml);
        $this->assertStringContainsString('stroke="#366F3A"', $donutHtml);
        $this->assertStringContainsString('stroke="#D9CBA2"', $donutHtml);

        $legendHtml = Blade::render('<x-legend-list :items="$items" :decimals="1" shape="dot" :total="44" />', ['items' => $items]);
        $this->assertStringContainsString('28 &middot; 63,6%', $legendHtml);
        $this->assertStringContainsString('16 &middot; 36,4%', $legendHtml);
        $this->assertStringContainsString('0 &middot; 0,0%', $legendHtml);
    }

    public function test_stacked_bar_renders_with_zero_and_positive_segments(): void
    {
        $statusItems = [
            ['label' => 'Open', 'value' => 10, 'color' => '#8DB6E8'],
            ['label' => 'CA Closed', 'value' => 0, 'color' => '#E07B2E'],
            ['label' => 'Closed', 'value' => 30, 'color' => '#1F5B2A'],
        ];

        $html = Blade::render('<x-stacked-bar :items="$items" />', ['items' => $statusItems]);
        $this->assertStringContainsString('role="img"', $html);
        $this->assertStringContainsString('background-color: #8DB6E8', $html);
        $this->assertStringContainsString('background-color: #1F5B2A', $html);
    }

    public function test_metric_bars_renders_4m_percentage_with_two_decimals(): void
    {
        // 20 of 44 = 45.4545...% -> "45,45%"
        $rows = [
            ['label' => 'Man (Manusia)', 'value' => 20],
            ['label' => 'Method (Metode)', 'value' => 14],
            ['label' => 'Machine (Mesin)', 'value' => 10],
            ['label' => 'Material (Bahan)', 'value' => 0],
        ];

        $html = Blade::render('<x-metric-bars :rows="$rows" :total="44" />', ['rows' => $rows]);
        $this->assertStringContainsString('20 kasus &middot; 45,45%', $html);
        $this->assertStringContainsString('0 kasus &middot; 0,00%', $html);
        $this->assertStringContainsString('bg-[#366F3A]', $html);
    }

    public function test_rank_list_renders_rank_badges_and_expandable_details(): void
    {
        $rows = [
            ['label' => 'Produksi', 'value' => 25],
            ['label' => 'Engineering', 'value' => 18],
            ['label' => 'Fabrikasi', 'value' => 12],
            ['label' => 'Pengadaan', 'value' => 8],
            ['label' => 'Warehouse', 'value' => 4],
            ['label' => 'Delivery', 'value' => 2],
        ];

        $html = Blade::render('<x-rank-list :rows="$rows" :limit="5" unit="temuan" />', ['rows' => $rows]);
        $this->assertStringContainsString('25 temuan', $html);
        $this->assertStringContainsString('bg-[#366F3A] text-white', $html); // Rank 1 badge
        $this->assertStringContainsString('bg-[#E9EFE5] text-[#366F3A]', $html); // Other ranks
        $this->assertStringContainsString('Tampilkan semua (6)', $html);
        $this->assertStringContainsString('Delivery', $html);
    }

    public function test_dashboard_ncr_tab_displays_redesigned_sections(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $project = Project::factory()->create(['project_code' => 'PRJ-TEST-01']);

        Ncr::factory()->create([
            'created_by' => $user->id,
            'type' => 'Project',
            'project_code' => $project->project_code,
            'status' => 'Open',
            'level' => 'Major',
            'root_cause_category' => ['Man'],
            'area_finding' => 'Process Produksi',
            'finding_date' => date('Y-m-d'),
        ]);

        $response = $this->actingAs($user)->get(route('dashboard', ['tab' => 'ncr']));

        $response->assertOk()
            ->assertSee('Karakteristik data')
            ->assertSee('Distribusi Tipe NCR')
            ->assertSee('Project dibanding Internal')
            ->assertSee('Distribusi Status Mutu')
            ->assertSee('Status penyelesaian saat ini')
            ->assertSee('Analisis akar masalah')
            ->assertSee('Kategori Akar Masalah (4M)')
            ->assertSee('Temuan per Area Proses')
            ->assertSee('Temuan NCR per Department');
    }

    public function test_dashboard_ncr_displays_empty_message_when_year_has_no_ncrs(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        // Request a year with no NCR records (e.g., 2019)
        $response = $this->actingAs($user)->get(route('dashboard', ['tab' => 'ncr', 'ncr_year' => 2019]));

        $response->assertOk()
            ->assertSee('Belum ada data NCR pada tahun 2019.');
    }
}
