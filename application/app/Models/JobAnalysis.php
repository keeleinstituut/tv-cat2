<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobAnalysis extends Model
{
    use HasFactory;
    use HasUuids;

    protected $casts = ['results' => 'array'];

    public function job() {
        return $this->belongsTo(Job::class);
    }

    public function analysis() {
        return $this->belongsTo(Analysis::class);
    }
}
