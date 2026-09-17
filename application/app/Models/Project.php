<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'name',
        'source_locale',
        'tenant_id',
    ];

    public function jobs() {
        return $this->hasMany(Job::class);
    }

    public function translationMemories() {
        return $this->belongsToMany(TranslationMemory::class, 'project_translation_memory')
            ->withPivot('read', 'write');
    }
}
