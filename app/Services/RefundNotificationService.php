<?php

namespace App\Services;

use App\Filament\Resources\Bookings\BookingResource;
use App\Mail\RefundActionRequiredStaffMail;
use App\Mail\RefundCompletedGuestMail;
use App\Mail\RefundEligibleGuestMail;
use App\Models\Booking;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class RefundNotificationService
{
    public function handleRefundPipelinePaymentStatusTransition(Booking $booking): void
    {
        if (! $booking->wasChanged('payment_status')) {
            return;
        }

        $stayStatus = (string) $booking->booking_status;
        if (! in_array($stayStatus, [
            Booking::BOOKING_STATUS_RESCHEDULED,
            Booking::BOOKING_STATUS_CANCELLED,
        ], true)) {
            return;
        }

        $newPaymentStatus = (string) $booking->payment_status;

        if ($newPaymentStatus === Booking::PAYMENT_STATUS_REFUND_PENDING) {
            $this->sendGuestRefundEligibleNotice($booking);
            $this->sendStaffRefundAlert($booking);

            return;
        }

        if ($newPaymentStatus === Booking::PAYMENT_STATUS_REFUNDED) {
            $this->sendGuestRefundCompleted($booking);
        }
    }

    private function sendGuestRefundEligibleNotice(Booking $booking): void
    {
        if (! (bool) config('notifications.refund_guest_eligible_enabled', false)) {
            return;
        }

        if ($booking->refund_guest_notice_sent_at !== null) {
            return;
        }

        $booking->loadMissing('guest');
        $email = trim((string) ($booking->guest?->email ?? ''));
        if ($email === '') {
            return;
        }

        try {
            $billingToken = $booking->generateBillingAccessToken();
            Mail::to($email)->send(new RefundEligibleGuestMail($booking, $billingToken));
            $booking->updateQuietly(['refund_guest_notice_sent_at' => now()]);
        } catch (\Throwable $exception) {
            $this->logFailure($booking, 'refund_guest_eligible', [$email], $exception);
        }
    }

    private function sendGuestRefundCompleted(Booking $booking): void
    {
        if (! (bool) config('notifications.refund_guest_completed_enabled', true)) {
            return;
        }

        if ($booking->refund_guest_confirmation_sent_at !== null) {
            return;
        }

        $booking->loadMissing('guest');
        $email = trim((string) ($booking->guest?->email ?? ''));
        if ($email === '') {
            return;
        }

        try {
            $billingToken = $booking->generateBillingAccessToken();
            Mail::to($email)->send(new RefundCompletedGuestMail($booking, $billingToken));
            $booking->updateQuietly(['refund_guest_confirmation_sent_at' => now()]);
        } catch (\Throwable $exception) {
            $this->logFailure($booking, 'refund_guest_completed', [$email], $exception);
        }
    }

    private function sendStaffRefundAlert(Booking $booking): void
    {
        if (! (bool) config('notifications.refund_staff_alert_enabled', true)) {
            return;
        }

        if ($booking->refund_alert_sent_at !== null) {
            return;
        }

        $recipients = config('notifications.refund_staff_recipients', []);
        if (! is_array($recipients) || $recipients === []) {
            return;
        }

        $booking->loadMissing(['guest', 'payments']);

        try {
            Mail::to($recipients)->send(
                new RefundActionRequiredStaffMail($booking, $this->bookingAdminUrl($booking))
            );
            $booking->updateQuietly(['refund_alert_sent_at' => now()]);
        } catch (\Throwable $exception) {
            $this->logFailure($booking, 'refund_staff_alert', $recipients, $exception);
        }
    }

    private function bookingAdminUrl(Booking $booking): string
    {
        $url = BookingResource::getUrl('view', ['record' => $booking]);

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return rtrim((string) config('app.url'), '/').'/'.ltrim($url, '/');
    }

    private function logFailure(Booking $booking, string $notificationType, array $recipients, \Throwable $exception): void
    {
        Log::warning('Refund notification send failed', [
            'booking_id' => $booking->id,
            'reference_number' => $booking->reference_number,
            'notification_type' => $notificationType,
            'recipients' => $recipients,
            'error' => $exception->getMessage(),
        ]);
    }
}
