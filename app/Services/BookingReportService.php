<?php


namespace App\Services;

use App\Models\Booking;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BookingReportService
{
    public static function generate(array $data)
    {
        $period = $data['period'] ?? 'daily';
        $format = $data['format'] ?? 'csv';

        // Get date range based on period
        $dateRange = self::getDateRange($period);

        $bookings = Booking::with(['guest', 'room', 'venue'])
            ->whereBetween('check_in', [
                $dateRange['start'],
                $dateRange['end'],
            ])
            ->get();

        if ($format === 'csv') {
            return self::exportCsv($bookings, $period);
        }

        return self::exportHtml($bookings, $period);
    }

    protected static function getDateRange(string $period): array
    {
        $now = Carbon::now();

        return match ($period) {
            'daily' => [
                'start' => $now->clone()->startOfDay(),
                'end' => $now->clone()->endOfDay(),
            ],
            'weekly' => [
                'start' => $now->clone()->startOfWeek(),
                'end' => $now->clone()->endOfWeek(),
            ],
            'monthly' => [
                'start' => $now->clone()->startOfMonth(),
                'end' => $now->clone()->endOfMonth(),
            ],
            'yearly' => [
                'start' => $now->clone()->startOfYear(),
                'end' => $now->clone()->endOfYear(),
            ],
            default => [
                'start' => $now->clone()->startOfDay(),
                'end' => $now->clone()->endOfDay(),
            ],
        };
    }

    protected static function exportCsv($bookings, $period): StreamedResponse
    {
        return response()->streamDownload(function () use ($bookings, $period) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Guest',
                'Room/Venue',
                'Check-in',
                'Check-out',
                'Total Price',
                'Status',
            ]);

            foreach ($bookings as $booking) {
                fputcsv($handle, [
                    $booking->guest->first_name . ' ' . $booking->guest->last_name,
                    $booking->room?->name ?? $booking->venue?->name,
                    $booking->check_in,
                    $booking->check_out,
                    $booking->total_price,
                    $booking->status,
                ]);
            }

            fclose($handle);
        }, 'booking-report-' . now()->format('Y-m-d') . '.csv');
    }

    protected static function exportHtml($bookings, $period)
    {
        $html = view('reports.booking', [
            'bookings' => $bookings,
            'period' => $period,
            'generatedAt' => now(),
        ])->render();

        return response($html)
            ->header('Content-Type', 'text/html; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="booking-report-' . now()->format('Y-m-d') . '.html"');
    }
}