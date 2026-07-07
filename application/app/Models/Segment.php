<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Segment extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'job_id',
        'source',
        'target',
        'confirmed',
        'position',
        'xliff_mrk_id',
        'xliff_internal_id',
    ];

    protected $casts = [
        'pretranslate_suggestion_score' => 'double',
        'confirmed'                     => 'boolean',
    ];

    public function job() {
        return $this->belongsTo(Job::class);
    }
}
