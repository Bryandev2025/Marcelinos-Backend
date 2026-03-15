@php
    $imageUrl = $record?->image_url;
@endphp

<div x-data="{ open: false }" class="group relative overflow-hidden rounded-md border border-gray-200 bg-white shadow-md dark:border-white/10 dark:bg-white/5">
    @if (filled($imageUrl))
        <img
            src="{{ $imageUrl }}"
            alt="Gallery image {{ $record?->id }}"
            class="h-36 w-full object-cover sm:h-44"
            loading="lazy"
        />
    @else
        <div class="flex h-36 w-full items-center justify-center bg-gray-100 text-xs text-gray-500 sm:h-44 dark:bg-white/10 dark:text-gray-400">
            No image
        </div>
    @endif

    <div class="pointer-events-none absolute inset-0 bg-black/35 opacity-0 transition md:group-hover:opacity-100"></div>

    <div class="absolute left-1 top-2 z-20 md:opacity-0 md:group-hover:opacity-100 md:group-focus-within:opacity-100 transition">
        <button
            type="button"
            x-on:click.stop="open = ! open"
            class="inline-flex h-8 w-8 items-center justify-center text-white"
            aria-label="More options"
        >
            <x-filament::icon icon="heroicon-m-ellipsis-vertical" class="h-5 w-5" />
        </button>

        <div
            x-cloak
            x-show="open"
            x-on:click.away="open = false"
            x-transition
            class="mt-1 w-fit overflow-hidden rounded-md p-1 bg-white py-1 shadow-lg ring-1 ring-black/10"
        >
            <button
                type="button"
                wire:click.stop="mountTableAction('edit', '{{ $record->getKey() }}')"
                x-on:click="open = false"
                class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs rounded-md font-medium text-primary hover:bg-gray-100"
            >
                <x-filament::icon icon="heroicon-o-arrow-path-rounded-square" class="h-4 w-4" />
                Replace image
            </button>
            <button
                type="button"
                wire:click.stop="mountTableAction('delete', '{{ $record->getKey() }}')"
                x-on:click="open = false"
                class="flex w-full items-center rounded-md gap-2 px-3 py-2 text-left text-xs font-medium text-danger-600 hover:bg-danger-50"
            >
                <x-filament::icon icon="heroicon-m-trash" class="h-4 w-4" />
                Delete
            </button>
        </div>
    </div>
</div>
