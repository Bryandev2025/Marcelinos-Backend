<?php

namespace App\Listeners;

use App\Filament\Resources\Bookings\Pages\CreateBooking;
use App\Models\Booking;
use App\Models\Payment;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Model;

/**
 * Filament dispatches {@see \Filament\Resources\Events\RecordCreated} as a class name with
 * payload [record, data, page] (see CreateRecord), so this listener uses a matching handle signature.
 */
class RecordBookingWizardInitialPayment
{
    public function handle(Model $record, array $data, Page $page): void
    {
        if (! $page instanceof CreateBooking) {
            return;
        }

        if (! $record instanceof Booking) {
            return;
        }

        $pending = $page->getWizardPendingPaymentAmount();
        if ($pending === null || $pending <= 0) {
            return;
        }

        $totalInt = (int) round((float) $record->total_price);
        $paid = $totalInt > 0 ? min($pending, $totalInt) : $pending;

        Payment::query()->create([
            'booking_id' => $record->id,
            'total_amount' => $totalInt,
            'partial_amount' => $paid,
            'is_fullypaid' => $totalInt > 0 && $paid >= $totalInt,
        ]);

        if (in_array((string) $record->stay_status, [Booking::STAY_STATUS_CANCELLED, Booking::STAY_STATUS_COMPLETED], true)) {
            return;
        }

        if ($totalInt > 0 && $paid >= $totalInt) {
            $record->update(['payment_status' => Booking::PAYMENT_STATUS_PAID]);

            return;
        }

        if ($paid > 0 && $totalInt > 0 && $paid < $totalInt) {
            $record->update(['payment_status' => Booking::PAYMENT_STATUS_PARTIAL]);
        }
    }
}
