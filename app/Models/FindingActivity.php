<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class FindingActivity extends Model
{
    public const UPDATED_AT = null;

    public const TYPE_RFI_SUBMITTED = 'rfi_submitted';

    public const TYPE_FINDING_CREATED = 'finding_created';

    public const TYPE_ACTION_PLAN_SAVED = 'action_plan_saved';

    public const TYPE_REINSPECT_SUBMITTED = 'reinspect_submitted';

    public const TYPE_REINSPECT_VERIFIED = 'reinspect_verified';

    public const TYPE_DUE_DATE_CHANGED = 'due_date_changed';

    protected $fillable = [
        'qc_finding_id',
        'type',
        'actor_id',
        'actor_name',
        'actor_role',
        'meta',
        'note',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('FindingActivity is append-only and cannot be updated or deleted.');
        });

        static::deleting(function (): never {
            throw new LogicException('FindingActivity is append-only and cannot be updated or deleted.');
        });
    }

    public function finding(): BelongsTo
    {
        return $this->belongsTo(QcFinding::class, 'qc_finding_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
