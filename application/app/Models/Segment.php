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
        'position',
        'xliff_mrk_id',
        'xliff_internal_id',
    ];
}
