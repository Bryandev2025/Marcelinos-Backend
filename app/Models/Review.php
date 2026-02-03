<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'guest_id',
        'booking_id',
        'reviewable_type',
        'reviewable_id',
        'is_site_review',
        'rating',
        'title',
        'comment',
        'is_approved',
        'reviewed_at',
    ];

    protected $casts = [
        'is_site_review' => 'boolean',
        'is_approved' => 'boolean',
        'reviewed_at' => 'datetime',
        'rating' => 'integer',
    ];

    /* ================= RELATIONSHIPS ================= */

    public function guest()
    {
        return $this->belongsTo(Guest::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function reviewable()
    {
        return $this->morphTo();
    }

    /* ================= SCOPES ================= */

    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    public function scopeSiteReviews($query)
    {
        return $query->where('is_site_review', true);
    }
}
