<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">{{ __('Recycle Bin') }}</x-slot>
            <x-slot name="description">{{ __('Restore items or remove them permanently.') }}</x-slot>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="text-sm text-gray-600 dark:text-gray-300">
                    <span class="font-semibold">{{ number_format($this->totalTrashed) }}</span>
                    {{ __('total items') }}
                </div>
                <div class="flex items-center gap-2">
                    @if (count($this->selectedTrashItems) > 0)
                        <span class="rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700 dark:bg-white/10 dark:text-gray-200">
                            {{ trans_choice(':count selected|:count selected', count($this->selectedTrashItems), ['count' => count($this->selectedTrashItems)]) }}
                        </span>
                    @endif
                    <x-filament::button
                        color="danger"
                        wire:click="openBulkDeleteModal"
                        :disabled="count($this->selectedTrashItems) === 0"
                    >
                        {{ __('Delete selected') }}
                    </x-filament::button>
                    <x-filament::button
                        color="danger"
                        outlined
                        wire:click="openEmptyTrashModal"
                        :disabled="$this->totalTrashed === 0"
                    >
                        {{ __('Empty recycle bin') }}
                    </x-filament::button>
                </div>
            </div>
        </x-filament::section>

        @if ($this->totalTrashed === 0)
            <x-filament::section>
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    {{ __('Recycle Bin is empty.') }}
                </p>
            </x-filament::section>
        @else
            <x-filament::section>
                <x-slot name="heading">{{ __('Deleted items') }}</x-slot>

                <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
                    <table class="w-full divide-y divide-gray-200 text-start text-sm dark:divide-white/10">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr>
                                <th class="px-3 py-3 text-center">
                                    <input
                                        type="checkbox"
                                        wire:click="toggleSelectAllCurrentPage"
                                        :checked="$this->areAllCurrentPageSelected"
                                        class="h-4 w-4 rounded border-gray-300 text-danger-600 focus:ring-danger-500 dark:border-white/20 dark:bg-gray-950"
                                    />
                                </th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">{{ __('Name') }}</th>
                                <th class="hidden px-4 py-3 text-xs font-semibold uppercase tracking-wide text-gray-600 sm:table-cell dark:text-gray-300">{{ __('Location') }}</th>
                                <th class="hidden px-4 py-3 text-xs font-semibold uppercase tracking-wide text-gray-600 md:table-cell dark:text-gray-300">{{ __('Deleted at') }}</th>
                                <th class="px-4 py-3 text-end text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                            @foreach ($this->trashedPaginator as $row)
                                <tr wire:key="trash-{{ $row['type'] }}-{{ $row['id'] }}">
                                    <td class="px-3 py-3 text-center align-top">
                                        <input
                                            type="checkbox"
                                            wire:model.live="selectedTrashItems"
                                            value="{{ $row['type'] }}:{{ (string) $row['id'] }}"
                                            class="h-4 w-4 rounded border-gray-300 text-danger-600 focus:ring-danger-500 dark:border-white/20 dark:bg-gray-950"
                                        />
                                    </td>
                                    <td class="px-4 py-3 align-top">
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $row['type_label'] }}</div>
                                        <div class="font-medium text-gray-950 dark:text-white">{{ $row['name'] }}</div>
                                        <div class="mt-1 text-xs text-gray-500 sm:hidden dark:text-gray-400">
                                            {{ $row['location'] }}
                                            @if ($row['deleted_at'])
                                                · {{ $row['deleted_at']->diffForHumans() }}
                                            @endif
                                        </div>
                                    </td>
                                    <td class="hidden px-4 py-3 align-middle text-gray-600 sm:table-cell dark:text-gray-400">{{ $row['location'] }}</td>
                                    <td class="hidden px-4 py-3 align-middle text-gray-600 md:table-cell dark:text-gray-400">
                                        {{ $row['deleted_at']?->timezone(config('app.timezone'))->format('M j, Y g:i A') ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 align-middle">
                                        <div class="flex items-center justify-end gap-2">
                                            @if (! empty($row['edit_url']))
                                                <x-filament::button color="gray" size="sm" outlined class="min-w-[5.5rem] justify-center" tag="a" :href="$row['edit_url']">
                                                    {{ __('Open') }}
                                                </x-filament::button>
                                            @endif
                                            <x-filament::button
                                                color="success"
                                                size="sm"
                                                outlined
                                                class="min-w-[5.5rem] justify-center"
                                                wire:click="restoreItem('{{ $row['type'] }}', '{{ (string) $row['id'] }}')"
                                            >
                                                {{ __('Restore') }}
                                            </x-filament::button>
                                            <div
                                                class="relative"
                                                x-data="{ open: false }"
                                                @click.outside="open = false"
                                            >
                                                <x-filament::icon-button
                                                    color="gray"
                                                    icon="heroicon-m-ellipsis-horizontal"
                                                    :label="__('More actions')"
                                                    class="h-9 w-9"
                                                    @click="open = !open"
                                                />
                                                <div
                                                    x-cloak
                                                    x-show="open"
                                                    x-transition.origin.top.right
                                                    class="absolute right-0 top-full z-20 mt-1 w-40 overflow-hidden rounded-lg border border-gray-200 bg-white py-1 shadow-lg dark:border-white/10 dark:bg-gray-900"
                                                >
                                                    <button
                                                        type="button"
                                                        class="block w-full px-3 py-2 text-left text-sm font-medium text-danger-600 hover:bg-danger-50 dark:text-danger-400 dark:hover:bg-danger-500/10"
                                                        @click="open = false; $wire.openPurgeModal('{{ $row['type'] }}', '{{ (string) $row['id'] }}')"
                                                    >
                                                        {{ __('Delete forever') }}
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($this->trashedPaginator->hasPages())
                    <div class="pt-3">
                        {{ $this->trashedPaginator->links() }}
                    </div>
                @endif
            </x-filament::section>
        @endif

        <x-filament::section :collapsible="true" :collapsed="true">
            <x-slot name="heading">{{ __('Browse by type') }}</x-slot>
            <ul class="divide-y divide-gray-200 rounded-xl border border-gray-200 bg-white dark:divide-white/10 dark:border-white/10 dark:bg-gray-900">
                @foreach ($this->links as $link)
                    <li class="flex items-center justify-between gap-2 px-4 py-3">
                        <a href="{{ $link['url'] }}" class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">
                            {{ $link['label'] }}
                        </a>
                        <span class="text-xs text-gray-600 dark:text-gray-300">
                            {{ $link['count'] }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </x-filament::section>
    </div>

    @if (filled($this->purgeType) && filled($this->purgeId))
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/50 px-4 py-6 dark:bg-gray-950/70"
            wire:click.self="closePurgeModal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="recycle-purge-title"
        >
            <div
                class="fi-modal-window w-full max-w-lg overflow-hidden rounded-xl bg-white shadow-xl ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
                @click.stop
            >
                <div class="border-b border-gray-200 px-6 py-4 dark:border-white/10">
                    <h2 id="recycle-purge-title" class="text-lg font-semibold text-gray-950 dark:text-white">
                        {{ __('Delete permanently?') }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ __('This cannot be undone.') }}</p>
                    <p class="mt-1 text-sm font-medium text-gray-950 dark:text-white">{{ $this->purgeItemName }}</p>
                </div>
                <div class="space-y-4 px-6 py-4">
                    <x-filament::input.wrapper>
                        <x-filament::input
                            type="text"
                            wire:model="purgeTypedConfirm"
                            :placeholder="__('Type DELETE in all capitals')"
                            autocomplete="off"
                            class="font-mono"
                        />
                    </x-filament::input.wrapper>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Type DELETE to confirm.') }}</p>
                </div>
                <div class="flex flex-wrap justify-end gap-2 border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-white/10 dark:bg-white/5">
                    <x-filament::button color="gray" wire:click="closePurgeModal">
                        {{ __('Cancel') }}
                    </x-filament::button>
                    <x-filament::button color="danger" wire:click="confirmPermanentDelete">
                        {{ __('Delete permanently') }}
                    </x-filament::button>
                </div>
            </div>
        </div>
    @endif

    @if ($this->emptyTrashOpen)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/50 px-4 py-6 dark:bg-gray-950/70"
            wire:click.self="closeEmptyTrashModal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="recycle-empty-title"
        >
            <div
                class="fi-modal-window w-full max-w-lg overflow-hidden rounded-xl bg-white shadow-xl ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
                @click.stop
            >
                <div class="border-b border-gray-200 px-6 py-4 dark:border-white/10">
                    <h2 id="recycle-empty-title" class="text-lg font-semibold text-gray-950 dark:text-white">{{ __('Empty Recycle Bin?') }}</h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        {{ __('This permanently deletes all trashed records from all modules.') }}
                    </p>
                </div>
                <div class="space-y-4 px-6 py-4">
                    <label class="flex items-start gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input
                            type="checkbox"
                            wire:model.live="emptyTrashConfirmed"
                            class="mt-0.5 h-4 w-4 rounded border-gray-300 text-danger-600 focus:ring-danger-500 dark:border-white/20 dark:bg-gray-950"
                        />
                        <span>{{ __('I understand this action cannot be undone.') }}</span>
                    </label>
                    <x-filament::input.wrapper>
                        <x-filament::input
                            type="text"
                            wire:model.live="emptyTrashTypedConfirm"
                            :placeholder="__('Type DELETE in all capitals')"
                            autocomplete="off"
                            class="font-mono"
                        />
                    </x-filament::input.wrapper>
                </div>
                <div class="flex flex-wrap justify-end gap-2 border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-white/10 dark:bg-white/5">
                    <x-filament::button color="gray" wire:click="closeEmptyTrashModal">
                        {{ __('Cancel') }}
                    </x-filament::button>
                    <x-filament::button color="danger" wire:click="confirmEmptyTrash">
                        {{ __('Empty trash now') }}
                    </x-filament::button>
                </div>
            </div>
        </div>
    @endif

    @if ($this->bulkDeleteOpen)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/50 px-4 py-6 dark:bg-gray-950/70"
            wire:click.self="closeBulkDeleteModal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="recycle-bulk-delete-title"
        >
            <div
                class="fi-modal-window w-full max-w-lg overflow-hidden rounded-xl bg-white shadow-xl ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
                @click.stop
            >
                <div class="border-b border-gray-200 px-6 py-4 dark:border-white/10">
                    <h2 id="recycle-bulk-delete-title" class="text-lg font-semibold text-gray-950 dark:text-white">
                        {{ __('Delete selected items forever?') }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        {{ trans_choice(':count selected item will be permanently deleted.|:count selected items will be permanently deleted.', count($this->selectedTrashItems), ['count' => count($this->selectedTrashItems)]) }}
                    </p>
                </div>
                <div class="space-y-4 px-6 py-4">
                    <label class="flex items-start gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input
                            type="checkbox"
                            wire:model.live="bulkDeleteConfirmed"
                            class="mt-0.5 h-4 w-4 rounded border-gray-300 text-danger-600 focus:ring-danger-500 dark:border-white/20 dark:bg-gray-950"
                        />
                        <span>{{ __('I understand this action cannot be undone.') }}</span>
                    </label>
                    <x-filament::input.wrapper>
                        <x-filament::input
                            type="text"
                            wire:model.live="bulkDeleteTypedConfirm"
                            :placeholder="__('Type DELETE in all capitals')"
                            autocomplete="off"
                            class="font-mono"
                        />
                    </x-filament::input.wrapper>
                </div>
                <div class="flex flex-wrap justify-end gap-2 border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-white/10 dark:bg-white/5">
                    <x-filament::button color="gray" wire:click="closeBulkDeleteModal">
                        {{ __('Cancel') }}
                    </x-filament::button>
                    <x-filament::button color="danger" wire:click="confirmBulkDelete">
                        {{ __('Delete selected') }}
                    </x-filament::button>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
