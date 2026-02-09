<?php

namespace App\Console\Commands;

use App\Mail\TestimonialFeedbackEmail;
use App\Models\Booking;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
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
        $today = $this->option('date')
            ? Carbon::parse($this->option('date'))->toDateString()
            : Carbon::today()->toDateString();

        $cutoff = Carbon::parse($today)->subDay()->endOfDay();

        $bookings = Booking::query()
            ->where('status', Booking::STATUS_COMPLETED)
            ->where('check_out', '<=', $cutoff)
            ->whereNull('testimonial_feedback_sent_at')
            ->with('guest')
            ->get();

        $sent = 0;
        foreach ($bookings as $booking) {
            $guest = $booking->guest;
            if (!$guest || !$guest->email) {
                continue;
            }
            Mail::to($guest->email)->send(new TestimonialFeedbackEmail($booking));
            $booking->update(['testimonial_feedback_sent_at' => now()]);
            $sent++;
            $this->info("Sent testimonial feedback to {$guest->email} for booking {$booking->reference_number}.");
        }

        if ($sent === 0) {
            $this->comment('No completed bookings eligible for testimonial email.');
        } else {
            $this->info("Sent {$sent} testimonial feedback emails.");
        }

        return self::SUCCESS;
    }
}
