<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $guarded = [];

    public function guest() { return $this->belongsTo(Guest::class); }
    public function room() { return $this->belongsTo(Room::class); }
    public function venue() { return $this->belongsTo(Venue::class); }
}
