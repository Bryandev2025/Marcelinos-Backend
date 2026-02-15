<?php

namespace App\Broadcasting;

/**
 * Centralized broadcast channel name constants.
 * Single source of truth for channel names used by events and frontend.
 */
final class BroadcastChannelNames
{
    /** Private channel for a single booking (by reference). */
    public static function booking(string $reference): string
    {
        return 'booking.' . $reference;
    }

    /** Private channel for admin/staff dashboard updates. */
    public static function adminDashboard(): string
    {
        return 'admin.dashboard';
    }

    /** Public channel for general booking lifecycle (optional). */
    public static function bookingsPublic(): string
    {
        return 'bookings';
    }
}
