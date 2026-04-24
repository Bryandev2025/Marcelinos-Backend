<x-filament-panels::page wire:poll.180s="refreshHealth">
    @php
        $emailUsed = max(0, (int) $this->emailsSentToday);
        $emailLimit = max(0, (int) $this->mailDailyLimit);
        $hasEmailLimit = $emailLimit > 0;
        $emailPercent = $hasEmailLimit
            ? min(100, (int) round(($emailUsed / $emailLimit) * 100))
            : 0;

        $hostingerDaysLeft = $this->hostingerPlanDaysLeft();

        $tabs = [
            'overview' => ['label' => 'Overview', 'icon' => 'heroicon-m-squares-2x2'],
            'actions' => ['label' => 'Diagnostics', 'icon' => 'heroicon-m-cpu-chip'],
            'email' => ['label' => 'Email', 'icon' => 'heroicon-m-envelope'],
            'sms' => ['label' => 'SMS', 'icon' => 'heroicon-m-chat-bubble-left-right'],
            'maintenance' => ['label' => 'Maintenance', 'icon' => 'heroicon-m-wrench'],
            'payment' => ['label' => 'Payment', 'icon' => 'heroicon-m-credit-card'],
        ];
    @endphp

    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center justify-between gap-3">
                    <span class="inline-flex items-center gap-2">
                        <x-filament::icon icon="heroicon-m-adjustments-horizontal" class="h-5 w-5 text-primary-500" />
                        Platform settings
                    </span>

                    <x-filament::button
                        color="gray"
                        size="sm"
                        outlined
                        wire:click="refreshHealth"
                        icon="heroicon-m-arrow-path"
                    >
                        Refresh
                    </x-filament::button>
                </div>
            </x-slot>
            <x-slot name="description">
                Configure delivery channels, maintenance messaging, and payments. Health checks update automatically.
            </x-slot>

            <div class="flex flex-wrap items-center gap-2">
                @foreach ($tabs as $key => $tab)
                    <button
                        type="button"
                        wire:click="setTab('{{ $key }}')"
                        @class([
                            'inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition',
                            'bg-primary-600 text-white shadow-sm hover:bg-primary-500 dark:bg-primary-500 dark:hover:bg-primary-400' => $this->activeTab === $key,
                            'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-300 dark:hover:bg-white/10' => $this->activeTab !== $key,
                        ])
                    >
                        <x-filament::icon :icon="$tab['icon']" class="h-4 w-4" />
                        {{ $tab['label'] }}
                    </button>
                @endforeach
            </div>
        </x-filament::section>

        @if (count($this->alerts) > 0)
            <div class="space-y-3">
                @foreach ($this->alerts as $alert)
                    <x-filament::section>
                        <x-slot name="heading">
                            <span class="inline-flex items-center gap-2">
                                <x-filament::icon
                                    :icon="$alert['level'] === 'danger' ? 'heroicon-m-exclamation-circle' : 'heroicon-m-exclamation-triangle'"
                                    class="h-5 w-5 {{ $alert['level'] === 'danger' ? 'text-danger-500' : 'text-warning-500' }}"
                                />
                                {{ $alert['title'] }}
                            </span>
                        </x-slot>
                        <p class="text-sm text-gray-600 dark:text-gray-300">{{ $alert['detail'] }}</p>
                    </x-filament::section>
                @endforeach
            </div>
        @endif

        @if ($this->activeTab === 'overview')
            <div class="grid gap-6 lg:grid-cols-2">
                <x-filament::section>
                    <x-slot name="heading">
                        <span class="inline-flex items-center gap-2">
                            <x-filament::icon icon="heroicon-o-envelope" class="h-5 w-5 text-primary-500" />
                            Email delivery health
                        </span>
                    </x-slot>
                    <x-slot name="description">{{ $this->emailHealth }}</x-slot>

                    <div class="space-y-3">
                        <div class="flex items-end justify-between gap-3">
                            <div class="text-3xl font-semibold tabular-nums text-gray-900 dark:text-white">
                                {{ number_format($emailUsed) }}
                            </div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                @if ($hasEmailLimit)
                                    out of {{ number_format($emailLimit) }} limit
                                @else
                                    no fixed daily limit
                                @endif
                            </div>
                        </div>

                        <div class="h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-white/10">
                            <div
                                class="h-full rounded-full {{ $emailPercent >= 85 ? 'bg-danger-500' : 'bg-success-500' }}"
                                style="width: {{ $emailPercent }}%"
                            ></div>
                        </div>

                        @if ($hasEmailLimit)
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                <span class="font-medium">{{ $emailPercent }}%</span> daily quota consumed ·
                                <span class="font-medium">Credits left:</span> {{ number_format(max(0, (int) $this->emailsLeftToday)) }}
                            </div>
                        @endif
                    </div>
                </x-filament::section>

                <x-filament::section>
                    <x-slot name="heading">
                        <span class="inline-flex items-center gap-2">
                            <x-filament::icon icon="heroicon-o-device-phone-mobile" class="h-5 w-5 text-primary-500" />
                            SMS gateway status
                        </span>
                    </x-slot>
                    <x-slot name="description">{{ $this->smsHealth }}</x-slot>

                    <div class="space-y-1">
                        <div class="text-3xl font-semibold tabular-nums text-gray-900 dark:text-white">
                            {{ $this->smsCredits !== null ? number_format($this->smsCredits, 2) : 'N/A' }}
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Available credits</div>
                    </div>
                </x-filament::section>

                <x-filament::section>
                    <x-slot name="heading">
                        <span class="inline-flex items-center gap-2">
                            <x-filament::icon icon="heroicon-o-server-stack" class="h-5 w-5 text-primary-500" />
                            Hosting plan expiry
                        </span>
                    </x-slot>
                    <x-slot name="description">
                        {{ $hostingerDaysLeft === null ? 'No expiry date set' : ($hostingerDaysLeft <= 30 ? 'Renewal soon' : 'Active') }}
                    </x-slot>

                    <div class="space-y-1">
                        <div class="text-2xl font-semibold text-gray-900 dark:text-white">
                            {{ $this->hostingerPlanExpiryDisplay() }}
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            @if ($hostingerDaysLeft === null)
                                Set `HOSTINGER_PLAN_EXPIRES_AT` in .env
                            @elseif ($hostingerDaysLeft < 0)
                                Expired {{ abs($hostingerDaysLeft) }} day(s) ago
                            @else
                                {{ $hostingerDaysLeft }} day(s) left
                            @endif
                        </div>
                    </div>
                </x-filament::section>

                <x-filament::section class="lg:col-span-2">
                    <x-slot name="heading">
                        <span class="inline-flex items-center gap-2">
                            <x-filament::icon icon="heroicon-m-light-bulb" class="h-5 w-5 text-primary-500" />
                            Smart recommendations
                        </span>
                    </x-slot>
                    <ul class="space-y-2">
                        @foreach ($this->recommendations as $tip)
                            <li class="flex items-start gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <x-filament::icon icon="heroicon-m-check-circle" class="mt-0.5 h-4 w-4 text-success-500" />
                                <span>{{ $tip }}</span>
                            </li>
                        @endforeach
                    </ul>
                </x-filament::section>
            </div>
        @endif

        @if ($this->activeTab === 'actions')
            <div class="grid gap-6 md:grid-cols-2">
                <x-filament::section>
                    <x-slot name="heading">
                        <span class="inline-flex items-center gap-2">
                            <x-filament::icon icon="heroicon-o-paper-airplane" class="h-5 w-5 text-primary-500" />
                            Test connectivity
                        </span>
                    </x-slot>
                    <x-slot name="description">
                        Send a test email and ping Semaphore using your saved credentials.
                    </x-slot>

                    <div class="space-y-4">
                        <x-filament::input.wrapper>
                            <x-filament::input type="email" wire:model.defer="testEmailRecipient" placeholder="admin@domain.com" />
                        </x-filament::input.wrapper>

                        <div class="flex flex-wrap gap-2">
                            <x-filament::button wire:click="sendTestEmail">Dispatch email</x-filament::button>
                            <x-filament::button
                                color="info"
                                wire:click="testSmsConnectivity"
                                :disabled="$this->smsRateLimitedSeconds > 0 || $this->smsPingCooldownSeconds > 0"
                            >
                                @if ($this->smsRateLimitedSeconds > 0)
                                    Ping Semaphore (retry in {{ $this->smsRateLimitedSeconds }}s)
                                @elseif ($this->smsPingCooldownSeconds > 0)
                                    Ping Semaphore (cooldown {{ $this->smsPingCooldownSeconds }}s)
                                @else
                                    Ping Semaphore
                                @endif
                            </x-filament::button>
                        </div>

                        <p wire:loading wire:target="sendTestEmail,testSmsConnectivity" class="text-xs text-gray-500 dark:text-gray-400">
                            Executing request…
                        </p>
                    </div>
                </x-filament::section>

                <x-filament::section>
                    <x-slot name="heading">
                        <span class="inline-flex items-center gap-2">
                            <x-filament::icon icon="heroicon-o-bell-alert" class="h-5 w-5 text-primary-500" />
                            Alert thresholds
                        </span>
                    </x-slot>
                    <x-slot name="description">
                        When to show warnings in this page.
                    </x-slot>

                    <div class="space-y-4">
                        <div>
                            <div class="mb-2 flex items-center justify-between gap-2">
                                <label class="text-xs font-medium text-gray-700 dark:text-gray-200">Email warning percentage</label>
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $this->emailAlertThreshold }}%</span>
                            </div>
                            <input type="range" min="1" max="100" wire:model.blur="emailAlertThreshold" class="w-full" />
                        </div>

                        <x-filament::input.wrapper>
                            <x-filament::input type="number" min="0" step="0.01" wire:model.blur="smsLowCreditThreshold" placeholder="Low SMS credits threshold" />
                        </x-filament::input.wrapper>
                    </div>
                </x-filament::section>
            </div>
        @endif

        @if ($this->activeTab === 'email')
            <x-filament::section>
                <x-slot name="heading">
                    <span class="inline-flex items-center gap-2">
                        <x-filament::icon icon="heroicon-m-envelope" class="h-5 w-5 text-primary-500" />
                        SMTP configuration
                    </span>
                </x-slot>
                <x-slot name="description">
                    Primary + backup SMTP for failover.
                </x-slot>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="text-sm text-gray-600 dark:text-gray-300">
                        Status: <span class="font-medium">{{ $this->editingMail ? 'Editing' : 'Locked' }}</span>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        @if (! $this->editingMail)
                            <x-filament::button color="warning" outlined wire:click="enableMailEdit" icon="heroicon-m-lock-closed">
                                Unlock
                            </x-filament::button>
                        @else
                            <x-filament::button color="gray" outlined wire:click="cancelMailEdit">Cancel</x-filament::button>
                            <x-filament::button color="success" wire:click="saveMailSettings" icon="heroicon-m-check">Save</x-filament::button>
                        @endif
                    </div>
                </div>

                <div class="mt-6 grid gap-6 lg:grid-cols-2">
                    <x-filament::section>
                        <x-slot name="heading">Primary</x-slot>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-filament::input.wrapper>
                                <x-filament::input type="text" wire:model.defer="mailHost" :disabled="! $this->editingMail" placeholder="SMTP host" />
                            </x-filament::input.wrapper>

                            <x-filament::input.wrapper>
                                <x-filament::input type="text" wire:model.defer="mailPort" :disabled="! $this->editingMail" placeholder="Port" />
                            </x-filament::input.wrapper>

                            <x-filament::input.wrapper>
                                <x-filament::input type="text" wire:model.defer="mailEncryption" :disabled="! $this->editingMail" placeholder="Encryption (ssl/tls)" />
                            </x-filament::input.wrapper>

                            <x-filament::input.wrapper>
                                <x-filament::input type="email" wire:model.defer="mailUsername" :disabled="! $this->editingMail" placeholder="Username" />
                            </x-filament::input.wrapper>

                            <div class="sm:col-span-2">
                                <div class="flex items-center gap-2">
                                    <x-filament::input.wrapper class="flex-1">
                                        <x-filament::input
                                            :type="$this->showMailPassword ? 'text' : 'password'"
                                            wire:model.defer="mailPassword"
                                            :disabled="! $this->editingMail"
                                            placeholder="Password"
                                        />
                                    </x-filament::input.wrapper>
                                    @if ($this->editingMail)
                                        <x-filament::icon-button
                                            color="gray"
                                            outlined
                                            :icon="$this->showMailPassword ? 'heroicon-m-eye-slash' : 'heroicon-m-eye'"
                                            :label="$this->showMailPassword ? 'Hide password' : 'Show password'"
                                            wire:click="toggleMailPasswordVisibility"
                                        />
                                    @endif
                                </div>
                            </div>
                        </div>
                    </x-filament::section>

                    <x-filament::section>
                        <x-slot name="heading">Backup</x-slot>
                        <x-slot name="description">Used automatically when primary fails.</x-slot>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-filament::input.wrapper>
                                <x-filament::input type="text" wire:model.defer="mailBackupHost" :disabled="! $this->editingMail" placeholder="Backup SMTP host" />
                            </x-filament::input.wrapper>

                            <x-filament::input.wrapper>
                                <x-filament::input type="text" wire:model.defer="mailBackupPort" :disabled="! $this->editingMail" placeholder="Backup port" />
                            </x-filament::input.wrapper>

                            <x-filament::input.wrapper>
                                <x-filament::input type="text" wire:model.defer="mailBackupEncryption" :disabled="! $this->editingMail" placeholder="Backup encryption" />
                            </x-filament::input.wrapper>

                            <x-filament::input.wrapper>
                                <x-filament::input type="email" wire:model.defer="mailBackupUsername" :disabled="! $this->editingMail" placeholder="Backup username" />
                            </x-filament::input.wrapper>

                            <div class="sm:col-span-2">
                                <div class="flex items-center gap-2">
                                    <x-filament::input.wrapper class="flex-1">
                                        <x-filament::input
                                            :type="$this->showMailBackupPassword ? 'text' : 'password'"
                                            wire:model.defer="mailBackupPassword"
                                            :disabled="! $this->editingMail"
                                            placeholder="Backup password"
                                        />
                                    </x-filament::input.wrapper>
                                    @if ($this->editingMail)
                                        <x-filament::icon-button
                                            color="gray"
                                            outlined
                                            :icon="$this->showMailBackupPassword ? 'heroicon-m-eye-slash' : 'heroicon-m-eye'"
                                            :label="$this->showMailBackupPassword ? 'Hide password' : 'Show password'"
                                            wire:click="toggleMailBackupPasswordVisibility"
                                        />
                                    @endif
                                </div>
                            </div>
                        </div>
                    </x-filament::section>
                </div>

                <div class="mt-6 grid gap-4 sm:grid-cols-3">
                    <x-filament::input.wrapper>
                        <x-filament::input type="number" min="0" wire:model.defer="mailDailyLimit" :disabled="! $this->editingMail" placeholder="Daily limit (0 = unlimited)" />
                    </x-filament::input.wrapper>
                    <x-filament::input.wrapper>
                        <x-filament::input type="email" wire:model.defer="mailFromAddress" :disabled="! $this->editingMail" placeholder="From address" />
                    </x-filament::input.wrapper>
                    <x-filament::input.wrapper>
                        <x-filament::input type="text" wire:model.defer="mailFromName" :disabled="! $this->editingMail" placeholder="From name" />
                    </x-filament::input.wrapper>
                </div>
            </x-filament::section>
        @endif

        @if ($this->activeTab === 'sms')
            <x-filament::section>
                <x-slot name="heading">
                    <span class="inline-flex items-center gap-2">
                        <x-filament::icon icon="heroicon-m-chat-bubble-left-right" class="h-5 w-5 text-primary-500" />
                        Semaphore gateway
                    </span>
                </x-slot>
                <x-slot name="description">
                    Configure the API key and sender identity.
                </x-slot>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="text-sm text-gray-600 dark:text-gray-300">
                        Status: <span class="font-medium">{{ $this->editingSms ? 'Editing' : 'Locked' }}</span>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        @if (! $this->editingSms)
                            <x-filament::button color="warning" outlined wire:click="enableSmsEdit" icon="heroicon-m-lock-closed">
                                Unlock
                            </x-filament::button>
                        @else
                            <x-filament::button color="gray" outlined wire:click="cancelSmsEdit">Cancel</x-filament::button>
                            <x-filament::button color="success" wire:click="saveSmsSettings" icon="heroicon-m-check">Save</x-filament::button>
                        @endif
                    </div>
                </div>

                <div class="mt-6 grid gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <div class="flex items-center gap-2">
                            <x-filament::input.wrapper class="flex-1">
                                <x-filament::input
                                    :type="$this->showSmsApiKey ? 'text' : 'password'"
                                    wire:model.defer="semaphoreApiKey"
                                    :disabled="! $this->editingSms"
                                    placeholder="Semaphore API key"
                                />
                            </x-filament::input.wrapper>
                            @if ($this->editingSms)
                                <x-filament::icon-button
                                    color="gray"
                                    outlined
                                    :icon="$this->showSmsApiKey ? 'heroicon-m-eye-slash' : 'heroicon-m-eye'"
                                    :label="$this->showSmsApiKey ? 'Hide key' : 'Show key'"
                                    wire:click="toggleSmsApiKeyVisibility"
                                />
                            @endif
                        </div>
                    </div>

                    <x-filament::input.wrapper>
                        <x-filament::input type="url" wire:model.defer="semaphoreOtpUrl" :disabled="! $this->editingSms" placeholder="OTP endpoint URL" />
                    </x-filament::input.wrapper>

                    <x-filament::input.wrapper>
                        <x-filament::input type="text" maxlength="11" wire:model.defer="semaphoreSenderName" :disabled="! $this->editingSms" placeholder="Sender name (max 11 chars)" />
                    </x-filament::input.wrapper>
                </div>
            </x-filament::section>
        @endif

        @if ($this->activeTab === 'maintenance')
            <x-filament::section>
                <x-slot name="heading">
                    <span class="inline-flex items-center gap-2">
                        <x-filament::icon icon="heroicon-m-wrench" class="h-5 w-5 text-primary-500" />
                        Maintenance mode
                    </span>
                </x-slot>
                <x-slot name="description">
                    Control the public maintenance gateway message.
                </x-slot>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="text-sm text-gray-600 dark:text-gray-300">
                        Status: <span class="font-medium">{{ $this->editingMaintenance ? 'Editing' : 'Viewing' }}</span>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        @if (! $this->editingMaintenance)
                            <x-filament::button color="primary" outlined wire:click="enableMaintenanceEdit" icon="heroicon-m-pencil-square">
                                Edit
                            </x-filament::button>
                        @else
                            <x-filament::button color="gray" outlined wire:click="cancelMaintenanceEdit">Cancel</x-filament::button>
                            <x-filament::button color="success" wire:click="saveMaintenanceSettings" icon="heroicon-m-check">Save</x-filament::button>
                        @endif
                    </div>
                </div>

                <div class="mt-6 space-y-6">
                    @if (! $this->editingMaintenance)
                        <div class="grid gap-4 md:grid-cols-3">
                            <x-filament::section>
                                <x-slot name="heading">Gateway</x-slot>
                                <div class="text-sm text-gray-600 dark:text-gray-300">
                                    <span class="font-medium {{ $this->maintenanceModeEnabled ? 'text-danger-600 dark:text-danger-400' : 'text-success-600 dark:text-success-400' }}">
                                        {{ $this->maintenanceModeEnabled ? 'Enabled' : 'Disabled' }}
                                    </span>
                                </div>
                            </x-filament::section>

                            <x-filament::section>
                                <x-slot name="heading">Layout</x-slot>
                                <div class="text-sm text-gray-600 dark:text-gray-300">
                                    {{ \App\Support\MaintenancePageVariant::labels()[$this->maintenanceVariant] ?? $this->maintenanceVariant }}
                                </div>
                            </x-filament::section>

                            <x-filament::section>
                                <x-slot name="heading">ETA</x-slot>
                                <div class="text-sm text-gray-600 dark:text-gray-300">
                                    {{ $this->maintenanceEta !== '' ? $this->maintenanceEta : '—' }}
                                </div>
                            </x-filament::section>
                        </div>
                    @else
                        <div class="flex flex-wrap gap-2">
                            <x-filament::button color="warning" outlined wire:click="applyMaintenancePreset('quick-fix')">Quick fix</x-filament::button>
                            <x-filament::button color="warning" outlined wire:click="applyMaintenancePreset('scheduled-upgrade')">Scheduled upgrade</x-filament::button>
                            <x-filament::button color="danger" outlined wire:click="applyMaintenancePreset('emergency')">Emergency</x-filament::button>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="flex items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm dark:border-white/10 dark:bg-gray-900">
                                <span class="font-medium text-gray-900 dark:text-white">Enable maintenance gateway</span>
                                <input type="checkbox" wire:model.defer="maintenanceModeEnabled" class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-white/20 dark:bg-gray-950" />
                            </label>

                            <x-filament::input.wrapper>
                                <select wire:model.defer="maintenanceVariant" class="fi-input block w-full rounded-lg border border-gray-300 bg-white py-2 ps-3 pe-8 text-sm shadow-sm transition duration-75 focus:border-primary-600 focus:ring-2 focus:ring-primary-600/20 dark:border-white/10 dark:bg-gray-900 dark:text-white dark:focus:border-primary-500 dark:focus:ring-primary-500/20">
                                    @foreach (\App\Support\MaintenancePageVariant::labels() as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </x-filament::input.wrapper>

                            <x-filament::input.wrapper>
                                <x-filament::input type="text" wire:model.defer="maintenanceBadge" placeholder="Badge" />
                            </x-filament::input.wrapper>

                            <x-filament::input.wrapper>
                                <x-filament::input type="text" wire:model.defer="maintenanceEta" placeholder="ETA (optional)" />
                            </x-filament::input.wrapper>

                            <div class="md:col-span-2">
                                <x-filament::input.wrapper>
                                    <x-filament::input type="text" wire:model.defer="maintenanceTitle" placeholder="Title" />
                                </x-filament::input.wrapper>
                            </div>

                            <div class="md:col-span-2">
                                <x-filament::input.wrapper>
                                    <textarea rows="4" wire:model.defer="maintenanceDescription" class="fi-textarea block w-full rounded-lg border border-gray-300 bg-white py-2 px-3 text-sm shadow-sm transition duration-75 focus:border-primary-600 focus:ring-2 focus:ring-primary-600/20 dark:border-white/10 dark:bg-gray-900 dark:text-white dark:focus:border-primary-500 dark:focus:ring-primary-500/20"></textarea>
                                </x-filament::input.wrapper>
                            </div>
                        </div>
                    @endif
                </div>
            </x-filament::section>
        @endif

        @if ($this->activeTab === 'payment')
            <x-filament::section>
                <x-slot name="heading">
                    <span class="inline-flex items-center gap-2">
                        <x-filament::icon icon="heroicon-m-credit-card" class="h-5 w-5 text-primary-500" />
                        Payment settings
                    </span>
                </x-slot>
                <x-slot name="description">
                    Xendit keys, deposits, and cancellation fee rules.
                </x-slot>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="text-sm text-gray-600 dark:text-gray-300">
                        Status: <span class="font-medium">{{ $this->editingPayment ? 'Editing' : 'Viewing' }}</span>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        @if (! $this->editingPayment)
                            <x-filament::button color="primary" outlined wire:click="enablePaymentEdit" icon="heroicon-m-pencil-square">
                                Edit
                            </x-filament::button>
                        @else
                            <x-filament::button color="gray" outlined wire:click="cancelPaymentEdit">Cancel</x-filament::button>
                            <x-filament::button color="success" wire:click="savePaymentSettings" icon="heroicon-m-check">Save</x-filament::button>
                        @endif
                    </div>
                </div>

                <div class="mt-6 space-y-6">
                    @php
                        $xenditConfigured = trim((string) $this->xenditSecretKey) !== '' && trim((string) $this->xenditPublicKey) !== '';
                    @endphp

                    @if (! $this->editingPayment)
                        <div class="grid gap-4 md:grid-cols-3">
                            <x-filament::section>
                                <x-slot name="heading">Checkout status</x-slot>
                                <div class="text-sm font-medium {{ $this->onlinePaymentEnabled ? 'text-success-600 dark:text-success-400' : 'text-gray-700 dark:text-gray-300' }}">
                                    {{ $this->onlinePaymentEnabled ? 'Live Payments Active' : 'Offline Mode Only' }}
                                </div>
                            </x-filament::section>

                            <x-filament::section>
                                <x-slot name="heading">Deposit structure</x-slot>
                                <div class="text-sm text-gray-700 dark:text-gray-300">
                                    <span class="font-semibold">{{ (int) $this->partialPaymentSelection }}%</span>
                                    @if ($this->allowCustomPartialPayment)
                                        <span class="text-xs text-gray-500 dark:text-gray-400">· custom allowed</span>
                                    @endif
                                </div>
                            </x-filament::section>

                            <x-filament::section>
                                <x-slot name="heading">Payment setting</x-slot>
                                <div class="text-sm text-gray-700 dark:text-gray-300">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $xenditConfigured ? 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-300' : 'bg-danger-50 text-danger-700 dark:bg-danger-500/15 dark:text-danger-300' }}">
                                        {{ $xenditConfigured ? 'Configured' : 'Pending keys' }}
                                    </span>
                                </div>
                            </x-filament::section>
                        </div>

                        <x-filament::section>
                            <x-slot name="heading">Cancellation deduction rule</x-slot>
                            <x-slot name="description">Deduct from booking total (capped by amount already paid).</x-slot>

                            <div class="text-sm text-gray-700 dark:text-gray-300">
                                Current: <span class="font-semibold">{{ (int) $this->cancellationFeePercent }}%</span>
                            </div>
                        </x-filament::section>
                    @else
                        <label class="flex items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm dark:border-white/10 dark:bg-gray-900">
                            <span class="font-medium text-gray-900 dark:text-white">Activate front-end payments</span>
                            <input type="checkbox" wire:model.defer="onlinePaymentEnabled" class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-white/20 dark:bg-gray-950" />
                        </label>

                        <x-filament::section>
                            <x-slot name="heading">Partial payment percentage</x-slot>
                            <x-slot name="description">Select one deposit option. Full payment (100%) is intentionally excluded.</x-slot>

                            <div class="space-y-4">
                                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                    <input type="checkbox" wire:model.live="allowCustomPartialPayment" class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-white/20 dark:bg-gray-950" />
                                    Allow custom deposit inputs
                                </label>

                                @if (! $this->allowCustomPartialPayment)
                                    <div class="grid gap-2 sm:grid-cols-3 lg:grid-cols-5">
                                        @foreach ([10, 20, 30, 40, 50, 60, 70, 80, 90] as $option)
                                            <label class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900">
                                                <input
                                                    type="radio"
                                                    value="{{ $option }}"
                                                    wire:model.defer="partialPaymentSelection"
                                                    class="h-4 w-4 border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-white/20 dark:bg-gray-950"
                                                />
                                                <span class="font-medium text-gray-800 dark:text-gray-200">{{ $option }}%</span>
                                            </label>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="max-w-xs">
                                        <x-filament::input.wrapper>
                                            <x-filament::input
                                                type="number"
                                                min="1"
                                                max="99"
                                                wire:model.defer="partialPaymentSelection"
                                                placeholder="e.g. 25"
                                            />
                                        </x-filament::input.wrapper>
                                    </div>
                                @endif
                            </div>
                        </x-filament::section>

                        <x-filament::section>
                            <x-slot name="heading">Cancellation deduction percentage</x-slot>
                            <x-slot name="description">Cancellation fee is based on booking total. Collected amount is capped by what the guest has already paid.</x-slot>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="max-w-xs">
                                    <x-filament::input.wrapper>
                                        <x-filament::input type="number" min="0" max="100" wire:model.defer="cancellationFeePercent" placeholder="e.g. 30" />
                                    </x-filament::input.wrapper>
                                </div>

                                <div class="text-sm text-gray-600 dark:text-gray-300">
                                    <div class="font-medium text-gray-900 dark:text-white">Rule preview</div>
                                    <div class="mt-1">
                                        Example: if total is ₱10,000 and deduction is {{ (int) $this->cancellationFeePercent }}%, fee is ₱{{ number_format((10000 * (int) $this->cancellationFeePercent) / 100, 2) }}.
                                    </div>
                                </div>
                            </div>
                        </x-filament::section>
                    @endif

                    <div class="grid gap-4 md:grid-cols-2">
                        <x-filament::input.wrapper>
                            <x-filament::input type="text" wire:model.defer="xenditPublicKey" :disabled="! $this->editingPayment" placeholder="Xendit public key" />
                        </x-filament::input.wrapper>
                        <x-filament::input.wrapper>
                            <x-filament::input type="password" wire:model.defer="xenditSecretKey" :disabled="! $this->editingPayment" placeholder="Xendit secret key" />
                        </x-filament::input.wrapper>
                        <div class="md:col-span-2">
                            <x-filament::input.wrapper>
                                <x-filament::input type="password" wire:model.defer="xenditWebhookToken" :disabled="! $this->editingPayment" placeholder="Webhook token" />
                            </x-filament::input.wrapper>
                        </div>
                    </div>

                    <x-filament::section :collapsible="true" :collapsed="true">
                        <x-slot name="heading">
                            Webhook diagnostics
                        </x-slot>
                        <x-slot name="description">
                            Shows the most recent Xendit webhook event processed by the system.
                        </x-slot>

                        <div class="flex items-center justify-between gap-2">
                            <div class="text-sm text-gray-600 dark:text-gray-300">
                                Last event: {{ $this->lastXenditWebhookEvent ? 'available' : 'none yet' }}
                            </div>
                            <x-filament::button color="info" outlined size="sm" wire:click="refreshPaymentDebug">
                                Refresh
                            </x-filament::button>
                        </div>

                        <div class="mt-4">
                            @if ($this->lastXenditWebhookEvent)
                                <div class="grid gap-3 md:grid-cols-3">
                                    @foreach ([
                                        'received_at' => 'Received at',
                                        'raw_status' => 'Response code',
                                        'result' => 'Resolution',
                                        'booking_reference' => 'Booking ref',
                                        'booking_status' => 'Booking status',
                                        'paid_amount' => 'Paid amount',
                                        'invoice_id' => 'Invoice ID',
                                        'external_id' => 'External ID',
                                    ] as $k => $label)
                                        <div class="rounded-xl border border-gray-200 bg-white p-3 text-sm dark:border-white/10 dark:bg-gray-900">
                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</div>
                                            <div class="mt-1 font-medium text-gray-900 dark:text-white break-words">
                                                @if ($k === 'paid_amount')
                                                    ₱{{ number_format((float) ($this->lastXenditWebhookEvent[$k] ?? 0), 2) }}
                                                @else
                                                    {{ $this->lastXenditWebhookEvent[$k] ?? '—' }}
                                                @endif
                                            </div>
                                            @if ($k === 'result' && ! empty($this->lastXenditWebhookEvent['reason']))
                                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                    Reason: {{ $this->lastXenditWebhookEvent['reason'] }}
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="rounded-xl border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
                                    No webhook event captured yet.
                                </div>
                            @endif
                        </div>
                    </x-filament::section>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
