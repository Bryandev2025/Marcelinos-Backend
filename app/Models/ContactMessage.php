<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactMessage extends Model
{
    protected $fillable = [
        'contact_us_id',
        'sender_type',
        'sender_name',
        'sender_email',
        'body',
        'sent_via',
    ];

    protected function casts(): array
    {
        return [
            'sender_type' => 'string',
            'sent_via' => 'string',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ContactUs::class, 'contact_us_id');
    }
}
