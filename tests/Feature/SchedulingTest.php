<?php

namespace Tests\Feature;

use Tests\TestCase;

class SchedulingTest extends TestCase
{
    public function test_schedule_list_includes_all_booking_commands(): void
    {
        $this->artisan('schedule:list')
            ->assertSuccessful()
            ->expectsOutputToContain('bookings:send-reminders')
            ->expectsOutputToContain('bookings:cancel-unpaid')
            ->expectsOutputToContain('bookings:prune-pending-verification')
            ->doesntExpectOutputToContain('bookings:complete-checkouts')
            ->doesntExpectOutputToContain('bookings:activate-checkins');
    }
}
