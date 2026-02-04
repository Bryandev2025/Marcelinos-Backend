<?php

namespace App\MediaLibrary\PathGenerators;

use App\Models\Room;
use App\Models\Venue;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

class ModelTypePathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        return $this->getBasePath($media) . '/' . $media->uuid . '/';
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->getPath($media) . 'conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->getPath($media) . 'responsive/';
    }

    private function getBasePath(Media $media): string
    {
        return match ($media->model_type) {
            Room::class => 'rooms',
            Venue::class => 'venues',
            default => 'media',
        };
    }
}
