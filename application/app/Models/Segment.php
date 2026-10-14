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
        'source_words',
        'source_chars',
        'target',
        'confirmed',
        'locked',
        'position',
        'filter_segment_id',
    ];

    protected $casts = [
        'pretranslate_suggestion_score' => 'double',
        'confirmed'                     => 'boolean',
        'locked'                        => 'boolean',
    ];

    public function job() {
        return $this->belongsTo(Job::class);
    }
}
