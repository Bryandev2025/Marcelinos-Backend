<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Mail\TestimonialFeedbackEmail;
use App\Models\Booking;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendTestimonialFeedback extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'testimonials:send-feedback
                            {--date= : The date (Y-m-d) to consider as "today"; defaults to today}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send testimonial feedback email to guests 1 day after their booking is completed';

    /**
     * Execute the console command.
     * Sends one email per completed booking whose check-out was at least 1 day ago.
     * Each email contains a signed, expiring link to the testimonial form.
     */
    public function handle(): int
    {
        // Invoked after each complete-checkouts batch (see routes/console.php); --date treats that as "as of".
        // We want a true 24-hour delay after guests become eligible, so we use `subDay()` (no endOfDay boundary).
        $timezone = config('app.timezone', 'Asia/Manila');

        $asOf = $this->option('date')
            ? Carbon::parse($this->option('date'), $timezone)->setTime(12, 0, 0)
            : Carbon::now($timezone);

        $cutoff = $asOf->copy()->subDay();

        $query = Booking::query()
            ->where('status', Booking::STATUS_COMPLETED)
            ->where('check_out', '<=', $cutoff)
            ->whereNull('testimonial_feedback_sent_at')
            ->with('guest')
            ->orderBy('id');

        $sent = 0;
        $failed = 0;

        $query->chunkById(100, function ($bookings) use (&$sent, &$failed) {
            $ids = $bookings->pluck('id')->all();

            // Only send testimonial feedback when the booking transitioned from
            // `occupied` -> `completed`. We use the latest audit record to enforce
            // that rule for admin/staff-triggered status changes.
            //
            // When there is no audit record (e.g. console-driven completion), we
            // allow sending because the completion flow only upgrades from
            // `occupied` -> `completed`.
            $statusChangedLogs = ActivityLog::query()
                ->where('category', 'booking')
                ->where('event', 'booking.status_changed')
                ->where('subject_type', Booking::class)
                ->whereIn('subject_id', $ids)
                ->orderBy('created_at', 'desc')
                ->get();

            $latestStatusChangedLogByBookingId = [];
            foreach ($statusChangedLogs as $log) {
                $bookingId = (int) $log->subject_id;
                if (! isset($latestStatusChangedLogByBookingId[$bookingId])) {
                    $latestStatusChangedLogByBookingId[$bookingId] = $log;
                }
            }

            foreach ($bookings as $booking) {
                $guest = $booking->guest;
                $email = $guest?->email;
                if (! $email) {
                    continue;
                }

                $latestLog = $latestStatusChangedLogByBookingId[(int) $booking->id] ?? null;
                if ($latestLog) {
                    $oldStatus = data_get($latestLog->meta, 'old_status');
                    $newStatus = data_get($latestLog->meta, 'new_status');

                    if ($oldStatus !== Booking::STATUS_OCCUPIED || $newStatus !== Booking::STATUS_COMPLETED) {
                        continue;
                    }
                }

                try {
                    Mail::to($email)->send(new TestimonialFeedbackEmail($booking));
                } catch (\Throwable $e) {
                    $failed++;
                    Log::error('Failed sending testimonial feedback', [
                        'booking_id' => $booking->id,
                        'reference_number' => $booking->reference_number,
                        'guest_email' => $email,
                        'error' => $e->getMessage(),
                    ]);
                    continue;
                }

                // Avoid triggering model "updated" broadcasts/auditing noise for console sends.
                $booking->updateQuietly(['testimonial_feedback_sent_at' => now()]);
                $sent++;
                $this->info("Sent testimonial feedback to {$email} for booking {$booking->reference_number}.");
            }
        });

        if ($sent === 0) {
            $this->comment($failed > 0 ? 'No testimonial emails sent (all eligible sends failed).' : 'No completed bookings eligible for testimonial email.');
        } else {
            $this->info("Sent {$sent} testimonial feedback emails." . ($failed > 0 ? " ({$failed} failed)" : ''));
        }

        return self::SUCCESS;
    }
}
