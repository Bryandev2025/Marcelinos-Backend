<?php

namespace App\Models;

use Spatie\MediaLibrary\HasMedia;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Gallery extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $appends = [
        'image_url',
    ];

    protected $fillable = [];

    /**
     * Define Media Collections
     * This tells Spatie how to handle gallery images.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')
            ->singleFile(); // Ensures only one image per gallery item
    }

    public function getImageUrlAttribute(): ?string
    {
        $media = $this->getFirstMedia('image');

        return $media ? $this->resolveMediaUrl($media) : null;
    }

    private function resolveMediaUrl(Media $media): string
    {
        $lifetime = (int) config('media-library.temporary_url_default_lifetime', 5);

        if ($media->disk === 's3') {
            return $media->getTemporaryUrl(now()->addMinutes($lifetime));
        }

        return $media->getUrl();
    }
}
