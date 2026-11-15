<?php

namespace App\Console\Commands;

use App\Services\DailyReminderService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reminders:send-daily {--dry-run : Hanya tampilkan ringkasan tanpa menyimpan notifikasi}')]
#[Description('Kirim notifikasi ringkasan pengingat harian ke pengguna (QC & Production)')]
class SendDailyReminders extends Command
{
    public function handle(DailyReminderService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Mode DRY-RUN aktif: tidak ada notifikasi yang disimpan ke database.');
        }

        $results = $service->sendDailyReminders($dryRun);

        if (empty($results)) {
            $this->info('Tidak ada pengguna di sistem.');

            return self::SUCCESS;
        }

        $rows = [];
        $sentCount = 0;
        $skippedCount = 0;

        foreach ($results as $result) {
            $user = $result['user'];
            $status = $result['status'];
            $message = $result['message'];

            $statusLabel = match ($status) {
                'sent' => 'Terkirim',
                'dry_run' => 'Simulasi (Akan Dikirim)',
                'already_sent' => 'Dilewati (Sudah Dikirim Hari Ini)',
                'no_items' => 'Dilewati (Tidak Ada Item)',
                default => $status,
            };

            if (in_array($status, ['sent', 'dry_run'], true)) {
                $sentCount++;
            } else {
                $skippedCount++;
            }

            $rows[] = [
                $user->id,
                $user->name,
                $user->role,
                $statusLabel,
                $message ?? '—',
            ];
        }

        $this->table(
            ['ID', 'Nama', 'Role', 'Status', 'Isi Pesan'],
            $rows
        );

        if ($dryRun) {
            $this->info("Simulasi selesai. {$sentCount} pengguna akan menerima pengingat, {$skippedCount} dilewati.");
        } else {
            $this->info("Pengiriman selesai. {$sentCount} notifikasi terkirim, {$skippedCount} dilewati.");
        }

        return self::SUCCESS;
    }
}
