<?php

namespace App\Models;

use App\Enums\RfiResult;
use Carbon\Carbon;
use Database\Factories\QcFindingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class QcFinding extends Model
{
    /** @use HasFactory<QcFindingFactory> */
    use HasFactory;

    protected $fillable = [
        'rfi_id',
        'result',
        'finding_list',
        'date_of_finding',
        'due_date',
        'status',
        'action_plan',
        'action_plan_by',
        'action_plan_at',
        'date_of_close',
    ];

    protected function casts(): array
    {
        return [
            'result' => RfiResult::class,
            'date_of_finding' => 'date',
            'due_date' => 'date',
            'action_plan_at' => 'datetime',
            'date_of_close' => 'date',
        ];
    }

    public function rfi(): BelongsTo
    {
        return $this->belongsTo(Rfi::class);
    }

    public function actionPlanBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'action_plan_by');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(FindingSubmission::class)->orderBy('round_no');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(FindingHistory::class)->latest('created_at')->latest('id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(FindingActivity::class)->orderByDesc('occurred_at')->orderByDesc('id');
    }

    public function latestActivity(): HasOne
    {
        return $this->hasOne(FindingActivity::class)->latestOfMany(['occurred_at', 'id']);
    }

    /**
     * @return 'due_today'|'overdue'|null
     */
    public function dueDateStatus(): ?string
    {
        if ($this->status === 'closed' || $this->due_date === null) {
            return null;
        }

        $today = Carbon::now('Asia/Jakarta')->toDateString();
        $dueDate = $this->due_date->toDateString();

        if ($dueDate === $today) {
            return 'due_today';
        }

        if ($dueDate < $today) {
            return 'overdue';
        }

        return null;
    }

    public function isDueToday(): bool
    {
        return $this->dueDateStatus() === 'due_today';
    }

    public function isOverdue(): bool
    {
        return $this->dueDateStatus() === 'overdue';
    }

    /**
     * Scope: non-closed findings whose due_date is strictly before today (Asia/Jakarta).
     */
    public function scopeOverdue(Builder $query): void
    {
        $today = Carbon::now('Asia/Jakarta')->toDateString();
        $query->where('status', '!=', 'closed')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today);
    }

    /**
     * Scope: non-closed findings whose due_date is exactly today (Asia/Jakarta).
     */
    public function scopeDueToday(Builder $query): void
    {
        $today = Carbon::now('Asia/Jakarta')->toDateString();
        $query->where('status', '!=', 'closed')
            ->whereNotNull('due_date')
            ->whereDate('due_date', $today);
    }

    /**
     * Scope: non-closed findings whose due_date is today or overdue (Asia/Jakarta).
     */
    public function scopeUrgentDue(Builder $query): void
    {
        $today = Carbon::now('Asia/Jakarta')->toDateString();
        $query->where('status', '!=', 'closed')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', $today);
    }
}
