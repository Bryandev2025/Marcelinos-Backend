<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Image extends Model
{
    protected $fillable = ['url', 'type', 'imageable_id', 'imageable_type'];

        // This allows the image to belong to any other model
        public function imageable(): MorphTo
        {
            return $this->morphTo();
        }
}
