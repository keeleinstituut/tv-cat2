<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TranslationMemory extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'name',
        'source_locale',
        'target_locale',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function translationMemorySegments() {
        return $this->hasMany(TranslationMemorySegment::class);
    }
}
