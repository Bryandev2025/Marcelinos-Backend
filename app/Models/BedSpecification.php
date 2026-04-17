<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class BedSpecification extends Model implements HasMedia
{
    use SoftDeletes, InteractsWithMedia;

    protected $fillable = ['specification'];

    protected $appends = [
        'featured_image_url',
        'gallery_urls',
    ];

    public function rooms()
    {
        return $this->belongsToMany(Room::class, 'bed_specification_room');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('featured')
            ->singleFile();

        $this->addMediaCollection('gallery');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('card')
            ->width(600)
            ->optimize()
            ->nonQueued();
    }

    public function getFeaturedImageUrlAttribute(): ?string
    {
        $media = $this->getFirstMedia('featured');

        return $media ? $this->resolveMediaUrl($media, 'card') : null;
    }

    public function getGalleryUrlsAttribute(): array
    {
        return $this->getMedia('gallery')
            ->map(fn (Media $media) => $this->resolveMediaUrl($media, 'card'))
            ->values()
            ->all();
    }

    private function resolveMediaUrl(Media $media, string $conversion = 'card'): string
    {
        $useConversion = $media->hasGeneratedConversion($conversion) ? $conversion : '';
        $lifetime = (int) config('media-library.temporary_url_default_lifetime', 60);

        if ($media->disk === 's3') {
            return $media->getTemporaryUrl(now()->addMinutes($lifetime), $useConversion);
        }

        return $media->getUrl($useConversion);
    }
}
