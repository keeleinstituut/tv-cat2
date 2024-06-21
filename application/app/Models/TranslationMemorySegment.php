<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TranslationMemorySegment extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'translation_memory_id',
        'segment_id',

        'source',
        'source_context_before',
        'source_context_after',

        'target',
        'target_context_before',
        'target_context_after',
    ];
}
