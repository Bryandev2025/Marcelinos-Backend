<x-filament-panels::page>
    <div wire:poll.5s class="space-y-6">
        @forelse ($this->timelineGroups as $groupLabel => $logs)
            <section class="space-y-3">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                        {{ $groupLabel }}
                    </h3>

                    @if ($loop->first)
                        <div class="flex shrink-0 items-center gap-2" x-data="{ searchOpen: false }">
                            <div class="relative shrink-0" x-show="!searchOpen" x-transition.opacity>
                                <button
                                    type="button"
                                    @click="searchOpen = true; $nextTick(() => $refs.activitySearchInput?.focus())"
                                    class="inline-flex h-9 w-9 items-center justify-center text-gray-500 transition hover:text-gray-700 dark:text-gray-300 dark:hover:text-white"
                                    title="Search activity"
                                >
                                    <x-filament::icon icon="heroicon-o-magnifying-glass" class="h-5 w-5" />
                                </button>
                            </div>

                            <div
                                x-show="searchOpen"
                                x-transition
                                class="relative min-w-0 w-[260px] max-w-[70vw]"
                            >
                                <label for="activity-search" class="sr-only">Search activity</label>
                                <input
                                    id="activity-search"
                                    type="search"
                                    x-ref="activitySearchInput"
                                    wire:model.live.debounce.400ms="search"
                                    placeholder="Search user, event, message, device..."
                                    class="fi-input block h-9 w-full rounded-lg border-none bg-white px-3 py-2 text-sm text-gray-900 ring-1 ring-gray-300 transition focus:ring-2 focus:ring-primary-500 dark:bg-white/5 dark:text-white dark:ring-white/20"
                                />
                            </div>

                            <div class="relative shrink-0" x-data="{ open: false }" @click.away="open = false">
                                <button
                                    type="button"
                                    @click="open = !open"
                                    class="inline-flex h-9 w-9 items-center justify-center text-gray-500 transition hover:text-gray-700 dark:text-gray-300 dark:hover:text-white"
                                    title="Filter by date range"
                                >
                                    <x-filament::icon icon="heroicon-o-funnel" class="h-5 w-5" />
                                </button>

                                <div
                                    x-show="open"
                                    x-transition
                                    class="absolute right-0 z-20 mt-2 w-[340px] max-w-[90vw] rounded-xl border border-gray-200 bg-white p-4 shadow-lg dark:border-white/20 dark:bg-gray-900"
                                >
                                    <div class="space-y-2">
                                        <div>
                                            <label for="activity-date-filter-from" class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">From</label>
                                            <input
                                                id="activity-date-filter-from"
                                                type="date"
                                                max="{{ now()->toDateString() }}"
                                                wire:model.live="fromDate"
                                                class="fi-input block h-9 w-full rounded-lg border-none bg-white px-2 py-1 text-sm text-gray-900 ring-1 ring-gray-300 focus:ring-2 focus:ring-primary-500 dark:bg-white/5 dark:text-white dark:ring-white/20"
                                            />
                                        </div>
                                        <div>
                                            <label for="activity-date-filter-to" class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">To</label>
                                            <input
                                                id="activity-date-filter-to"
                                                type="date"
                                                max="{{ now()->toDateString() }}"
                                                wire:model.live="toDate"
                                                class="fi-input block h-9 w-full rounded-lg border-none bg-white px-2 py-1 text-sm text-gray-900 ring-1 ring-gray-300 focus:ring-2 focus:ring-primary-500 dark:bg-white/5 dark:text-white dark:ring-white/20"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
                

                <div class="relative pl-7">
                    <div class="absolute left-[1.1rem] top-0 bottom-0 w-px bg-gray-200 dark:bg-white/10"></div>

                    <div class="space-y-4">
                        @foreach ($logs as $log)
                            @php
                                $icon = $this->getLogIcon((string) $log->category, (string) $log->event);
                                $iconColor = $this->getLogIconColor((string) $log->category, (string) $log->event);
                                $actor = $log->user?->name ?? 'System';
                            @endphp

                            <article class="relative rounded-xl border border-gray-200 bg-white p-3 shadow-xs dark:border-white/10 dark:bg-white/5">
                                <span class="absolute -left-[1.85rem] top-5 inline-flex h-8 w-8 items-center justify-center rounded-full border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
                                    <x-filament::icon
                                        :icon="$icon"
                                        class="h-4 w-4 {{ $iconColor }}"
                                    />
                                </span>

                                <p class="text-sm text-gray-900 dark:text-gray-100">
                                    <span class="font-semibold">{{ $actor }}</span>
                                    <span>{{ $this->getDisplayMessage($log) }}</span>
                                </p>

                                <div class="mt-2 flex flex-col gap-2 text-xs text-gray-500 dark:text-gray-400 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="flex items-center gap-2">
                                        <span>{{ $log->created_at?->format('h:i A') }}</span>
                                        <span aria-hidden="true">•</span>
                                        <span>{{ $this->getCategoryLabel($log) }}</span>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                                        <span class="inline-flex items-center rounded-full bg-yellow-50 px-2 py-0.5 font-medium text-gray-700 dark:bg-white/10 dark:text-gray-200">
                                            {{ $this->getDeviceName($log) }}
                                        </span>
                                        <span class="inline-flex items-center rounded-full bg-green-50 px-2 py-0.5 font-medium text-gray-700 dark:bg-white/10 dark:text-gray-200">
                                            {{ $this->getBrowserName($log) }}
                                        </span>
                                        <span class="inline-flex max-w-full items-center rounded-full bg-blue-50 px-2 py-0.5 font-medium text-gray-700 dark:bg-white/10 dark:text-gray-200">
                                            {{ $log->ip_address ?: 'Unknown IP' }}
                                        </span>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>
        @empty
            <div class="space-y-2">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                        Today
                    </h3>

                    <div class="flex shrink-0 items-center gap-2" x-data="{ searchOpen: false }">
                        <div class="relative shrink-0" x-show="!searchOpen" x-transition.opacity>
                            <button
                                type="button"
                                @click="searchOpen = true; $nextTick(() => $refs.activitySearchInput?.focus())"
                                class="inline-flex h-9 w-9 items-center justify-center text-gray-500 transition hover:text-gray-700 dark:text-gray-300 dark:hover:text-white"
                                title="Search activity"
                            >
                                <x-filament::icon icon="heroicon-o-magnifying-glass" class="h-5 w-5" />
                            </button>
                        </div>

                        <div
                            x-show="searchOpen"
                            x-transition
                            class="relative min-w-0 w-[260px] max-w-[70vw]"
                        >
                            <label for="activity-search-empty" class="sr-only">Search activity</label>
                            <input
                                id="activity-search-empty"
                                type="search"
                                x-ref="activitySearchInput"
                                wire:model.live.debounce.400ms="search"
                                placeholder="Search user, event, message, device..."
                                class="fi-input block h-9 w-full rounded-lg border-none bg-white px-3 py-2 text-sm text-gray-900 ring-1 ring-gray-300 transition focus:ring-2 focus:ring-primary-500 dark:bg-white/5 dark:text-white dark:ring-white/20"
                            />
                        </div>

                        <div class="relative shrink-0" x-data="{ open: false }" @click.away="open = false">
                            <button
                                type="button"
                                @click="open = !open"
                                class="inline-flex h-9 w-9 items-center justify-center text-gray-500 transition hover:text-gray-700 dark:text-gray-300 dark:hover:text-white"
                                title="Filter by date range"
                            >
                                <x-filament::icon icon="heroicon-o-funnel" class="h-5 w-5" />
                            </button>

                            <div
                                x-show="open"
                                x-transition
                                class="absolute right-0 z-20 mt-2 w-[340px] max-w-[90vw] rounded-xl border border-gray-200 bg-white p-4 shadow-lg dark:border-white/20 dark:bg-gray-900"
                            >
                                <div class="space-y-2">
                                    <div>
                                        <label for="activity-date-filter-from-empty" class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">From</label>
                                        <input
                                            id="activity-date-filter-from-empty"
                                            type="date"
                                            max="{{ now()->toDateString() }}"
                                            wire:model.live="fromDate"
                                            class="fi-input block h-9 w-full rounded-lg border-none bg-white px-2 py-1 text-sm text-gray-900 ring-1 ring-gray-300 focus:ring-2 focus:ring-primary-500 dark:bg-white/5 dark:text-white dark:ring-white/20"
                                        />
                                    </div>
                                    <div>
                                        <label for="activity-date-filter-to-empty" class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">To</label>
                                        <input
                                            id="activity-date-filter-to-empty"
                                            type="date"
                                            max="{{ now()->toDateString() }}"
                                            wire:model.live="toDate"
                                            class="fi-input block h-9 w-full rounded-lg border-none bg-white px-2 py-1 text-sm text-gray-900 ring-1 ring-gray-300 focus:ring-2 focus:ring-primary-500 dark:bg-white/5 dark:text-white dark:ring-white/20"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-500 dark:border-white/20 dark:bg-white/5 dark:text-gray-400">
                @if (filled($search))
                    No activity matched "{{ $search }}".
                @else
                    No activity yet.
                @endif
                </div>
            </div>
        @endforelse

        @if ($this->hasMoreLogs)
            <div class="flex justify-center pt-2">
                <x-filament::button
                    color="gray"
                    icon="heroicon-o-chevron-down"
                    wire:click="seeMore"
                >
                    See more
                </x-filament::button>
            </div>
        @endif
    </div>
</x-filament-panels::page>
