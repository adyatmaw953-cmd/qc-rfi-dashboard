<?php

namespace App\Models;

use App\Enums\MdrStatus;
use Database\Factories\MdrReportStatusFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MdrReportStatus extends Model
{
    /** @use HasFactory<MdrReportStatusFactory> */
    use HasFactory;

    protected $fillable = [
        'mdr_id',
        'report_key',
        'report_title',
        'status',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => MdrStatus::class,
        ];
    }

    public function mdr(): BelongsTo
    {
        return $this->belongsTo(Mdr::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getLabelAttribute(): string
    {
        return $this->report_title ?: (config('mudalaya.mdr_reports')[$this->report_key] ?? $this->report_key);
    }
}
