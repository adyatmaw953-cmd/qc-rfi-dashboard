<?php

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    protected $fillable = [
        'external_id',
        'project_code',
        'project_name',
        'customer',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'external_id' => 'integer',
            'synced_at' => 'datetime',
        ];
    }

    public function rfis(): HasMany
    {
        return $this->hasMany(Rfi::class);
    }

    public function mdr(): HasOne
    {
        return $this->hasOne(Mdr::class);
    }

    public function ncrs(): HasMany
    {
        return $this->hasMany(Ncr::class, 'project_code', 'project_code');
    }
}
