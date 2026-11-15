<?php

namespace App\Models;

use Database\Factories\FindingEvidenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FindingEvidence extends Model
{
    /** @use HasFactory<FindingEvidenceFactory> */
    use HasFactory;

    protected $table = 'finding_evidences';

    protected $fillable = [
        'finding_submission_id',
        'original_name',
        'path',
        'mime',
        'size',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(FindingSubmission::class, 'finding_submission_id');
    }
}
