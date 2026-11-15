<?php

namespace App\Models;

use Database\Factories\NcrFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ncr extends Model
{
    /** @use HasFactory<NcrFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ncr_number',
        'finding_date',
        'department',
        'related_departments',
        'area_finding',
        'level',
        'type',
        'project_code',
        'customer',
        'responsible_pic',
        'problem_description',
        'root_cause',
        'root_cause_category',
        'corrective_action',
        'pic_ca',
        'ca_closed_date',
        'preventive_action',
        'pic_pa',
        'pa_closed_date',
        'status',
        'nc_cost',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'finding_date' => 'date',
            'ca_closed_date' => 'date',
            'pa_closed_date' => 'date',
            'related_departments' => 'array',
            'nc_cost' => 'integer',
        ];
    }

    /**
     * Kategori akar masalah (mendukung multi-select, disimpan sebagai JSON di kolom string).
     */
    protected function rootCauseCategory(): Attribute
    {
        return Attribute::make(
            get: function ($value): array {
                if ($value === null || $value === '') {
                    return [];
                }
                if (is_array($value)) {
                    return $value;
                }
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    return $decoded;
                }

                return array_values(array_filter(array_map('trim', explode(',', $value))));
            },
            set: function ($value) {
                if (is_array($value)) {
                    return json_encode(array_values($value));
                }

                return $value;
            }
        );
    }

    /**
     * Time Leads (hari) = ca_closed_date dikurangi finding_date.
     * Mengembalikan null jika ca_closed_date kosong.
     */
    public function getTimeLeadsAttribute(): ?int
    {
        if (! $this->ca_closed_date || ! $this->finding_date) {
            return null;
        }

        return (int) $this->finding_date->diffInDays($this->ca_closed_date, false);
    }

    /**
     * Nama bulan temuan dalam bahasa Indonesia.
     */
    public function getMonthNameAttribute(): string
    {
        return $this->finding_date ? $this->finding_date->locale('id')->isoFormat('MMMM') : '';
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_code', 'project_code');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Generate ncr_number berurutan per tahun dengan format NCR-{tahun}-{urut 3 digit}.
     */
    public static function generateNcrNumber(int $year): string
    {
        $prefix = "NCR-{$year}-";

        $lastNcr = static::withTrashed()
            ->where('ncr_number', 'like', "{$prefix}%")
            ->lockForUpdate()
            ->orderByDesc('ncr_number')
            ->first();

        if ($lastNcr) {
            $lastSeq = (int) substr($lastNcr->ncr_number, strlen($prefix));
            $nextSeq = $lastSeq + 1;
        } else {
            $nextSeq = 1;
        }

        return sprintf('%s%03d', $prefix, $nextSeq);
    }
}
