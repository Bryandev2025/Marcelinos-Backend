<x-filament-panels::page>
    <style>
        @media print {
            .fi-topbar,
            .fi-sidebar,
            header.fi-header,
            .print-hidden {
                display: none !important;
            }

            .fi-main {
                margin: 0 !important;
                padding: 0 !important;
            }

            body {
                background: white !important;
            }

            .print-break {
                page-break-inside: avoid;
            }
        }
    </style>

    {{-- Main interactive dashboard (hidden during specialized prints) --}}
    <div id="mainDashboard">
        <div class="flex justify-end print-hidden mb-6">
            <x-filament::button icon="heroicon-o-printer" onclick="triggerPrint('monthly_overview', null)" color="primary">
                Print Full Monthly Report
            </x-filament::button>
        </div>

        <div class="grid grid-cols-1 gap-8">
            {{-- Unpaid Bookings Section --}}
            <x-filament::section icon="heroicon-m-clock" icon-color="primary">
                <x-slot name="heading">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between w-full">
                        <span>Highest Unpaid Bookings (Pending)</span>
                        <button onclick="triggerPrint('unpaid', 'all')" class="mt-2 sm:mt-0 px-3 py-1.5 rounded-lg text-sm font-bold bg-primary-50 text-primary-700 hover:bg-primary-100 transition-colors flex items-center gap-1.5 w-fit">
                            <x-filament::icon icon="heroicon-m-printer" class="w-4 h-4" />
                            Print Entire Pending
                        </button>
                    </div>
                </x-slot>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 custom-stats-grid">
                    @foreach (['today' => 'Today', 'next_7_days' => 'Next 7 Days', 'this_month' => 'This Month', 'next_month' => 'Next Month'] as $key => $label)
                        <div wire:click="mountAction('viewBookings', { period: '{{ $key }}', type: 'unpaid' })"
                            class="cursor-pointer relative flex flex-col justify-between overflow-hidden rounded-2xl bg-gray-50 p-6 shadow-sm ring-1 ring-gray-950/5 transition duration-300 hover:shadow-md hover:ring-primary-500/50 hover:-translate-y-1 dark:bg-white/5 dark:ring-white/10 dark:hover:ring-primary-400/50 group">
                            <!-- Decorative gradient blob -->
                            <div
                                class="absolute -right-6 -top-6 w-24 h-24 bg-primary-500/10 rounded-full blur-2xl group-hover:bg-primary-500/30 transition duration-500">
                            </div>

                            <div class="relative z-10">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                                @if (isset($unpaid[$key]))
                                    <dd class="mt-2 flex flex-col gap-0.5">
                                        <span class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white line-clamp-1">
                                            {{ $unpaid[$key]['name'] }}
                                        </span>
                                        @if(isset($unpaid[$key]['sub']) && $unpaid[$key]['sub'])
                                            <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                                {{ $unpaid[$key]['sub'] }}
                                            </span>
                                        @endif
                                    </dd>
                                @else
                                    <dd class="mt-2 flex flex-col gap-0.5">
                                        <span class="text-xl font-medium tracking-tight text-gray-400 dark:text-gray-500">
                                            No Data
                                        </span>
                                    </dd>
                                @endif
                            </div>

                            <div class="mt-4 flex items-center justify-between gap-x-2 text-sm relative z-10 block">
                                @if (isset($unpaid[$key]))
                                    <span class="inline-flex items-center gap-x-1.5 rounded-full px-2 py-1 text-xs font-medium text-primary-700 bg-primary-50 dark:text-primary-400 dark:bg-primary-400/10">
                                        <x-filament::icon icon="heroicon-m-user-group" class="w-4 h-4" />
                                        {{ $unpaid[$key]['count'] }} Booking(s)
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-x-1.5 rounded-full px-2 py-1 text-xs font-medium text-gray-600 bg-gray-100 dark:text-gray-400 dark:bg-gray-400/10">
                                        <x-filament::icon icon="heroicon-m-minus" class="w-4 h-4" />
                                        0 Bookings
                                    </span>
                                @endif

                                <button title="Print {{ $label }}" onclick="event.stopPropagation(); triggerPrint('unpaid', '{{ $key }}')" class="p-1.5 rounded-md text-gray-400 hover:text-primary-700 hover:bg-primary-100 dark:hover:bg-white/10 transition">
                                    <x-filament::icon icon="heroicon-m-printer" class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>

            {{-- Successful Bookings Section --}}
            <x-filament::section icon="heroicon-m-check-badge" icon-color="success">
                <x-slot name="heading">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between w-full">
                        <span>Highest Successful Bookings (Paid/Confirmed)</span>
                        <button onclick="triggerPrint('successful', 'all')" class="mt-2 sm:mt-0 px-3 py-1.5 rounded-lg text-sm font-bold bg-success-50 text-success-700 hover:bg-success-100 transition-colors flex items-center gap-1.5 w-fit">
                            <x-filament::icon icon="heroicon-m-printer" class="w-4 h-4" />
                            Print Entire Confirmed
                        </button>
                    </div>
                </x-slot>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 custom-stats-grid">
                    @foreach (['today' => 'Today', 'next_7_days' => 'Next 7 Days', 'this_month' => 'This Month', 'next_month' => 'Next Month'] as $key => $label)
                        <div wire:click="mountAction('viewBookings', { period: '{{ $key }}', type: 'successful' })"
                            class="cursor-pointer relative flex flex-col justify-between overflow-hidden rounded-2xl bg-gray-50 p-6 shadow-sm ring-1 ring-gray-950/5 transition duration-300 hover:shadow-md hover:ring-success-500/50 hover:-translate-y-1 dark:bg-white/5 dark:ring-white/10 dark:hover:ring-success-400/50 group">
                            <!-- Decorative gradient blob -->
                            <div
                                class="absolute -right-6 -top-6 w-24 h-24 bg-success-500/10 rounded-full blur-2xl group-hover:bg-success-500/30 transition duration-500">
                            </div>

                            <div class="relative z-10">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                                @if (isset($successful[$key]))
                                    <dd class="mt-2 flex flex-col gap-0.5">
                                        <span class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white line-clamp-1">
                                            {{ $successful[$key]['name'] }}
                                        </span>
                                        @if(isset($successful[$key]['sub']) && $successful[$key]['sub'])
                                            <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                                {{ $successful[$key]['sub'] }}
                                            </span>
                                        @endif
                                    </dd>
                                @else
                                    <dd class="mt-2 flex flex-col gap-0.5">
                                        <span class="text-xl font-medium tracking-tight text-gray-400 dark:text-gray-500">
                                            No Data
                                        </span>
                                    </dd>
                                @endif
                            </div>

                            <div class="mt-4 flex items-center justify-between gap-x-2 text-sm relative z-10 block">
                                @if (isset($successful[$key]))
                                    <span class="inline-flex items-center gap-x-1.5 rounded-full px-2 py-1 text-xs font-medium text-success-700 bg-success-50 dark:text-success-400 dark:bg-success-400/10">
                                        <x-filament::icon icon="heroicon-m-user-group" class="w-4 h-4" />
                                        {{ $successful[$key]['count'] }} Booking(s)
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-x-1.5 rounded-full px-2 py-1 text-xs font-medium text-gray-600 bg-gray-100 dark:text-gray-400 dark:bg-gray-400/10">
                                        <x-filament::icon icon="heroicon-m-minus" class="w-4 h-4" />
                                        0 Bookings
                                    </span>
                                @endif

                                <button title="Print {{ $label }}" onclick="event.stopPropagation(); triggerPrint('successful', '{{ $key }}')" class="p-1.5 rounded-md text-gray-400 hover:text-success-700 hover:bg-success-100 dark:hover:bg-white/10 transition">
                                    <x-filament::icon icon="heroicon-m-printer" class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        </div>
    </div>


    {{-- PRINT TEMPLATES MANAGER --}}
    <div id="printTemplatesManager" class="hidden print:block !bg-transparent !shadow-none !p-0">
        
        {{-- Generic Template builder for specific periods --}}
        @foreach (['unpaid' => 'Unpaid Bookings (Pending)', 'successful' => 'Successful Bookings (Paid/Confirmed)'] as $typeKey => $typeLabel)
            @foreach (['today' => 'Today', 'next_7_days' => 'Next 7 Days', 'this_month' => 'This Month', 'next_month' => 'Next Month', 'all' => 'All Time'] as $periodKey => $periodLabel)
                @php
                    $rep = $reports[$typeKey][$periodKey];
                    $repLocal = $rep->where('is_international', false);
                    $repForeign = $rep->where('is_international', true);
                @endphp
                <div id="print-{{ $typeKey }}-{{ $periodKey }}" class="tourism-print-block hidden w-full break-after-page">
                    <div class="text-center mb-8">
                        <h1 class="text-3xl font-bold text-gray-900">Demographics Report: {{ $typeLabel }}</h1>
                        <p class="text-gray-500 text-lg mt-1">Period: {{ $periodLabel }} | Printed: {{ now()->format('M d, Y') }}</p>
                    </div>

                    <div class="space-y-8">
                        {{-- Domestic --}}
                        <div>
                            <div class="border-b-2 border-gray-900 pb-2 mb-4 flex justify-between items-end">
                                <h3 class="text-xl font-bold text-gray-900 uppercase tracking-widest">Domestic Tourists</h3>
                                <span class="font-bold border border-gray-300 px-3 py-1 rounded bg-gray-100">Total: {{ $repLocal->sum('total') }}</span>
                            </div>
                            <table class="w-full text-left text-sm border-collapse border border-gray-300">
                                <thead class="bg-gray-100 font-bold">
                                    <tr>
                                        <th class="px-4 py-3 border border-gray-300">Region</th>
                                        <th class="px-4 py-3 border border-gray-300">Province</th>
                                        <th class="px-4 py-3 border border-gray-300">Municipality/City</th>
                                        <th class="px-4 py-3 border border-gray-300 text-right">Count</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($repLocal as $stat)
                                        <tr>
                                            <td class="px-4 py-3 border border-gray-300 font-medium">{{ $stat->region ?: 'N/A' }}</td>
                                            <td class="px-4 py-3 border border-gray-300">{{ $stat->province ?: 'N/A' }}</td>
                                            <td class="px-4 py-3 border border-gray-300">{{ $stat->municipality ?: 'N/A' }}</td>
                                            <td class="px-4 py-3 border border-gray-300 text-right font-bold">{{ $stat->total }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">No records found.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        {{-- International --}}
                        <div>
                            <div class="border-b-2 border-gray-900 pb-2 mb-4 flex justify-between items-end">
                                <h3 class="text-xl font-bold text-gray-900 uppercase tracking-widest">International Tourists</h3>
                                <span class="font-bold border border-gray-300 px-3 py-1 rounded bg-gray-100">Total: {{ $repForeign->sum('total') }}</span>
                            </div>
                            <table class="w-full text-left text-sm border-collapse border border-gray-300">
                                <thead class="bg-gray-100 font-bold">
                                    <tr>
                                        <th class="px-4 py-3 border border-gray-300">Country of Origin</th>
                                        <th class="px-4 py-3 border border-gray-300 text-right">Count</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($repForeign as $stat)
                                        <tr>
                                            <td class="px-4 py-3 border border-gray-300">{{ $stat->country ?: 'N/A' }}</td>
                                            <td class="px-4 py-3 border border-gray-300 text-right font-bold">{{ $stat->total }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="2" class="px-4 py-6 text-center text-gray-500">No records found.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endforeach
        @endforeach


        {{-- Default Overview Report Block (Monthly Successful) --}}
        <div id="print-monthly_overview-null" class="tourism-print-block hidden w-full">
            <div class="text-center mb-10">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Comprehensive Demographics Report</h1>
                <p class="text-gray-600 font-medium text-lg uppercase tracking-wider">MONTH: {{ $reportMonth }}</p>
                <p class="text-gray-500 text-sm mt-1">Generated: {{ now()->format('F j, Y, g:i a') }}</p>
            </div>

            <div class="space-y-12">
                {{-- Local Guests Table --}}
                <div>
                    <div class="border-b-2 border-gray-800 pb-2 mb-4 px-2 flex justify-between items-end">
                        <h3 class="text-xl font-bold text-gray-900 uppercase tracking-widest">Confirmed Domestic Tourists</h3>
                        <span class="font-bold bg-gray-100 border border-gray-300 px-3 py-1 rounded">
                            {{ $localDemographics->sum('total') }} Total
                        </span>
                    </div>
                    <table class="w-full text-left text-sm border-collapse border border-gray-300">
                        <thead class="bg-gray-100 text-gray-800 font-bold uppercase text-xs tracking-wider">
                            <tr>
                                <th class="px-4 py-3 border border-gray-300">Region</th>
                                <th class="px-4 py-3 border border-gray-300">Province</th>
                                <th class="px-4 py-3 border border-gray-300">Municipality/City</th>
                                <th class="px-4 py-3 border border-gray-300 text-right">Confirmed Guests</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white text-gray-900">
                            @forelse ($localDemographics as $stat)
                                <tr>
                                    <td class="px-4 py-2 border border-gray-300 font-medium">{{ $stat->region ?: 'Not Specified' }}</td>
                                    <td class="px-4 py-2 border border-gray-300">{{ $stat->province ?: 'Not Specified' }}</td>
                                    <td class="px-4 py-2 border border-gray-300">{{ $stat->municipality ?: 'Not Specified' }}</td>
                                    <td class="px-4 py-2 border border-gray-300 text-right font-bold">{{ $stat->total }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-8 text-center text-gray-500 italic">No domestic records found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Foreign Guests Table --}}
                <div class="break-inside-avoid">
                    <div class="border-b-2 border-gray-800 pb-2 mb-4 px-2 flex justify-between items-end">
                        <h3 class="text-xl font-bold text-gray-900 uppercase tracking-widest">Confirmed International Tourists</h3>
                        <span class="font-bold bg-gray-100 border border-gray-300 px-3 py-1 rounded">
                            {{ $foreignDemographics->sum('total') }} Total
                        </span>
                    </div>
                    <table class="w-full text-left text-sm border-collapse border border-gray-300">
                        <thead class="bg-gray-100 text-gray-800 font-bold uppercase text-xs tracking-wider">
                            <tr>
                                <th class="px-4 py-3 border border-gray-300">Country of Origin</th>
                                <th class="px-4 py-3 border border-gray-300 text-right">Confirmed Guests</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white text-gray-900">
                            @forelse ($foreignDemographics as $stat)
                                <tr>
                                    <td class="px-4 py-2 border border-gray-300 font-bold">{{ $stat->country ?: 'Not Specified' }}</td>
                                    <td class="px-4 py-2 border border-gray-300 text-right font-bold">{{ $stat->total }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="px-4 py-8 text-center text-gray-500 italic">No international records found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        function triggerPrint(type, period) {
            // First, block the main interactive dashboard completely during print
            document.getElementById('mainDashboard').classList.add('print:hidden');
            
            // Unhide the print template manager so its display rules apply
            document.getElementById('printTemplatesManager').classList.remove('hidden');
            
            // Hide all specialized templates initially
            document.querySelectorAll('.tourism-print-block').forEach(el => {
                el.classList.add('hidden');
                el.classList.remove('block', 'print:block');
            });
            
            // Find the exact template we requested
            let targetId = 'print-' + type + '-' + period;
            let targetElement = document.getElementById(targetId);
            
            if (targetElement) {
                targetElement.classList.remove('hidden');
                targetElement.classList.add('block', 'print:block');
            }
            
            // Trigger actual browser print
            window.print();
            
            // Restore normal state after the print dialog closes
            setTimeout(() => {
                document.getElementById('mainDashboard').classList.remove('print:hidden');
                document.getElementById('printTemplatesManager').classList.add('hidden');
            }, 1000);
        }
    </script>
</x-filament-panels::page>