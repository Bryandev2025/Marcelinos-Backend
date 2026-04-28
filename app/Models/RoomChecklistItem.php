<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomChecklistItem extends Model
{
    const STATUS_GOOD = 'good';
    const STATUS_BROKEN = 'broken';
    const STATUS_MISSING = 'missing';

    protected $fillable = [
        'room_checklist_id',
        'label',
        'charge',
        'status',
        'notes',
        'sort_order',
    ];

    public function roomChecklist(): BelongsTo
    {
        return $this->belongsTo(RoomChecklist::class);
    }
}

