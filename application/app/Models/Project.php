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
    ];

    public function jobs() {
        return $this->hasMany(Job::class);
    }
}
