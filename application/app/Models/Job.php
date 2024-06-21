<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
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
    public const XLIFF_FILE_COLLECTION = 'xliff';
    public const TARGET_XLIFF_FILE_COLLECTION = 'target-xliff';

    public const TARGET_FILE_COLLECTION = 'target';


    protected $fillable = [
        'target_locale',
        'project_id',
    ];

    protected $casts = [
        'xliff_parsed' => AsArrayObject::class,
    ];

    public function registerMediaCollections(): void
    {
        $this
            ->addMediaCollection(self::SOURCE_FILE_COLLECTION)
            ->singleFile();

        $this
            ->addMediaCollection(self::XLIFF_FILE_COLLECTION)
            ->singleFile();

        $this
            ->addMediaCollection(self::TARGET_XLIFF_FILE_COLLECTION)
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

    public function xliffFileCollection()
    {
        return $this->media()->where('collection_name', self::XLIFF_FILE_COLLECTION);
    }

    public function targetXliffFileCollection()
    {
        return $this->media()->where('collection_name', self::TARGET_XLIFF_FILE_COLLECTION);
    }

    public function targetFileCollection()
    {
        return $this->media()->where('collection_name', self::TARGET_FILE_COLLECTION);
    }
}
