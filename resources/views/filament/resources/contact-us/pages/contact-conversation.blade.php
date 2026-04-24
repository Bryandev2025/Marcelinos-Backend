<x-filament-panels::page>
    <div class="grid gap-4 lg:grid-cols-3" wire:poll.5s="refreshConversation">
        <section class="space-y-3 rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-white/5">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Conversation Details</h2>

            <dl class="space-y-2 text-sm">
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Client</dt>
                    <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $this->record->full_name }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Email</dt>
                    <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $this->record->email }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Subject</dt>
                    <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $this->record->subject }}</dd>
                </div>
                @if (filled($this->record->phone))
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Phone</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $this->record->phone }}</dd>
                    </div>
                @endif
            </dl>

            <div class="rounded-lg border border-gray-200 p-3 text-xs dark:border-white/10">
                <p class="mb-2 font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Thread Summary</p>
                <ul class="space-y-1 text-gray-700 dark:text-gray-200">
                    <li>Total Messages: {{ $this->threadMeta['total_messages'] }}</li>
                    <li>Started: {{ $this->threadMeta['started_at'] ?? 'N/A' }}</li>
                    <li>Last Message: {{ $this->threadMeta['last_message_at'] ?? 'N/A' }}</li>
                    <li>Replied At: {{ $this->threadMeta['replied_at'] ?? 'Not replied yet' }}</li>
                </ul>
            </div>

            <div class="space-y-2">
                <label for="conversation-status" class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</label>
                <div class="flex gap-2">
                    <select
                        id="conversation-status"
                        wire:model="status"
                        style="color-scheme: light dark;"
                        class="fi-input block w-full rounded-lg border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-white/20 dark:bg-white/5 dark:text-white"
                    >
                        <option value="new" class="bg-white text-gray-900 dark:bg-gray-900 dark:text-white">New</option>
                        <option value="in_progress" class="bg-white text-gray-900 dark:bg-gray-900 dark:text-white">In Progress</option>
                        <option value="resolved" class="bg-white text-gray-900 dark:bg-gray-900 dark:text-white">Resolved</option>
                        <option value="closed" class="bg-white text-gray-900 dark:bg-gray-900 dark:text-white">Closed</option>
                    </select>
                    <x-filament::button color="gray" wire:click="updateStatus">Update</x-filament::button>
                </div>
            </div>
        </section>

        <section class="space-y-4 lg:col-span-2">
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-white/5">
                <h2 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Conversation</h2>

                <div class="flex max-h-128 flex-col gap-3 overflow-y-auto pr-1">
                    @php($lastDay = null)
                    @php($prevSender = null)
                    @forelse ($this->record->messages as $message)
                        @php($currentDay = $message->created_at?->format('Y-m-d'))
                        @if ($currentDay !== $lastDay)
                            <div class="flex items-center gap-3 py-2 text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                <span class="h-px flex-1 bg-gray-200 dark:bg-white/10"></span>
                                <span>{{ $message->created_at?->format('M d, Y') }}</span>
                                <span class="h-px flex-1 bg-gray-200 dark:bg-white/10"></span>
                            </div>
                            @php($lastDay = $currentDay)
                            @php($prevSender = null)
                        @endif

                        @php($isAdmin = $message->sender_type === 'admin')
                        @php($isGroupStart = $prevSender !== $message->sender_type)
                        @php($initial = \Illuminate\Support\Str::of($message->sender_name ?? '?')->trim()->substr(0, 1)->upper())

                        <div @class([
                            'flex items-end gap-2',
                            'justify-end' => $isAdmin,
                            'justify-start' => ! $isAdmin,
                            'mt-2' => $isGroupStart,
                        ])>
                            @if (! $isAdmin)
                                @if ($isGroupStart)
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-200 text-xs font-semibold text-gray-700 dark:bg-white/10 dark:text-gray-200">
                                        {{ $initial }}
                                    </div>
                                @else
                                    <div class="h-8 w-8 shrink-0"></div>
                                @endif
                            @endif

                            <div class="flex max-w-[75%] flex-col {{ $isAdmin ? 'items-end' : 'items-start' }}">
                                @if ($isGroupStart)
                                    <p class="mb-1 px-1 text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                        {{ $message->sender_name }}
                                    </p>
                                @endif

                                <div @class([
                                    'px-3 py-2 text-sm leading-relaxed shadow-sm',
                                    'rounded-2xl rounded-br-sm bg-primary-600 text-white' => $isAdmin,
                                    'rounded-2xl rounded-bl-sm bg-gray-100 text-gray-900 dark:bg-white/10 dark:text-gray-100' => ! $isAdmin,
                                ])>
                                    <p class="whitespace-pre-line wrap-break-word">{{ $message->body }}</p>
                                </div>
                                <p class="mt-1 px-1 text-[11px] text-gray-500 dark:text-gray-400">
                                    {{ $message->created_at?->format('h:i A') }}
                                </p>
                            </div>

                            @if ($isAdmin)
                                @if ($isGroupStart)
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-600/20 text-xs font-semibold text-primary-700 dark:text-primary-300">
                                        {{ $initial }}
                                    </div>
                                @else
                                    <div class="h-8 w-8 shrink-0"></div>
                                @endif
                            @endif
                        </div>

                        @php($prevSender = $message->sender_type)
                    @empty
                        <div class="flex flex-col items-center justify-center gap-2 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                            <x-filament::icon icon="heroicon-o-chat-bubble-left-right" class="h-8 w-8 text-gray-300 dark:text-gray-600" />
                            <p>No messages yet.</p>
                            <p class="text-xs">Send a reply below to start the conversation.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-white/5">
                <label for="reply-message" class="mb-2 block text-sm font-semibold text-gray-900 dark:text-white">Reply to client</label>
                <textarea
                    id="reply-message"
                    wire:model.defer="replyMessage"
                    rows="4"
                    style="color-scheme: light dark;"
                    class="fi-input block w-full rounded-lg border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-white/20 dark:bg-white/5 dark:text-white"
                    placeholder="Type your message..."
                ></textarea>
                <div class="mt-3 flex justify-end">
                    <x-filament::button wire:click="sendReply" icon="heroicon-o-paper-airplane">
                        Send Reply
                    </x-filament::button>
                </div>
            </div>
        </section>
    </div>
</x-filament-panels::page>
