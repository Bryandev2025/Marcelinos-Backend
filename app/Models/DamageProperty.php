<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DamageProperty extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'default_charge',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function rooms()
    {
        return $this->belongsToMany(Room::class, 'damage_property_room')->withTimestamps();
    }
}

