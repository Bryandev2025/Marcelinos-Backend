<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\BookingRoomLine;
use App\Models\Room;
use App\Models\Venue;
use App\Support\BookingPricing;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BookingOccupiedAssignmentTest extends TestCase
{
    #[Test]
    public function assert_fails_when_venue_event_type_set_but_no_venues_attached(): void
    {
        $booking = new Booking([
            'venue_event_type' => BookingPricing::VENUE_EVENT_WEDDING,
        ]);
        $booking->setRelation('roomLines', collect());
        $booking->setRelation('venues', collect());

        $this->expectException(ValidationException::class);
        $booking->assertAssignmentsSatisfiedForOccupied();
    }

    #[Test]
    public function assert_passes_when_no_room_lines_and_no_venue_event_type(): void
    {
        $booking = new Booking([
            'venue_event_type' => null,
        ]);
        $booking->setRelation('roomLines', collect());
        $booking->setRelation('venues', collect());

        $booking->assertAssignmentsSatisfiedForOccupied();
        $this->assertTrue($booking->assignmentsSatisfiedForOccupied());
    }

    #[Test]
    public function expects_room_assignments_when_room_lines_are_present_in_memory(): void
    {
        $line = new BookingRoomLine([
            'room_type' => Room::TYPE_STANDARD,
            'inventory_group_key' => 'spec:1 Double Bed',
            'quantity' => 1,
        ]);
        $booking = new Booking;
        $booking->setRelation('roomLines', collect([$line]));

        $this->assertTrue($booking->expectsRoomAssignments());
    }

    #[Test]
    public function assignments_satisfied_for_occupied_returns_false_when_invalid(): void
    {
        $booking = new Booking([
            'venue_event_type' => BookingPricing::VENUE_EVENT_WEDDING,
        ]);
        $booking->setRelation('roomLines', collect());
        $booking->setRelation('venues', collect());

        $this->assertFalse($booking->assignmentsSatisfiedForOccupied());
    }

    #[Test]
    public function assert_passes_when_venue_package_has_at_least_one_venue_and_no_room_lines(): void
    {
        $venue = new Venue;
        $venue->id = 1;

        $booking = new Booking([
            'venue_event_type' => BookingPricing::VENUE_EVENT_WEDDING,
        ]);
        $booking->setRelation('roomLines', collect());
        $booking->setRelation('venues', collect([$venue]));

        $booking->assertAssignmentsSatisfiedForOccupied();
        $this->assertTrue($booking->assignmentsSatisfiedForOccupied());
    }
}
