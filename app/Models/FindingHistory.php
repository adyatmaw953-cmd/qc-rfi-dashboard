<?php

namespace App\Models;

use Database\Factories\FindingHistoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FindingHistory extends Model
{
    /** @use HasFactory<FindingHistoryFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'qc_finding_id',
        'event',
        'actor_id',
        'old_value',
        'new_value',
        'meta',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function qcFinding(): BelongsTo
    {
        return $this->belongsTo(QcFinding::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
