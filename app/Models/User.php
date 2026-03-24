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
        'permissions',
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
            'permissions' => 'array',
        ];
    }   

    /**
     * Central list used by admin UI when assigning staff permissions.
     *
     * @return array<string, string>
     */
    public static function staffPrivilegeOptions(): array
    {
        return [
            'manage_rooms' => 'Manage rooms',
            'manage_venues' => 'Manage venues',
            'manage_bookings' => 'Manage bookings',
            'manage_guests' => 'Manage guests',
            'manage_amenities' => 'Manage amenities',
            'manage_galleries' => 'Manage galleries',
            'manage_reviews' => 'Manage reviews',
            'manage_blocked_dates' => 'Manage blocked dates',
            'manage_contact_messages' => 'Manage contact messages',
            'manage_activity_logs' => 'Manage activity logs',
        ];
    }

    public function hasPrivilege(string $privilege): bool
    {
        if ($this->role === 'admin') {
            return true;
        }

        if ($this->role !== 'staff') {
            return false;
        }

        $permissions = $this->permissions ?? [];

        if (! is_array($permissions)) {
            return false;
        }

        return in_array($privilege, $permissions, true);
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

    public function canAccessPanel(Panel $panel): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $role = strtolower(trim((string) ($this->role ?? '')));

        return match ($panel->getId()) {
            'admin' => in_array($role, ['admin', 'staff'], true),
            'staff' => in_array($role, ['admin', 'staff'], true), // admins can access staff panel too
            default => false,
        };
    }
}
