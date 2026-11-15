<?php

namespace Tests\Feature;

use Tests\TestCase;

class ChartComponentsTest extends TestCase
{
    public function test_bar_chart_renders_title_with_correct_values(): void
    {
        // Grouped
        $groupedHtml = (string) $this->blade(
            '<x-bar-chart :months="[\'Mei\', \'Jun\']" :series="[\'Closed\' => [5, 10], \'Belum Closed\' => [2, 4]]" layout="grouped" />'
        );
        $this->assertStringContainsString('chart-animate', $groupedHtml);
        $this->assertStringContainsString('chart-bar-vertical', $groupedHtml);
        $this->assertStringContainsString('<title>Mei: Closed: 5</title>', $groupedHtml);
        $this->assertStringContainsString('<title>Jun: Closed: 10</title>', $groupedHtml);
        $this->assertStringContainsString('<title>Mei: Belum Closed: 2</title>', $groupedHtml);
        $this->assertStringContainsString('<title>Jun: Belum Closed: 4</title>', $groupedHtml);

        // Stacked
        $stackedHtml = (string) $this->blade(
            '<x-bar-chart :months="[\'Mei\', \'Jun\']" :series="[\'NCR\' => [3, 7]]" layout="stacked" />'
        );
        $this->assertStringContainsString('chart-animate', $stackedHtml);
        $this->assertStringContainsString('chart-bar-vertical', $stackedHtml);
        $this->assertStringContainsString('<title>Mei: NCR: 3</title>', $stackedHtml);
        $this->assertStringContainsString('<title>Jun: NCR: 7</title>', $stackedHtml);
    }

    public function test_line_chart_renders_pathlength_and_dots_with_title(): void
    {
        $html = (string) $this->blade(
            '<x-line-chart :months="[\'Mei\', \'Jun\']" :series="[\'Dibuat\' => [8, 12]]" />'
        );

        $this->assertStringContainsString('chart-animate', $html);
        $this->assertStringContainsString('pathLength="1"', $html);
        $this->assertStringContainsString('chart-line-path', $html);
        $this->assertStringContainsString('chart-line-dot', $html);
        $this->assertStringContainsString('<title>Mei: Dibuat: 8</title>', $html);
        $this->assertStringContainsString('<title>Jun: Dibuat: 12</title>', $html);
    }

    public function test_donut_chart_renders_segments_with_title(): void
    {
        $items = [
            ['label' => 'Project', 'value' => 6, 'color' => '#1F5B2A'],
            ['label' => 'Internal', 'value' => 4, 'color' => '#E07B2E'],
        ];

        $html = (string) $this->blade('<x-donut-chart :items="$items" :total="10" />', ['items' => $items]);

        $this->assertStringContainsString('chart-animate', $html);
        $this->assertStringContainsString('chart-donut-segment', $html);
        $this->assertStringContainsString('chart-donut-center', $html);
        $this->assertStringContainsString('<title>Project: 6</title>', $html);
        $this->assertStringContainsString('<title>Internal: 4</title>', $html);
    }

    public function test_horizontal_bar_components_render_title_with_correct_values(): void
    {
        // 1. stacked-bar
        $stackedItems = [
            ['label' => 'Major', 'value' => 5, 'color' => '#E07B2E'],
            ['label' => 'Minor', 'value' => 3, 'color' => '#1F5B2A'],
        ];
        $stackedHtml = (string) $this->blade('<x-stacked-bar :items="$items" />', ['items' => $stackedItems]);
        $this->assertStringContainsString('chart-animate', $stackedHtml);
        $this->assertStringContainsString('chart-bar-horizontal', $stackedHtml);
        $this->assertStringContainsString('title="Major: 5"', $stackedHtml);
        $this->assertStringContainsString('title="Minor: 3"', $stackedHtml);

        // 2. progress-stack
        $progressHtml = (string) $this->blade('<x-progress-stack :open="4" :verifikasi="2" :closed="6" />');
        $this->assertStringContainsString('chart-animate', $progressHtml);
        $this->assertStringContainsString('chart-bar-horizontal', $progressHtml);
        $this->assertStringContainsString('title="Open: 4"', $progressHtml);
        $this->assertStringContainsString('title="Menunggu verifikasi: 2"', $progressHtml);
        $this->assertStringContainsString('title="Closed: 6"', $progressHtml);

        // 3. metric-bars
        $metricRows = [
            ['label' => 'Material', 'value' => 7],
            ['label' => 'Metode', 'value' => 3],
        ];
        $metricHtml = (string) $this->blade('<x-metric-bars :rows="$rows" :total="10" />', ['rows' => $metricRows]);
        $this->assertStringContainsString('chart-animate', $metricHtml);
        $this->assertStringContainsString('chart-bar-horizontal', $metricHtml);
        $this->assertStringContainsString('title="Material: 7"', $metricHtml);
        $this->assertStringContainsString('title="Metode: 3"', $metricHtml);

        // 4. rank-list
        $rankRows = [
            ['label' => 'Fabrikasi', 'value' => 8],
            ['label' => 'Pengelasan', 'value' => 5],
        ];
        $rankHtml = (string) $this->blade('<x-rank-list :rows="$rows" unit="kasus" />', ['rows' => $rankRows]);
        $this->assertStringContainsString('chart-animate', $rankHtml);
        $this->assertStringContainsString('chart-bar-horizontal', $rankHtml);
        $this->assertStringContainsString('title="Fabrikasi: 8 kasus"', $rankHtml);
        $this->assertStringContainsString('title="Pengelasan: 5 kasus"', $rankHtml);
    }

    public function test_count_up_elements_render_data_count_to_and_final_text(): void
    {
        // 1. Action card
        $actionHtml = (string) $this->blade('<x-action-card title="RFI menunggu review" :count="5" />');
        $this->assertStringContainsString('data-count-to="5"', $actionHtml);
        $this->assertStringContainsString('tabular-nums', $actionHtml);
        $this->assertStringContainsString('5', $actionHtml);

        // 2. Stat card
        $statHtml = (string) $this->blade('<x-stat-card label="Temuan Open" :value="12" status="open" />');
        $this->assertStringContainsString('data-count-to="12"', $statHtml);
        $this->assertStringContainsString('tabular-nums', $statHtml);
        $this->assertStringContainsString('12', $statHtml);

        // 3. Module summary card
        $summaryHtml = (string) $this->blade(
            '<x-module-summary-card title="QC Finding" :stats="[[\'label\' => \'Belum closed\', \'value\' => 7], [\'label\' => \'NC Cost\', \'value\' => \'Rp 15 jt\']]" />'
        );
        $this->assertStringContainsString('data-count-to="7"', $summaryHtml);
        $this->assertStringContainsString('data-count-to="15"', $summaryHtml);
        $this->assertStringContainsString('data-prefix="Rp "', $summaryHtml);
        $this->assertStringContainsString('data-suffix=" jt"', $summaryHtml);
        $this->assertStringContainsString('7', $summaryHtml);
        $this->assertStringContainsString('Rp 15 jt', $summaryHtml);

        // 4. Donut chart center total
        $donutHtml = (string) $this->blade('<x-donut-chart :items="[]" :total="25" />');
        $this->assertStringContainsString('data-count-to="25"', $donutHtml);
        $this->assertStringContainsString('25', $donutHtml);
    }
}
