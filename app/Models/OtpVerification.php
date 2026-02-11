<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class OtpVerification extends Model
{
    protected $fillable = [
        'email',
        'code',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public static function generateCode(): string
    {
        return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    public static function createForEmail(string $email): self
    {
        // Delete any existing for this email
        self::where('email', $email)->delete();

        return self::create([
            'email' => $email,
            'code' => self::generateCode(),
            'expires_at' => Carbon::now()->addMinutes(10),
        ]);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function verify(string $code): bool
    {
        return $this->code === $code && !$this->isExpired();
    }
}
