@php
    /** @var string $title */
    /** @var array<int, string> $items */
    /** @var string|null $subtitle */
    $subtitle = $subtitle ?? null;
@endphp

<div class="space-y-4">
    <div class="space-y-1">
        <div class="text-sm font-semibold text-gray-900 dark:text-white">
            {{ $title }}
        </div>
        @if ($subtitle)
            <div class="text-xs text-gray-600 dark:text-gray-400">
                {{ $subtitle }}
            </div>
        @endif
    </div>

    @if (count($items) === 0)
        <div class="rounded-xl border border-dashed border-gray-200 bg-gray-50 px-4 py-8 text-center text-sm text-gray-600 dark:border-white/10 dark:bg-white/[0.03] dark:text-gray-300">
            {{ __('No items') }}
        </div>
    @else
        <div class="max-h-[60vh] overflow-y-auto rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-950/40">
            <ul class="divide-y divide-gray-100 dark:divide-white/5">
                @foreach ($items as $item)
                    <li class="px-4 py-3 text-sm text-gray-800 dark:text-gray-200">
                        {{ $item }}
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>

