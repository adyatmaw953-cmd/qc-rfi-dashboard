<?php

namespace App\Models;

use App\Enums\MdrStatus;
use Database\Factories\MdrFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mdr extends Model
{
    /** @use HasFactory<MdrFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'owner_serial_no',
        'manufacture_serial_no',
        'unit_no',
        'created_by',
    ];

    protected $appends = [
        'overall_status',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function reportStatuses(): HasMany
    {
        return $this->hasMany(MdrReportStatus::class);
    }

    public function getOverallStatusAttribute(): MdrStatus
    {
        $statuses = $this->reportStatuses;

        if ($statuses->isEmpty()) {
            return MdrStatus::Open;
        }

        foreach ($statuses as $status) {
            if (! in_array($status->status?->value, ['close', 'na'], true)) {
                return MdrStatus::Open;
            }
        }

        return MdrStatus::Close;
    }
}
