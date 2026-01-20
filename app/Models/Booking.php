<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Booking extends Model
{
     use HasFactory;

    protected $fillable = [
    'guest_id',
    'room_id',
    'reference_id',
    'check_in',
    'check_out',
    'total_price',
    'status',
];

    public function guest()
    {
        return $this->belongsTo(Guest::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }
}
