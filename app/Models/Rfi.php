<?php

namespace App\Models;

use App\Enums\RfiResult;
use Database\Factories\RfiFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Rfi extends Model
{
    /** @use HasFactory<RfiFactory> */
    use HasFactory;

    protected $fillable = [
        'rfi_no',
        'project_id',
        'category',
        'details',
        'date_inspect',
        'scheduled_at',
        'note',
        'status',
        'result',
        'qc_note',
        'created_by',
        'reviewed_by',
        'submitted_at',
        'reviewed_at',
        'deadline_at',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'date_inspect' => 'date',
            'scheduled_at' => 'date',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'deadline_at' => 'datetime',
            'result' => RfiResult::class,
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function qcFinding(): HasOne
    {
        return $this->hasOne(QcFinding::class);
    }
}
