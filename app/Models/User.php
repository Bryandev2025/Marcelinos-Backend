<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Exception;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }   

    protected static function booted()
    {
        static::updating(function ($user) {
            // Check if it WAS an admin before this change
            if ($user->getOriginal('role') === 'admin' && $user->role !== 'admin') {
                throw new Exception("Security Breach: You cannot change the Admin role!");
            }
        });

        static::deleting(function ($user) {
            if ($user->getOriginal('role') === 'admin') {
                throw new Exception("Security Breach: You cannot delete the Admin!");
            }
        });
    }

    public function canAccessPanel(\Filament\Panel $panel): bool
{
    if (! $this->is_active) {
        return false; // cannot access any panel if inactive
    }

    if ($panel->getId() === 'admin') {
        return $this->role === 'admin';
    }

    if ($panel->getId() === 'staff') {
        return $this->role === 'staff';
    }

    return true;
}
}
