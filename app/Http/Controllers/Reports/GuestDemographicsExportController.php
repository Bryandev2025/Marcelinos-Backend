<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GuestDemographicsExportController extends Controller
{
    public function pdf(Request $request)
    {
        $type = (string) $request->query('type', 'overview_selected');
        $period = $request->query('period');
        $period = $period === 'null' ? null : $period;

        $now = Carbon::now();

        [$start, $end, $title, $subtitle, $kind] = match ($type) {
            'unpaid' => $this->resolvePresetReport($period, 'unpaid', 'Guest Address Report: Unpaid Bookings (Pending)', $now),
            'successful' => $this->resolvePresetReport($period, 'successful', 'Guest Address Report: Successful Bookings (Paid/Confirmed)', $now),
            'overview_selected' => $this->resolveOverviewReport($request, 'successful', $now),
            default => $this->resolveOverviewReport($request, 'successful', $now),
        };

        $rows = $this->getHierarchicalData($kind, $start, $end);
        $localData = $rows->where('is_international', false)->values();
        $foreignData = $rows->where('is_international', true)->values();

        $logoPath = public_path('brand-logo.webp');
        $logoSrc = null;
        if (is_file($logoPath)) {
            $logoSrc = 'data:image/webp;base64,' . base64_encode((string) file_get_contents($logoPath));
        }

        $pdf = Pdf::loadView('reports.guest-demographics-pdf', [
            'title' => $title,
            'subtitle' => $subtitle,
            'localData' => $localData,
            'foreignData' => $foreignData,
            'logoSrc' => $logoSrc,
        ])->setPaper('a4', 'portrait');

        $safePeriod = $period ? Str::slug((string) $period) : 'selected';
        $filename = 'guest-address-' . Str::slug($type) . '-' . $safePeriod . '-' . $now->format('Ymd-His') . '.pdf';

        return $pdf->download($filename);
    }

    private function resolvePresetReport($period, string $kind, string $baseTitle, Carbon $now): array
    {
        $period = (string) ($period ?: 'today');

        [$start, $end, $label] = match ($period) {
            'today' => [Carbon::today(), Carbon::today(), 'Today'],
            'next_7_days' => [Carbon::tomorrow(), Carbon::today()->addDays(7), 'Next 7 Days'],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth(), 'This Month'],
            'next_month' => [$now->copy()->addMonth()->startOfMonth(), $now->copy()->addMonth()->endOfMonth(), 'Next Month'],
            'all' => [$now->copy()->subYears(10), $now->copy()->addYears(10), 'All Time'],
            default => [Carbon::today(), Carbon::today(), 'Today'],
        };

        $subtitle = 'Period: ' . $label . '  ·  Generated: ' . $now->format('F j, Y, g:i a');

        return [$start, $end, $baseTitle, $subtitle, $kind];
    }

    private function resolveOverviewReport(Request $request, string $kind, Carbon $now): array
    {
        $start = $request->query('start') ? Carbon::parse((string) $request->query('start')) : $now->copy()->startOfMonth();
        $end = $request->query('end') ? Carbon::parse((string) $request->query('end')) : $now->copy()->endOfMonth();

        if ($end->lessThan($start)) {
            [$start, $end] = [$end, $start];
        }

        $title = 'Comprehensive Guest Address Report';
        $subtitle = $this->overviewLabel($start, $end) . '  ·  Generated: ' . $now->format('F j, Y, g:i a');

        return [$start, $end, $title, $subtitle, $kind];
    }

    private function overviewLabel(Carbon $start, Carbon $end): string
    {
        if ($start->isSameDay($start->copy()->startOfMonth()) && $end->isSameDay($start->copy()->endOfMonth())) {
            return 'Month: ' . $start->format('F Y');
        }

        if ($start->isSameDay($start->copy()->startOfYear()) && $end->isSameDay($start->copy()->endOfYear())) {
            return 'Year: ' . $start->format('Y');
        }

        return 'Dates: ' . $start->toDateString() . ' → ' . $end->toDateString();
    }

    private function getHierarchicalData(string $kind, Carbon $startDate, Carbon $endDate)
    {
        return Booking::select(
            'guests.is_international',
            'guests.country',
            'guests.region',
            'guests.province',
            'guests.municipality',
            'guests.barangay',
            DB::raw('count(*) as total')
        )
            ->join('guests', 'bookings.guest_id', '=', 'guests.id')
            ->when($kind === 'unpaid', function ($query): void {
                $query->whereIn('bookings.payment_status', [
                    Booking::PAYMENT_STATUS_UNPAID,
                    Booking::PAYMENT_STATUS_PARTIAL,
                ]);
            })
            ->when($kind === 'successful', function ($query): void {
                $query->where(function ($q): void {
                    $q->where('bookings.payment_status', Booking::PAYMENT_STATUS_PAID)
                        ->orWhereIn('bookings.booking_status', [
                            Booking::BOOKING_STATUS_OCCUPIED,
                            Booking::BOOKING_STATUS_COMPLETED,
                        ]);
                });
            })
            ->whereBetween('bookings.check_in', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->groupBy('guests.is_international', 'guests.country', 'guests.region', 'guests.province', 'guests.municipality', 'guests.barangay')
            ->orderByRaw('guests.is_international ASC, total DESC, guests.region ASC')
            ->get();
    }
}

