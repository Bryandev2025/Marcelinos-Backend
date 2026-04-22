<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContactUs extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'full_name',
        'email',
        'phone',
        'subject',
        'message',
        'status',
        'replied_at',
        'conversation_token',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'string',
            'replied_at' => 'datetime',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ContactMessage::class, 'contact_us_id');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(ContactMessage::class, 'contact_us_id')->latestOfMany();
    }
}
