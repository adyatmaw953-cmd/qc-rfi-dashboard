<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

class NotificationHelper
{
    private const TZ = 'Asia/Jakarta';

    /**
     * Map notification type / event key to icon name and chip attributes.
     *
     * @return array{
     *     icon: string,
     *     chip: array{label: string, class: string}
     * }
     */
    public static function meta(string $type, ?string $title = null, ?string $url = null): array
    {
        $normalizedType = trim($type);

        $chip = match ($normalizedType) {
            'daily_reminder' => [
                'label' => 'Pengingat',
                'class' => 'bg-[#FEF3C7] text-[#92400E]',
            ],
            'rfi.submitted' => [
                'label' => 'Perlu review',
                'class' => 'bg-chip-open-bg text-chip-open-fg',
            ],
            'finding.reinspect_submitted' => [
                'label' => 'Re-Inspect',
                'class' => 'bg-chip-open-bg text-chip-open-fg',
            ],
            'rfi.reviewed_nc', 'reviewed_nc' => [
                'label' => 'NC',
                'class' => 'bg-chip-nc-bg text-chip-nc-fg',
            ],
            'finding.verified_rejected', 'verified_rejected' => [
                'label' => 'Ditolak',
                'class' => 'bg-chip-nc-bg text-chip-nc-fg',
            ],
            'finding.due_date_changed', 'due_date_changed' => [
                'label' => 'Due date',
                'class' => 'bg-chip-nc-bg text-chip-nc-fg',
            ],
            'rfi.reviewed_ok', 'reviewed_ok' => [
                'label' => 'Close',
                'class' => 'bg-chip-close-bg text-chip-close-fg',
            ],
            'finding.verified_ok', 'verified_ok' => [
                'label' => 'Close',
                'class' => 'bg-chip-close-bg text-chip-close-fg',
            ],
            default => (
                // Fallback smart matching by title if type happens to be generic
                str_contains(strtolower($title ?? ''), 'nc')
                    ? ['label' => 'NC', 'class' => 'bg-chip-nc-bg text-chip-nc-fg']
                    : (str_contains(strtolower($title ?? ''), 'review')
                        ? ['label' => 'Perlu review', 'class' => 'bg-chip-open-bg text-chip-open-fg']
                        : ['label' => 'Info', 'class' => 'bg-chip-open-bg text-chip-open-fg'])
            ),
        };

        $icon = match ($normalizedType) {
            'daily_reminder' => 'reminder',
            'rfi.submitted', 'rfi.reviewed_ok' => 'rfi',
            'finding.reinspect_submitted', 'finding.due_date_changed' => 'qc_finding',
            'finding.verified_ok' => 'doc_check',
            'rfi.reviewed_nc', 'reviewed_nc', 'finding.verified_rejected', 'verified_rejected' => 'ncr',
            default => (
                str_contains(strtolower($title ?? ''), 'temuan') || str_contains(strtolower($title ?? ''), 'nc')
                    ? 'ncr'
                    : 'rfi'
            ),
        };

        return [
            'icon' => $icon,
            'chip' => $chip,
        ];
    }

    /**
     * Format a Carbon timestamp into compact Indonesian relative time.
     * <1 menit: "Baru saja"
     * <60 menit: "{n} mnt lalu"
     * <24 jam: "{n} jam lalu"
     * kemarin (kalender kemarin / 24-48 jam): "Kemarin"
     * <7 hari: "{n} hari lalu"
     * selebihnya: "d MMM yyyy"
     */
    public static function formatTime(CarbonInterface $timestamp): string
    {
        $now = Carbon::now();
        $time = Carbon::instance($timestamp);

        $diffInSeconds = max(0, $now->diffInSeconds($time, false));
        // If $time is in the past, $diffInSeconds should be based on absolute diff
        $diffInSeconds = (int) abs($now->diffInSeconds($time));

        if ($diffInSeconds < 60) {
            return 'Baru saja';
        }

        $diffInMinutes = (int) floor($diffInSeconds / 60);
        if ($diffInMinutes < 60) {
            return "{$diffInMinutes} mnt lalu";
        }

        $diffInHours = (int) floor($diffInSeconds / 3600);
        if ($diffInHours < 24) {
            return "{$diffInHours} jam lalu";
        }

        if ($time->isYesterday()) {
            return 'Kemarin';
        }

        $diffInDays = (int) floor($diffInSeconds / 86400);
        if ($diffInDays < 7) {
            return "{$diffInDays} hari lalu";
        }

        return $time->locale('id')->translatedFormat('d M Y');
    }
}
