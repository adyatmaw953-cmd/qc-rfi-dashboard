<?php

namespace App\Models;

use Database\Factories\FindingSubmissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FindingSubmission extends Model
{
    /** @use HasFactory<FindingSubmissionFactory> */
    use HasFactory;

    protected $fillable = [
        'qc_finding_id',
        'round_no',
        'note',
        'submitted_by',
        'submitted_at',
        'verification_result',
        'verification_note',
        'verified_by',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function qcFinding(): BelongsTo
    {
        return $this->belongsTo(QcFinding::class);
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(FindingEvidence::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
