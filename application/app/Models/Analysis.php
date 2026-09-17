<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Analysis extends Model
{
    use HasFactory;
    use HasUuids;

    protected $casts = ['translation_memory_ids' => 'array'];

    public function project() {
        return $this->belongsTo(Project::class);
    }

    public function jobAnalyses() {
        return $this->hasMany(JobAnalysis::class);
    }
}
