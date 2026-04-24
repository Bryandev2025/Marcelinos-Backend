<x-filament-panels::page>
    <div class="space-y-8 max-w-3xl">
        <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-6">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Profile</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Update your account details.</p>

            <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">Name</label>
                    <input type="text" wire:model.defer="name" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-950" />
                    @error('name') <p class="text-sm text-danger-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">Email</label>
                    <input type="email" wire:model.defer="email" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-950" />
                    @error('email') <p class="text-sm text-danger-600 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="mt-5">
                <x-filament::button wire:click="saveProfile" color="primary" class="!rounded-lg">
                    Save profile
                </x-filament::button>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-6">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Change password</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Choose a strong password you don’t use elsewhere.</p>

            <div class="mt-6 grid grid-cols-1 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">Current password</label>
                    <input type="password" wire:model.defer="currentPassword" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-950" />
                    @error('currentPassword') <p class="text-sm text-danger-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">New password</label>
                        <input type="password" wire:model.defer="password" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-950" />
                        @error('password') <p class="text-sm text-danger-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">Confirm new password</label>
                        <input type="password" wire:model.defer="passwordConfirmation" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-950" />
                        @error('passwordConfirmation') <p class="text-sm text-danger-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="mt-5">
                <x-filament::button wire:click="changePassword" color="warning" class="!rounded-lg">
                    Update password
                </x-filament::button>
            </div>
        </div>
    </div>
</x-filament-panels::page>

