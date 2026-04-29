<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Guest;
use App\Models\Room;
use App\Models\RoomChecklist;
use App\Models\RoomChecklistItem;
use App\Models\RoomChecklistTemplate;
use App\Models\User;
use App\Support\BookingLifecycleActions;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BookingManualLifecycleDamageSettlementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_manual_checkin_and_checkout_are_logged_with_actor(): void
    {
        $staff = User::factory()->create([
            'role' => 'staff',
            'is_active' => true,
        ]);
        $this->actingAs($staff);

        Carbon::setTestNow(Carbon::parse('2026-04-29 09:30:00', Booking::timezoneManila()));

        try {
            $booking = $this->createBooking([
                'booking_status' => Booking::BOOKING_STATUS_RESERVED,
                'payment_status' => Booking::PAYMENT_STATUS_PAID,
                'check_in' => Carbon::parse('2026-04-29 14:00:00', Booking::timezoneManila()),
                'check_out' => Carbon::parse('2026-04-29 18:00:00', Booking::timezoneManila()),
            ]);

            BookingLifecycleActions::checkIn($booking);
            BookingLifecycleActions::complete($booking->fresh());

            $booking->refresh();
            $this->assertSame(Booking::BOOKING_STATUS_COMPLETED, (string) $booking->booking_status);

            $this->assertDatabaseHas('activity_logs', [
                'event' => 'checkin_triggered',
                'user_id' => (int) $staff->id,
                'subject_type' => Booking::class,
                'subject_id' => (int) $booking->id,
            ]);

            $this->assertDatabaseHas('activity_logs', [
                'event' => 'checkout_triggered',
                'user_id' => (int) $staff->id,
                'subject_type' => Booking::class,
                'subject_id' => (int) $booking->id,
            ]);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_checkout_sets_pending_settlement_when_any_checklist_item_is_broken_or_missing(): void
    {
        $staff = User::factory()->create([
            'role' => 'staff',
            'is_active' => true,
        ]);
        $this->actingAs($staff);

        Carbon::setTestNow(Carbon::parse('2026-04-29 09:30:00', Booking::timezoneManila()));

        try {
            $booking = $this->createBooking([
                'booking_status' => Booking::BOOKING_STATUS_OCCUPIED,
                'payment_status' => Booking::PAYMENT_STATUS_PAID,
                'check_in' => Carbon::parse('2026-04-28 14:00:00', Booking::timezoneManila()),
                'check_out' => Carbon::parse('2026-04-29 10:00:00', Booking::timezoneManila()),
            ]);

            $checklist = RoomChecklist::query()->create([
                'booking_id' => (int) $booking->id,
                'room_id' => null,
                'generated_at' => now(),
            ]);

            RoomChecklistItem::query()->create([
                'room_checklist_id' => (int) $checklist->id,
                'label' => 'TV remote',
                'charge' => '100.00',
                'status' => RoomChecklistItem::STATUS_MISSING,
                'notes' => 'Missing on checkout',
                'sort_order' => 0,
            ]);

            BookingLifecycleActions::complete($booking);

            $booking->refresh();
            $this->assertTrue((bool) $booking->has_damage_claim);
            $this->assertSame(Booking::DAMAGE_SETTLEMENT_STATUS_PENDING, (string) $booking->damage_settlement_status);
            $this->assertSame((int) $staff->id, (int) $booking->damage_settlement_marked_by);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_completed_booking_checklist_update_recalculates_damage_settlement_status(): void
    {
        $staff = User::factory()->create([
            'role' => 'staff',
            'is_active' => true,
        ]);
        $this->actingAs($staff);

        $booking = $this->createBooking([
            'booking_status' => Booking::BOOKING_STATUS_COMPLETED,
            'payment_status' => Booking::PAYMENT_STATUS_PAID,
            'has_damage_claim' => false,
            'damage_settlement_status' => Booking::DAMAGE_SETTLEMENT_STATUS_NONE,
        ]);

        $checklist = RoomChecklist::query()->create([
            'booking_id' => (int) $booking->id,
            'room_id' => null,
            'generated_at' => now(),
        ]);

        $item = RoomChecklistItem::query()->create([
            'room_checklist_id' => (int) $checklist->id,
            'label' => 'Bath towel',
            'charge' => '250.00',
            'status' => RoomChecklistItem::STATUS_GOOD,
            'notes' => null,
            'sort_order' => 0,
        ]);

        $booking->refresh();
        $this->assertSame(Booking::DAMAGE_SETTLEMENT_STATUS_NONE, (string) $booking->damage_settlement_status);

        $item->update([
            'status' => RoomChecklistItem::STATUS_BROKEN,
            'notes' => 'Torn fabric',
        ]);

        $booking->refresh();
        $this->assertTrue((bool) $booking->has_damage_claim);
        $this->assertSame(Booking::DAMAGE_SETTLEMENT_STATUS_PENDING, (string) $booking->damage_settlement_status);
    }

    public function test_checkout_checklist_uses_active_templates_for_attached_room(): void
    {
        RoomChecklistTemplate::query()->delete();
        RoomChecklistTemplate::query()->create([
            'label' => 'Wall paint',
            'default_charge' => '350.00',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        RoomChecklistTemplate::query()->create([
            'label' => 'Aircon filter',
            'default_charge' => null,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $booking = $this->createBooking();
        $room = $this->createRoom();
        $booking->rooms()->attach($room->id);

        $rows = BookingLifecycleActions::checkoutChecklistFormItems($booking->fresh());

        $this->assertCount(2, $rows);
        $this->assertSame('Wall paint', $rows[0]['label']);
        $this->assertSame('350.00', $rows[0]['charge']);
        $this->assertSame('Aircon filter', $rows[1]['label']);
        $this->assertSame('', $rows[1]['charge']);

        $checklist = RoomChecklist::query()
            ->where('booking_id', (int) $booking->id)
            ->where('room_id', (int) $room->id)
            ->first();

        $this->assertNotNull($checklist);
        $this->assertSame(2, $checklist->items()->count());
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createBooking(array $overrides = []): Booking
    {
        $guest = Guest::query()->create([
            'first_name' => 'Test',
            'middle_name' => null,
            'last_name' => 'Guest',
            'email' => 'guest'.strval(random_int(1000, 9999)).'@example.com',
            'contact_num' => '09123456789',
            'gender' => Guest::GENDER_MALE,
            'is_international' => false,
            'country' => 'Philippines',
            'region' => 'Region 1',
            'province' => 'Ilocos Norte',
            'municipality' => 'Laoag',
            'barangay' => 'Barangay 1',
        ]);

        $base = [
            'guest_id' => (int) $guest->id,
            'check_in' => now()->copy()->subDay(),
            'check_out' => now()->copy(),
            'no_of_days' => 1,
            'total_price' => 1000,
            'booking_status' => Booking::BOOKING_STATUS_RESERVED,
            'payment_status' => Booking::PAYMENT_STATUS_PAID,
            'payment_method' => 'cash',
        ];

        return Booking::query()->create(array_merge($base, $overrides));
    }

    private function createRoom(array $overrides = []): Room
    {
        return Room::query()->create(array_merge([
            'name' => 'Room '.strval(random_int(1000, 9999)),
            'capacity' => 2,
            'type' => Room::TYPE_STANDARD,
            'price' => 2500,
            'status' => Room::STATUS_AVAILABLE,
        ], $overrides));
    }
}
