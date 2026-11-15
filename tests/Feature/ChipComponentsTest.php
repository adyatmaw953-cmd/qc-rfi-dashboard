<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Tests\TestCase;

class ChipComponentsTest extends TestCase
{
    public function test_status_chip_renders_mapped_colors_and_labels(): void
    {
        // Open
        $open = (string) $this->blade('<x-status-chip status="open" />');
        $this->assertStringContainsString('Open', $open);
        $this->assertStringContainsString('bg-[#E3EEFB]', $open);
        $this->assertStringContainsString('text-[#1B4C8C]', $open);
        $this->assertStringContainsString('bg-[#8DB6E8]', $open);

        // NC
        $nc = (string) $this->blade('<x-status-chip status="nc" />');
        $this->assertStringContainsString('NC', $nc);
        $this->assertStringContainsString('bg-[#FCE8D8]', $nc);
        $this->assertStringContainsString('text-[#8F3F08]', $nc);
        $this->assertStringContainsString('bg-[#E07B2E]', $nc);

        // Menunggu verifikasi
        $waiting = (string) $this->blade('<x-status-chip status="waiting_verification" />');
        $this->assertStringContainsString('Menunggu verifikasi', $waiting);
        $this->assertStringContainsString('bg-[#FCE8D8]', $waiting);
        $this->assertStringContainsString('text-[#8F3F08]', $waiting);

        // Perlu action plan
        $actionPlan = (string) $this->blade('<x-status-chip status="perlu_action_plan" />');
        $this->assertStringContainsString('Perlu action plan', $actionPlan);
        $this->assertStringContainsString('bg-[#FCE8D8]', $actionPlan);

        // Close / Closed
        $close = (string) $this->blade('<x-status-chip status="close" />');
        $this->assertStringContainsString('Closed', $close);
        $this->assertStringContainsString('bg-[#E1F0E2]', $close);
        $this->assertStringContainsString('text-[#1F5B2A]', $close);

        // CA Closed
        $caClosed = (string) $this->blade('<x-status-chip status="CA Closed" />');
        $this->assertStringContainsString('CA Closed', $caClosed);
        $this->assertStringContainsString('bg-[#FBF0C9]', $caClosed);
        $this->assertStringContainsString('text-[#6B4E00]', $caClosed);

        // Custom label override
        $custom = (string) $this->blade('<x-status-chip status="open" label="Custom Label" />');
        $this->assertStringContainsString('Custom Label', $custom);
    }

    public function test_level_chip_renders_mapped_levels(): void
    {
        // Major
        $major = (string) $this->blade('<x-level-chip level="Major" />');
        $this->assertStringContainsString('Major', $major);
        $this->assertStringContainsString('bg-[#FCE8D8]', $major);
        $this->assertStringContainsString('text-[#8F3F08]', $major);

        // Minor
        $minor = (string) $this->blade('<x-level-chip level="Minor" />');
        $this->assertStringContainsString('Minor', $minor);
        $this->assertStringContainsString('bg-[#EEEBE1]', $minor);
        $this->assertStringContainsString('text-[#3F4A41]', $minor);

        // Near Miss
        $nearMiss = (string) $this->blade('<x-level-chip level="Near Miss" />');
        $this->assertStringContainsString('Near Miss', $nearMiss);
        $this->assertStringContainsString('bg-[#E3EEFB]', $nearMiss);
        $this->assertStringContainsString('text-[#1B4C8C]', $nearMiss);
    }

    public function test_due_chip_renders_dates_and_completion_states(): void
    {
        $now = Carbon::parse('2026-10-20 12:00:00', 'Asia/Jakarta');
        Carbon::setTestNow($now);

        // Overdue (diff < 0)
        $overdue = (string) $this->blade('<x-due-chip date="2026-10-18" />');
        $this->assertStringContainsString('Terlambat 2 hari', $overdue);
        $this->assertStringContainsString('bg-[#FCE8D8]', $overdue);

        // Future (diff > 0)
        $future = (string) $this->blade('<x-due-chip date="2026-10-25" />');
        $this->assertStringContainsString('5 hari lagi', $future);
        $this->assertStringContainsString('bg-[#EEEBE1]', $future);

        // Done / all closed
        $done = (string) $this->blade('<x-due-chip :done="true" />');
        $this->assertStringContainsString('Selesai', $done);
        $this->assertStringContainsString('bg-[#E1F0E2]', $done);

        $allClosed = (string) $this->blade('<x-due-chip :all-closed="true" />');
        $this->assertStringContainsString('Semua closed', $allClosed);

        Carbon::setTestNow();
    }

    public function test_detail_list_and_detail_item_renders_labels_and_empty_states(): void
    {
        $rendered = (string) $this->blade('
            <x-detail-list>
                <x-detail-item label="Project Name" value="Heavy Crane" />
                <x-detail-item label="Customer" value="" />
            </x-detail-list>
        ');

        $this->assertStringContainsString('Project Name', $rendered);
        $this->assertStringContainsString('Heavy Crane', $rendered);
        $this->assertStringContainsString('Customer', $rendered);
        $this->assertStringContainsString('Belum diisi', $rendered);
    }
}
