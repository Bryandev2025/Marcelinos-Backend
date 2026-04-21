@php
    /** @var \App\Models\Guest|null $guest */
    $address = null;

    if ($guest) {
        $parts = array_filter([
            $guest->barangay,
            $guest->municipality,
            $guest->province,
            $guest->region,
            $guest->country,
        ]);
        $address = $parts !== [] ? implode(', ', $parts) : null;
    }
@endphp

<div class="space-y-4">
    @if (! $guest)
        <div class="rounded-xl border border-dashed border-gray-200 bg-gray-50 px-4 py-8 text-center text-sm text-gray-600 dark:border-white/10 dark:bg-white/[0.03] dark:text-gray-300">
            {{ __('No guest details available.') }}
        </div>
    @else
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div class="rounded-lg border border-gray-200 bg-white px-3 py-2 dark:border-white/10 dark:bg-gray-950/40">
                <div class="text-xs text-gray-500 dark:text-gray-400">Full name</div>
                <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $guest->full_name ?: '—' }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white px-3 py-2 dark:border-white/10 dark:bg-gray-950/40">
                <div class="text-xs text-gray-500 dark:text-gray-400">Email</div>
                <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $guest->email ?: '—' }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white px-3 py-2 dark:border-white/10 dark:bg-gray-950/40">
                <div class="text-xs text-gray-500 dark:text-gray-400">Contact number</div>
                <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $guest->contact_num ?: '—' }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white px-3 py-2 dark:border-white/10 dark:bg-gray-950/40">
                <div class="text-xs text-gray-500 dark:text-gray-400">Gender</div>
                <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $guest->gender ? ucfirst((string) $guest->gender) : '—' }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white px-3 py-2 dark:border-white/10 dark:bg-gray-950/40 sm:col-span-2">
                <div class="text-xs text-gray-500 dark:text-gray-400">Address</div>
                <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $address ?: '—' }}</div>
            </div>
        </div>
    @endif
</div>
