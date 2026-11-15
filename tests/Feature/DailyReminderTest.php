<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\QcFinding;
use App\Models\Rfi;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DailyReminderTest extends TestCase
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
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_dry_run_option_does_not_save_notifications(): void
    {
        $now = Carbon::parse('2026-11-04 08:00:00', 'Asia/Jakarta');
        Carbon::setTestNow($now);

        $production = User::factory()->create(['role' => 'production']);
        $project = Project::factory()->create();
        $rfi = Rfi::factory()->create(['project_id' => $project->id, 'category' => 'B']);

        QcFinding::factory()->create([
            'rfi_id' => $rfi->id,
            'status' => 'open',
            'due_date' => '2026-11-04',
        ]);

        $this->artisan('reminders:send-daily', ['--dry-run' => true])
            ->expectsOutputToContain('Mode DRY-RUN aktif')
            ->expectsOutputToContain('Pengingat: 1 temuan jatuh tempo hari ini. Buka QC Finding.')
            ->assertSuccessful();

        $this->assertSame(0, $production->notifications()->count());
    }

    public function test_production_receives_single_combined_reminder_for_due_today_and_overdue_findings(): void
    {
        $now = Carbon::parse('2026-11-04 08:00:00', 'Asia/Jakarta');
        Carbon::setTestNow($now);

        $production = User::factory()->create(['role' => 'production']);
        $project = Project::factory()->create();

        $rfi1 = Rfi::factory()->create(['project_id' => $project->id, 'category' => 'B']);
        $rfi2 = Rfi::factory()->create(['project_id' => $project->id, 'category' => 'B']);
        $rfi3 = Rfi::factory()->create(['project_id' => $project->id, 'category' => 'B']);

        // 2 due today
        QcFinding::factory()->create([
            'rfi_id' => $rfi1->id,
            'status' => 'open',
            'due_date' => '2026-11-04',
        ]);
        QcFinding::factory()->create([
            'rfi_id' => $rfi2->id,
            'status' => 'open',
            'due_date' => '2026-11-04',
        ]);

        // 1 overdue
        QcFinding::factory()->create([
            'rfi_id' => $rfi3->id,
            'status' => 'open',
            'due_date' => '2026-11-02',
        ]);

        $this->artisan('reminders:send-daily')
            ->assertSuccessful();

        $this->assertSame(1, $production->notifications()->count());

        $notification = $production->notifications()->first();
        $this->assertSame('daily_reminder', $notification->data['type']);
        $this->assertSame(
            'Pengingat: 2 temuan jatuh tempo hari ini dan 1 terlambat. Buka QC Finding.',
            $notification->data['body']
        );
        $this->assertSame(route('qc-finding.index', ['pf' => 'terlambat']), $notification->data['url']);
    }

    public function test_qc_receives_reminder_for_unreviewed_rfis_and_h_plus_1(): void
    {
        $now = Carbon::parse('2026-11-04 08:00:00', 'Asia/Jakarta');
        Carbon::setTestNow($now);

        $qc = User::factory()->create(['role' => 'qc_qa']);
        $project = Project::factory()->create();

        // 2 RFIs submitted yesterday (H+1 is today 23:59 -> not overdue yet)
        Rfi::factory()->create([
            'project_id' => $project->id,
            'category' => 'B',
            'status' => 'open',
            'submitted_at' => '2026-11-03 10:00:00',
        ]);
        Rfi::factory()->create([
            'project_id' => $project->id,
            'category' => 'B',
            'status' => 'open',
            'submitted_at' => '2026-11-03 11:00:00',
        ]);

        // 1 RFI submitted 3 days ago (H+1 deadline passed)
        Rfi::factory()->create([
            'project_id' => $project->id,
            'category' => 'B',
            'status' => 'open',
            'submitted_at' => '2026-11-01 10:00:00',
        ]);

        $this->artisan('reminders:send-daily')
            ->assertSuccessful();

        $this->assertSame(1, $qc->notifications()->count());

        $notification = $qc->notifications()->first();
        $this->assertSame('daily_reminder', $notification->data['type']);
        $this->assertSame(
            'Pengingat: 3 RFI belum direspon, 1 sudah melewati batas H+1. Buka daftar RFI.',
            $notification->data['body']
        );
        $this->assertSame(route('rfi.index'), $notification->data['url']);
    }

    public function test_deduplication_prevents_duplicate_reminders_on_the_same_day(): void
    {
        $now = Carbon::parse('2026-11-04 08:00:00', 'Asia/Jakarta');
        Carbon::setTestNow($now);

        $production = User::factory()->create(['role' => 'production']);
        $project = Project::factory()->create();
        $rfi = Rfi::factory()->create(['project_id' => $project->id, 'category' => 'B']);

        QcFinding::factory()->create([
            'rfi_id' => $rfi->id,
            'status' => 'open',
            'due_date' => '2026-11-04',
        ]);

        // First run sends notification
        $this->artisan('reminders:send-daily')->assertSuccessful();
        $this->assertSame(1, $production->notifications()->count());

        // Second run on same day must NOT send duplicate
        $this->artisan('reminders:send-daily')
            ->expectsOutputToContain('Dilewati (Sudah Dikirim Hari Ini)')
            ->assertSuccessful();

        $this->assertSame(1, $production->notifications()->count());
    }

    public function test_no_reminder_sent_when_user_has_no_actionable_items(): void
    {
        $now = Carbon::parse('2026-11-04 08:00:00', 'Asia/Jakarta');
        Carbon::setTestNow($now);

        $production = User::factory()->create(['role' => 'production']);
        $qc = User::factory()->create(['role' => 'qc_qa']);

        $this->artisan('reminders:send-daily')->assertSuccessful();

        $this->assertSame(0, $production->notifications()->count());
        $this->assertSame(0, $qc->notifications()->count());
    }

    public function test_notification_renders_in_list_and_redirects_on_click(): void
    {
        $now = Carbon::parse('2026-11-04 08:00:00', 'Asia/Jakarta');
        Carbon::setTestNow($now);

        $production = User::factory()->create(['role' => 'production']);
        $project = Project::factory()->create();
        $rfi = Rfi::factory()->create(['project_id' => $project->id, 'category' => 'B']);

        QcFinding::factory()->create([
            'rfi_id' => $rfi->id,
            'status' => 'open',
            'due_date' => '2026-11-04',
        ]);

        $this->artisan('reminders:send-daily')->assertSuccessful();

        $notification = $production->notifications()->firstOrFail();

        // Check list view renders chip and message
        $response = $this->actingAs($production)->get(route('notifications.index'));
        $response->assertOk();
        $response->assertSee('Pengingat');
        $response->assertSee('Pengingat: 1 temuan jatuh tempo hari ini. Buka QC Finding.');

        // Clicking the notification marks it as read and redirects
        $readResponse = $this->actingAs($production)->post(route('notifications.read', ['id' => $notification->id]));
        $readResponse->assertRedirect(route('qc-finding.index', ['pf' => 'terlambat']));
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_command_is_scheduled_daily_at_0800_wib_without_overlapping(): void
    {
        $schedule = app(Schedule::class);

        $events = collect($schedule->events())->filter(function ($event) {
            return str_contains($event->command ?? '', 'reminders:send-daily');
        });

        $this->assertCount(1, $events);

        $event = $events->first();
        $this->assertSame('0 8 * * *', $event->expression);
        $this->assertSame('Asia/Jakarta', $event->timezone);
        $this->assertTrue($event->withoutOverlapping);
    }
}
