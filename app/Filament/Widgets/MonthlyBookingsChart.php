<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\Booking;
use Carbon\Carbon;

class MonthlyBookingsChart extends ChartWidget
{
    protected ?string $heading = 'Visitors Statistics';

    // Full width in dashboard
    protected int | string | array $columnSpan = 'full';

    // Medium height
    protected int | string | array $rowSpan = 2;

    protected function getData(): array
    {
        $labels = [];
        $data = [];
        $pointBackgroundColors = [];
        $previous = null;

        $currentMonth = now()->month;
        $currentYear = now()->year;

        for ($month = $currentMonth; $month <= 12; $month++) {
            $date = Carbon::create($currentYear, $month, 1);
            $labels[] = $date->format('M Y');

            // Count bookings for this month
            $count = Booking::whereYear('created_at', $currentYear)
                            ->whereMonth('created_at', $month)
                            ->count();
            $data[] = $count;

            // Color points based on increase/decrease
            if ($previous === null) {
                $pointBackgroundColors[] = '#f59e0b'; // amber
            } elseif ($count > $previous) {
                $pointBackgroundColors[] = '#22c55e'; // green = increase
            } elseif ($count < $previous) {
                $pointBackgroundColors[] = '#ef4444'; // red = decrease
            } else {
                $pointBackgroundColors[] = '#f59e0b'; // same = amber
            }

            $previous = $count;
        }

        // Dummy data if no bookings
        if (max($data) === 0) {
            $data = [5, 8, 6, 12, 10, 15, 9, 20, 18, 25, 22, 30];
            $pointBackgroundColors = [];
            $previous = null;
            foreach ($data as $count) {
                if ($previous === null) {
                    $pointBackgroundColors[] = '#f59e0b';
                } elseif ($count > $previous) {
                    $pointBackgroundColors[] = '#22c55e';
                } elseif ($count < $previous) {
                    $pointBackgroundColors[] = '#ef4444';
                } else {
                    $pointBackgroundColors[] = '#f59e0b';
                }
                $previous = $count;
            }
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Bookings',
                    'data' => $data,
                    'borderColor' => '#f59e0b', // amber line
                    'backgroundColor' => 'rgba(245, 158, 11, 0.15)', // subtle gradient
                    'fill' => true,
                    'pointBackgroundColor' => $pointBackgroundColors,
                    'tension' => 0.4, // smooth curve
                    'pointRadius' => 6, // visible points
                    'pointHoverRadius' => 8,
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        $dataset = $this->getData()['datasets'][0]['data'];
        $maxValue = max($dataset) ?: 10;
        $yMax = ceil($maxValue / 5) * 5; // round up to nearest multiple of 5

        return [
            'responsive' => true,
            'maintainAspectRatio' => false, // height controlled by rowSpan
            'plugins' => [
                'tooltip' => [
                    'callbacks' => [
                        'label' => fn($context) => $context['raw'],
                    ],
                ],
                'legend' => [
                    'display' => false, // hide legend for cleaner look
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'max' => $yMax,
                    'ticks' => [
                        'stepSize' => 5,
                        'precision' => 0,
                        'color' => '#4b5563', // Tailwind gray-700
                        'font' => ['weight' => '500'],
                    ],
                    'grid' => [
                        'color' => 'rgba(203, 213, 225, 0.3)', // Tailwind gray-300
                    ],
                ],
                'x' => [
                    'ticks' => [
                        'color' => '#4b5563',
                        'font' => ['weight' => '500'],
                    ],
                    'grid' => [
                        'color' => 'rgba(203, 213, 225, 0.1)',
                    ],
                ],
            ],
        ];
    }
}
