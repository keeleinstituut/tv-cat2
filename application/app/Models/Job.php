<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Job extends Model implements HasMedia
{
    use HasFactory;
    use HasUuids;
    use InteractsWithMedia;

    public const SOURCE_FILE_COLLECTION = 'source';

    public const STATUS_PROCESSING = 'processing';
    public const STATUS_READY = 'ready';
    public const STATUS_FAILED = 'failed';

    public const TARGET_FILE_COLLECTION = 'target';


    protected $fillable = [
        'target_locale',
        'project_id',
    ];

    public function registerMediaCollections(): void
    {
        $this
            ->addMediaCollection(self::SOURCE_FILE_COLLECTION)
            ->singleFile();

        $this
            ->addMediaCollection(self::TARGET_FILE_COLLECTION)
            ->singleFile();
    }

    public function project() {
        return $this->belongsTo(Project::class);
    }

    public function segments() {
        return $this->hasMany(Segment::class);
    }

    public function sourceFileCollection()
    {
        return $this->media()->where('collection_name', self::SOURCE_FILE_COLLECTION);
    }

    public function targetFileCollection()
    {
        return $this->media()->where('collection_name', self::TARGET_FILE_COLLECTION);
    }
}
