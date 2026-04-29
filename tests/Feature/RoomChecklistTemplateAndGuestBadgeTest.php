<?php

namespace Tests\Feature;

use App\Filament\Resources\Guests\GuestResource;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\RoomChecklist;
use App\Models\RoomChecklistItem;
use App\Models\RoomChecklistTemplate;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RoomChecklistTemplateAndGuestBadgeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_deleting_template_does_not_delete_historical_checklist_items(): void
    {
        $template = RoomChecklistTemplate::query()->create([
            'label' => 'Glass door',
            'default_charge' => '450.00',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $booking = $this->createBookingWithGuest();
        $checklist = RoomChecklist::query()->create([
            'booking_id' => (int) $booking->id,
            'room_id' => null,
            'generated_at' => now(),
        ]);
        $item = RoomChecklistItem::query()->create([
            'room_checklist_id' => (int) $checklist->id,
            'label' => (string) $template->label,
            'charge' => (string) $template->default_charge,
            'status' => RoomChecklistItem::STATUS_BROKEN,
            'notes' => 'Cracked',
            'sort_order' => 1,
        ]);

        $template->delete();

        $this->assertSoftDeleted('room_checklist_item_templates', ['id' => (int) $template->id]);
        $this->assertDatabaseHas('room_checklist_items', ['id' => (int) $item->id]);
    }

    public function test_guest_navigation_badge_counts_distinct_guests_with_pending_settlement(): void
    {
        $baseline = (int) (GuestResource::getNavigationBadge() ?? '0');

        $guestWithPending = $this->createGuest();
        $anotherPendingGuest = $this->createGuest();
        $guestWithoutPending = $this->createGuest();

        $this->createBookingWithGuest($guestWithPending, [
            'damage_settlement_status' => Booking::DAMAGE_SETTLEMENT_STATUS_PENDING,
        ]);
        $this->createBookingWithGuest($guestWithPending, [
            'damage_settlement_status' => Booking::DAMAGE_SETTLEMENT_STATUS_PENDING,
        ]);
        $this->createBookingWithGuest($anotherPendingGuest, [
            'damage_settlement_status' => Booking::DAMAGE_SETTLEMENT_STATUS_PENDING,
        ]);
        $this->createBookingWithGuest($guestWithoutPending, [
            'damage_settlement_status' => Booking::DAMAGE_SETTLEMENT_STATUS_NONE,
        ]);

        $this->assertSame((string) ($baseline + 2), GuestResource::getNavigationBadge());

        $rows = GuestResource::getEloquentQuery()
            ->whereIn('id', [
                (int) $guestWithPending->id,
                (int) $anotherPendingGuest->id,
                (int) $guestWithoutPending->id,
            ])
            ->get()
            ->keyBy('id');

        $this->assertSame(2, (int) $rows[(int) $guestWithPending->id]->pending_settlement_bookings_count);
        $this->assertSame(1, (int) $rows[(int) $anotherPendingGuest->id]->pending_settlement_bookings_count);
        $this->assertSame(0, (int) $rows[(int) $guestWithoutPending->id]->pending_settlement_bookings_count);
    }

    private function createBookingWithGuest(?Guest $guest = null, array $overrides = []): Booking
    {
        $guest = $guest ?: $this->createGuest();

        return Booking::query()->create(array_merge([
            'guest_id' => (int) $guest->id,
            'check_in' => now()->copy()->subDay(),
            'check_out' => now()->copy()->addDay(),
            'no_of_days' => 1,
            'total_price' => 1000,
            'booking_status' => Booking::BOOKING_STATUS_RESERVED,
            'payment_status' => Booking::PAYMENT_STATUS_PAID,
            'payment_method' => 'cash',
            'damage_settlement_status' => Booking::DAMAGE_SETTLEMENT_STATUS_NONE,
        ], $overrides));
    }

    private function createGuest(): Guest
    {
        return Guest::query()->create([
            'first_name' => 'Guest',
            'middle_name' => null,
            'last_name' => (string) random_int(1000, 9999),
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
    }
}

