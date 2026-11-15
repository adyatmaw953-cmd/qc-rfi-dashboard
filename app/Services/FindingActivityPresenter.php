<?php

namespace App\Services;

use App\Models\FindingActivity;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class FindingActivityPresenter
{
    private const TZ = 'Asia/Jakarta';

    /**
     * Present a FindingActivity as a structured display array.
     *
     * @return array{
     *     actor: string,
     *     verb: string,
     *     object: string,
     *     tone: 'info'|'warn'|'ok'|'neutral',
     *     icon: string,
     *     chips: list<string>,
     *     note: ?string,
     *     time_absolute: string,
     *     time_relative: string,
     * }
     */
    public static function present(FindingActivity $activity): array
    {
        $actor = self::resolveActor($activity);
        $meta = is_array($activity->meta) ? $activity->meta : [];

        [$verb, $object, $tone, $icon, $chips] = self::describe($activity, $meta, $actor);

        $sentence = trim(($actor !== '' ? $actor.' ' : '').$verb.($object !== '' ? ' '.$object : ''));

        return [
            'actor' => $actor,
            'verb' => $verb,
            'object' => $object,
            'sentence' => $sentence,
            'tone' => $tone,
            'icon' => $icon,
            'chips' => $chips,
            'note' => $activity->note,
            'time_absolute' => self::absoluteTime($activity->occurred_at),
            'time_relative' => self::relativeTime($activity->occurred_at),
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{string, string, string, string, list<string>}
     */
    private static function describe(FindingActivity $activity, array $meta, string $actor): array
    {
        return match ($activity->type) {
            FindingActivity::TYPE_RFI_SUBMITTED => [
                'mengirim',
                $meta['rfi_no'] ?? 'RFI',
                'info',
                'document',
                [],
            ],
            FindingActivity::TYPE_FINDING_CREATED => [
                'membuat temuan',
                '(review RFI: '.($meta['disposition'] ?? '-').')',
                'warn',
                'flag',
                [],
            ],
            FindingActivity::TYPE_ACTION_PLAN_SAVED => [
                ($meta['first'] ?? false) ? 'menyimpan action plan' : 'memperbarui action plan',
                '',
                'neutral',
                'pencil',
                [],
            ],
            FindingActivity::TYPE_REINSPECT_SUBMITTED => (function () use ($meta): array {
                $round = $meta['round'] ?? null;

                return [
                    'mengirim',
                    $round ? "Re-Inspect putaran {$round}" : 'Re-Inspect',
                    'info',
                    'arrow-up',
                    $round ? ["Putaran {$round}"] : [],
                ];
            })(),
            FindingActivity::TYPE_REINSPECT_VERIFIED => (function () use ($meta): array {
                $round = $meta['round'] ?? null;
                $result = $meta['result'] ?? 'not_ok';
                $isOk = $result === 'ok';
                $resultLabel = $isOk ? 'OK' : 'Belum OK';
                $suffix = $round ? " putaran {$round}" : '';

                return [
                    'memverifikasi',
                    "Re-Inspect{$suffix}: {$resultLabel}",
                    $isOk ? 'ok' : 'warn',
                    $isOk ? 'check' : 'x-circle',
                    [$resultLabel],
                ];
            })(),
            FindingActivity::TYPE_DUE_DATE_CHANGED => (function () use ($meta): array {
                $from = isset($meta['from']) ? Carbon::parse($meta['from'])->format('d M') : '—';
                $to = isset($meta['to']) ? Carbon::parse($meta['to'])->format('d M') : '—';

                return [
                    'mengubah due date',
                    "dari {$from} ke {$to}",
                    'neutral',
                    'calendar',
                    [],
                ];
            })(),
            default => [
                'melakukan',
                $activity->type,
                'neutral',
                'circle',
                [],
            ],
        };
    }

    private static function resolveActor(FindingActivity $activity): string
    {
        if ($activity->actor_name !== null) {
            return $activity->actor_name;
        }

        if ($activity->actor_role !== null) {
            return ucfirst(str_replace('_', ' ', $activity->actor_role));
        }

        return 'Sistem';
    }

    public static function absoluteTime(CarbonInterface $dt): string
    {
        return Carbon::instance($dt)->setTimezone(self::TZ)->format('d M Y H:i');
    }

    public static function relativeTime(CarbonInterface $dt): string
    {
        $now = Carbon::now(self::TZ);
        $local = Carbon::instance($dt)->setTimezone(self::TZ);
        $diffSeconds = $now->diffInSeconds($local, absolute: true);

        if ($diffSeconds < 60) {
            return 'Baru saja';
        }

        $diffMinutes = (int) floor($diffSeconds / 60);

        if ($diffMinutes < 60) {
            return "{$diffMinutes} menit lalu";
        }

        $diffHours = (int) floor($diffMinutes / 60);

        if ($diffHours < 24) {
            return "{$diffHours} jam lalu";
        }

        $diffDays = (int) floor($diffHours / 24);

        if ($diffDays <= 30) {
            return "{$diffDays} hari lalu";
        }

        return self::absoluteTime($dt);
    }
}
