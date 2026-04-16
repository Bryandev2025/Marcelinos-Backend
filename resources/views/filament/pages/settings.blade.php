<x-filament-panels::page wire:poll.75s="refreshHealth">
    @php
        $emailUsed = max(0, (int) $this->emailsSentToday);
        $emailLimit = max(1, (int) $this->mailDailyLimit);
        $emailPercent = min(100, (int) round(($emailUsed / $emailLimit) * 100));
        $emailOnline = str_starts_with(strtolower((string) $this->emailHealth), 'online');
        $smsOnline = str_starts_with(strtolower((string) $this->smsHealth), 'online');
        
        $tabMeta = [
            'overview' => [
                'label' => 'Dashboard Overview',
                'icon' => 'heroicon-m-squares-2x2',
                'desc' => 'System health & metrics',
                'color' => 'text-indigo-600 dark:text-indigo-400',
                'bg' => 'bg-indigo-50 dark:bg-indigo-500/10',
                'active_border' => 'border-indigo-500',
                'active_glow' => 'rgba(99, 102, 241, 0.15)',
            ],
            'actions' => [
                'label' => 'Diagnostics & Ops',
                'icon' => 'heroicon-m-cpu-chip',
                'desc' => 'Run connectivity tests',
                'color' => 'text-sky-600 dark:text-sky-400',
                'bg' => 'bg-sky-50 dark:bg-sky-500/10',
                'active_border' => 'border-sky-500',
                'active_glow' => 'rgba(14, 165, 233, 0.15)',
            ],
            'email' => [
                'label' => 'Email Configuration',
                'icon' => 'heroicon-m-envelope',
                'desc' => 'SMTP & mail quotas',
                'color' => 'text-emerald-600 dark:text-emerald-400',
                'bg' => 'bg-emerald-50 dark:bg-emerald-500/10',
                'active_border' => 'border-emerald-500',
                'active_glow' => 'rgba(16, 185, 129, 0.15)',
            ],
            'sms' => [
                'label' => 'SMS Gateway',
                'icon' => 'heroicon-m-chat-bubble-left-right',
                'desc' => 'Manage Semaphore API',
                'color' => 'text-purple-600 dark:text-purple-400',
                'bg' => 'bg-purple-50 dark:bg-purple-500/10',
                'active_border' => 'border-purple-500',
                'active_glow' => 'rgba(168, 85, 247, 0.15)',
            ],
            'maintenance' => [
                'label' => 'Maintenance Mode',
                'icon' => 'heroicon-m-wrench',
                'desc' => 'Control public access',
                'color' => 'text-rose-600 dark:text-rose-400',
                'bg' => 'bg-rose-50 dark:bg-rose-500/10',
                'active_border' => 'border-rose-500',
                'active_glow' => 'rgba(244, 63, 94, 0.15)',
            ],
            'payment' => [
                'label' => 'Payment Processing',
                'icon' => 'heroicon-m-credit-card',
                'desc' => 'Xendit API & Webhooks',
                'color' => 'text-amber-600 dark:text-amber-400',
                'bg' => 'bg-amber-50 dark:bg-amber-500/10',
                'active_border' => 'border-amber-500',
                'active_glow' => 'rgba(245, 158, 11, 0.15)',
            ],
        ];
        $currentTab = $tabMeta[$this->activeTab] ?? $tabMeta['overview'];
    @endphp

    <style>
        .premium-settings {
            color: inherit;
        }

        .page-hero-card,
        .premium-card {
            background: rgb(255 255 255 / 1);
            border: 1px solid rgb(226 232 240 / 1);
            border-radius: 0.75rem;
            box-shadow: 0 1px 2px rgb(15 23 42 / 0.06);
        }

        .dark .page-hero-card,
        .dark .premium-card {
            background: rgb(15 23 42 / 1);
            border-color: rgb(51 65 85 / 0.7);
        }

        .section-shell {
            border-top: 3px solid rgb(131 160 112 / 0.45);
        }

        .premium-input {
            width: 100%;
            border: 1px solid rgb(203 213 225 / 1);
            border-radius: 0.625rem;
            background: rgb(255 255 255 / 1);
            color: inherit;
            padding: 0.625rem 0.875rem;
            font-size: 0.875rem;
            line-height: 1.25rem;
        }

        .dark .premium-input {
            border-color: rgb(71 85 105 / 0.75);
            background: rgb(15 23 42 / 1);
        }

        .premium-input:focus {
            outline: none;
            border-color: rgb(131 160 112 / 1);
            box-shadow: 0 0 0 3px rgb(131 160 112 / 0.2);
        }

        .premium-input:disabled {
            opacity: 0.75;
            cursor: not-allowed;
        }

        .pulse-indicator {
            display: inline-flex;
            width: 0.5rem;
            height: 0.5rem;
            border-radius: 9999px;
        }

        .settings-tabs {
            scrollbar-width: none;
        }

        .settings-tabs::-webkit-scrollbar {
            display: none;
        }

        .progress-bar-animated {
            transition: width 0.3s ease;
        }
    </style>

    <div class="premium-settings w-full">
        <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6 pb-10 sm:pb-12">
        
        <!-- Hero Header Area -->
        <div class="page-hero-card section-shell p-5 sm:p-7 lg:p-8">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white flex items-center gap-3">
                        <div class="p-2.5 bg-[#83A070]/15 dark:bg-[#83A070]/20 rounded-xl text-[#618753] dark:text-[#9bb78b]">
                            <x-filament::icon icon="heroicon-m-adjustments-horizontal" class="h-7 w-7" />
                        </div>
                        Platform Settings
                    </h1>
                    <p class="mt-3 text-sm sm:text-base text-gray-600 dark:text-gray-400 max-w-2xl leading-relaxed">
                        Manage infrastructure, delivery channels, maintenance messaging, and payments in one professional control center.
                    </p>
                </div>
                
                <div class="flex flex-col gap-3 w-full sm:w-auto sm:min-w-[240px] min-w-0">
                    <div class="premium-card p-4 rounded-xl flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1">Status Overview</p>
                            @if ($emailOnline && $smsOnline)
                                <div class="flex items-center gap-2">
                                    <span class="pulse-indicator text-emerald-500 bg-emerald-500"></span>
                                    <span class="text-sm font-bold text-emerald-600 dark:text-emerald-400">All Systems Online</span>
                                </div>
                            @else
                                <div class="flex items-center gap-2">
                                    <span class="pulse-indicator text-rose-500 bg-rose-500"></span>
                                    <span class="text-sm font-bold text-rose-600 dark:text-rose-400">Degraded Service</span>
                                </div>
                            @endif
                        </div>
                        <x-filament::button size="sm" color="gray" wire:click="refreshHealth" icon="heroicon-m-arrow-path" class="!rounded-lg" title="Force Refresh Status">
                            Refresh
                        </x-filament::button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Horizontal Tabs Row -->
        <div class="w-full overflow-x-auto settings-tabs pb-2 -mx-4 sm:mx-0">
            <nav class="flex gap-2 bg-white dark:bg-slate-900 p-1.5 rounded-xl border border-gray-200 dark:border-slate-700 w-max min-w-full sm:w-full sm:flex-wrap sm:justify-start px-4 sm:px-1.5 shadow-sm">
                @foreach ($tabMeta as $tabKey => $tabInfo)
                    <button type="button"
                        wire:click="setTab('{{ $tabKey }}')"
                        class="flex-shrink-0 whitespace-nowrap flex items-center gap-2.5 px-3 sm:px-4 py-2.5 sm:py-3 rounded-lg font-semibold text-sm transition-all duration-200 {{ $this->activeTab === $tabKey ? 'bg-[#83A070]/15 text-[#618753] dark:bg-[#83A070]/20 dark:text-[#b4cca7] border border-[#83A070]/40' : 'text-gray-500 hover:text-gray-900 hover:bg-gray-50 dark:text-gray-400 dark:hover:text-white dark:hover:bg-gray-700/50 border border-transparent' }}">
                        <x-filament::icon :icon="$tabInfo['icon']" class="h-5 w-5" />
                        {{ $tabInfo['label'] }}
                    </button>
                @endforeach
            </nav>
        </div>

        <!-- Main Content Area -->
        <main class="w-full min-w-0 pb-16">
            <!-- Intelligent Alerts -->
            @if (count($this->alerts) > 0)
                <div class="space-y-4 mb-8">
                    @foreach ($this->alerts as $alert)
                        <div class="rounded-xl p-4 flex items-start gap-4 border shadow-sm
                            {{ $alert['level'] === 'danger' ? 'border-rose-200 bg-rose-50 dark:border-rose-500/20 dark:bg-rose-500/10' : 'border-amber-200 bg-amber-50 dark:border-amber-500/20 dark:bg-amber-500/10' }}">
                            <div class="flex-shrink-0 mt-0.5 p-2 rounded-full {{ $alert['level'] === 'danger' ? 'bg-rose-100 text-rose-600 dark:bg-rose-500/20 dark:text-rose-400' : 'bg-amber-100 text-amber-600 dark:bg-amber-500/20 dark:text-amber-400' }}">
                                <x-filament::icon :icon="$alert['level'] === 'danger' ? 'heroicon-m-exclamation-circle' : 'heroicon-m-exclamation-triangle'" class="h-6 w-6" />
                            </div>
                            <div class="flex-1">
                                <h3 class="text-base font-bold tracking-tight {{ $alert['level'] === 'danger' ? 'text-rose-900 dark:text-rose-300' : 'text-amber-900 dark:text-amber-300' }}">{{ $alert['title'] }}</h3>
                                <div class="mt-1 text-sm font-medium {{ $alert['level'] === 'danger' ? 'text-rose-700 dark:text-rose-400' : 'text-amber-700 dark:text-amber-400' }}">{{ $alert['detail'] }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <div>
                <!-- Section Header -->
                <div class="mb-6 flex flex-col md:flex-row md:items-center justify-between border-b border-gray-200 dark:border-gray-800 pb-4 gap-4">
                    <div>
                        <h2 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white flex items-center gap-3">
                            <span class="{{ $currentTab['color'] }} p-2 {{ $currentTab['bg'] }} rounded-xl">
                                <x-filament::icon :icon="$currentTab['icon']" class="h-6 w-6" />
                            </span>
                            {{ $currentTab['label'] }}
                        </h2>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400 font-medium">{{ $currentTab['desc'] }}</p>
                    </div>
                </div>

                <!-- DASHBOARD OVERVIEW TAB -->
                @if ($this->activeTab === 'overview')
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Email KPI -->
                        <div class="premium-card p-6 flex flex-col justify-between">
                            <div class="flex justify-between items-start mb-6">
                                <div class="p-3 bg-blue-50 dark:bg-blue-500/10 rounded-2xl">
                                    <x-filament::icon icon="heroicon-o-envelope" class="h-7 w-7 text-blue-500" />
                                </div>
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold {{ $emailOnline ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400' : 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-400' }}">
                                    {{ $this->emailHealth }}
                                </span>
                            </div>
                            <dt class="text-sm font-semibold uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1">Email Delivery Health</dt>
                            
                            <div class="mt-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl p-4 border border-gray-100 dark:border-gray-800">
                                <div class="flex justify-between items-end mb-2">
                                    <span class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($emailUsed) }}</span>
                                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">out of {{ number_format($emailLimit) }} limit</span>
                                </div>
                                <div class="h-3 w-full bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                    <div class="progress-bar-animated h-full rounded-full {{ $emailPercent > 85 ? 'bg-rose-500' : 'bg-emerald-500' }}"
                                         style="width: {{ $emailPercent }}%"></div>
                                </div>
                                <p class="mt-2 text-[11px] font-bold uppercase tracking-widest {{ $emailPercent > 85 ? 'text-rose-600 dark:text-rose-400' : 'text-gray-500 dark:text-gray-400' }}">
                                    {{ $emailPercent }}% Daily Quota Consumed
                                </p>
                            </div>
                        </div>
                        
                        <!-- SMS KPI -->
                        <div class="premium-card p-6 flex flex-col justify-between">
                            <div class="flex justify-between items-start mb-6">
                                <div class="p-3 bg-purple-50 dark:bg-purple-500/10 rounded-2xl">
                                    <x-filament::icon icon="heroicon-o-device-phone-mobile" class="h-7 w-7 text-purple-500" />
                                </div>
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold {{ $smsOnline ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400' : 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-400' }}">
                                    {{ $this->smsHealth }}
                                </span>
                            </div>
                            <dt class="text-sm font-semibold uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1">SMS Gateway Status</dt>
                            
                            <div class="mt-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl p-4 border border-gray-100 dark:border-gray-800 flex flex-col justify-center gap-1 h-[104px]">
                                <span class="text-3xl font-bold text-gray-900 dark:text-white">
                                    {{ $this->smsCredits !== null ? number_format($this->smsCredits, 2) : 'N/A' }}
                                </span>
                                <span class="text-xs font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Available Credits</span>
                            </div>
                        </div>

                        <!-- Recommendations -->
                        <div class="premium-card p-6 lg:col-span-2">
                            <div class="flex items-center gap-3 mb-4">
                                <div class="p-2 bg-amber-50 dark:bg-amber-500/10 rounded-lg">
                                    <x-filament::icon icon="heroicon-m-light-bulb" class="h-5 w-5 text-amber-500" />
                                </div>
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white">Smart Recommendations</h3>
                            </div>
                            <ul class="space-y-3">
                                @foreach ($this->recommendations as $tip)
                                    <li class="flex items-start gap-3 p-3 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                        <div class="mt-0.5 flex-shrink-0">
                                            <x-filament::icon icon="heroicon-m-check-circle" class="h-5 w-5 text-emerald-500" />
                                        </div>
                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300 leading-relaxed">{{ $tip }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif

                <!-- ACTIONS / DIAGNOSTICS TAB -->
                @if ($this->activeTab === 'actions')
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="premium-card p-8">
                            <div class="w-12 h-12 bg-sky-100 dark:bg-sky-500/20 rounded-2xl flex items-center justify-center mb-6">
                                <x-filament::icon icon="heroicon-o-paper-airplane" class="h-6 w-6 text-sky-600 dark:text-sky-400" />
                            </div>
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">Test Connectivity</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 font-medium mb-6 leading-relaxed">
                                Dispatch test packets to your configured gateways to ensure credentials and network paths are clear.
                            </p>
                            
                            <div class="space-y-4">
                                <div class="relative">
                                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">Test Recipient Address</label>
                                    <input type="email" wire:model.defer="testEmailRecipient" class="premium-input pl-10" placeholder="admin@domain.com" />
                                    <x-filament::icon icon="heroicon-m-at-symbol" class="absolute left-3.5 top-[2.1rem] h-5 w-5 text-gray-400" />
                                </div>
                                
                                <div class="grid grid-cols-2 gap-3 pt-4 border-t border-gray-100 dark:border-gray-800">
                                    <x-filament::button wire:click="sendTestEmail" color="primary" class="w-full !rounded-xl !py-2.5 shadow-md">
                                        Dispatch Email
                                    </x-filament::button>
                                    <x-filament::button wire:click="testSmsConnectivity" color="info" class="w-full !rounded-xl !py-2.5 shadow-md">
                                        Ping Semaphore
                                    </x-filament::button>
                                </div>
                                <p wire:loading wire:target="sendTestEmail,testSmsConnectivity" class="text-xs font-semibold text-center text-emerald-600 dark:text-emerald-400 mt-2 w-full">
                                    Executing Request...
                                </p>
                            </div>
                        </div>
                        
                        <div class="premium-card p-8">
                            <div class="w-12 h-12 bg-indigo-100 dark:bg-indigo-500/20 rounded-2xl flex items-center justify-center mb-6">
                                <x-filament::icon icon="heroicon-o-bell-alert" class="h-6 w-6 text-indigo-600 dark:text-indigo-400" />
                            </div>
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">Alert Thresholds</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 font-medium mb-6 leading-relaxed">
                                Define the limits at which the system will automatically push warnings to the dashboard interface.
                            </p>
                            
                            <div class="space-y-5">
                                <div>
                                    <div class="flex justify-between items-center mb-2">
                                        <label class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Email Warning Percentage</label>
                                        <span class="text-xs font-bold text-indigo-600 dark:text-indigo-400">{{ $this->emailAlertThreshold }}%</span>
                                    </div>
                                    <input type="range" min="1" max="100" wire:model.blur="emailAlertThreshold" class="w-full h-2 bg-gray-200 dark:bg-gray-700 rounded-lg appearance-none cursor-pointer accent-indigo-600" />
                                </div>
                                
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">Low SMS Credits Mark</label>
                                    <div class="relative">
                                        <span class="absolute left-4 top-[11px] text-gray-500 font-bold">₱</span>
                                        <input type="number" min="0" step="0.01" wire:model.blur="smsLowCreditThreshold" class="premium-input pl-8" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- EMAIL SETUP TAB -->
                @if ($this->activeTab === 'email')
                    <div class="premium-card p-6 md:p-8">
                        <div class="flex flex-col sm:flex-row justify-between sm:items-center mb-8 pb-6 border-b border-gray-100 dark:border-gray-800 gap-4">
                            <div>
                                <h3 class="text-xl font-bold text-gray-900 dark:text-white">SMTP Credentials</h3>
                                <p class="text-sm text-gray-500 mt-1">Configure your outbound mail driver seamlessly.</p>
                            </div>
                            <div class="flex-shrink-0">
                                @if (! $this->editingMail)
                                    <x-filament::button size="md" color="warning" wire:click="enableMailEdit" icon="heroicon-m-lock-closed" class="!rounded-xl shadow-sm">
                                        Unlock for Editing
                                    </x-filament::button>
                                @else
                                    <div class="flex gap-3">
                                        <x-filament::button size="md" color="gray" wire:click="cancelMailEdit" class="!rounded-xl">Cancel</x-filament::button>
                                        <x-filament::button size="md" color="success" wire:click="saveMailSettings" icon="heroicon-m-check" class="!rounded-xl shadow-md">Save Configuration</x-filament::button>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-7">
                            @foreach ([
                                ['key' => 'mailHost', 'label' => 'SMTP Host', 'type' => 'text', 'icon' => 'heroicon-m-server'],
                                ['key' => 'mailPort', 'label' => 'SMTP Port', 'type' => 'text', 'icon' => 'heroicon-m-hashtag'],
                                ['key' => 'mailEncryption', 'label' => 'Encryption Type', 'type' => 'text', 'icon' => 'heroicon-m-shield-check'],
                                ['key' => 'mailUsername', 'label' => 'Mail Username', 'type' => 'email', 'icon' => 'heroicon-m-user'],
                                ['key' => 'mailPassword', 'label' => 'Mail Password', 'type' => 'password', 'icon' => 'heroicon-m-key'],
                                ['key' => 'mailDailyLimit', 'label' => 'Daily Quota Limit', 'type' => 'number', 'icon' => 'heroicon-m-chart-bar'],
                                ['key' => 'mailFromAddress', 'label' => 'Sender Address', 'type' => 'email', 'icon' => 'heroicon-m-at-symbol'],
                                ['key' => 'mailFromName', 'label' => 'Sender Name', 'type' => 'text', 'icon' => 'heroicon-m-identification'],
                            ] as $field)
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">{{ $field['label'] }}</label>
                                    <div class="relative">
                                        <input type="{{ $field['key'] === 'mailPassword' && $this->showMailPassword ? 'text' : $field['type'] }}" 
                                               wire:model.defer="{{ $field['key'] }}"
                                               class="premium-input pl-11 {{ $field['key'] === 'mailPassword' ? 'pr-12' : '' }}"
                                               @disabled(! $this->editingMail) />
                                        <x-filament::icon :icon="$field['icon']" class="absolute left-3.5 top-3 h-5 w-5 text-gray-400" />
                                        
                                        @if ($field['key'] === 'mailPassword' && $this->editingMail)
                                            <button type="button" class="absolute right-3 top-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors" wire:click="toggleMailPasswordVisibility">
                                                <x-filament::icon :icon="$this->showMailPassword ? 'heroicon-m-eye-slash' : 'heroicon-m-eye'" class="h-5 w-5" />
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- SMS SETUP TAB -->
                @if ($this->activeTab === 'sms')
                    <div class="premium-card p-6 md:p-8">
                        <div class="flex flex-col sm:flex-row justify-between sm:items-center mb-8 pb-6 border-b border-gray-100 dark:border-gray-800 gap-4">
                            <div>
                                <h3 class="text-xl font-bold text-gray-900 dark:text-white">Semaphore Gateway</h3>
                                <p class="text-sm text-gray-500 mt-1">Connect your verified Semaphore account.</p>
                            </div>
                            <div class="flex-shrink-0">
                                @if (! $this->editingSms)
                                    <x-filament::button size="md" color="warning" wire:click="enableSmsEdit" icon="heroicon-m-lock-closed" class="!rounded-xl shadow-sm">
                                        Unlock for Editing
                                    </x-filament::button>
                                @else
                                    <div class="flex gap-3">
                                        <x-filament::button size="md" color="gray" wire:click="cancelSmsEdit" class="!rounded-xl">Cancel</x-filament::button>
                                        <x-filament::button size="md" color="success" wire:click="saveSmsSettings" icon="heroicon-m-check" class="!rounded-xl shadow-md">Save Settings</x-filament::button>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-7">
                            <div class="md:col-span-2">
                                <label class="block text-[10px] font-bold uppercase tracking-widest text-purple-600 dark:text-purple-400 mb-2">Secure Value</label>
                                <label class="block text-sm font-bold text-gray-800 dark:text-gray-200 mb-2">Semaphore API Key</label>
                                <div class="relative">
                                    <input type="{{ $this->showSmsApiKey ? 'text' : 'password' }}" wire:model.defer="semaphoreApiKey" class="premium-input text-lg font-mono tracking-widest pl-11 pr-12 py-3" @disabled(! $this->editingSms) />
                                    <x-filament::icon icon="heroicon-m-key" class="absolute left-4 top-[14px] h-5 w-5 text-gray-400" />
                                    @if ($this->editingSms)
                                        <button type="button" class="absolute right-4 top-[14px] text-gray-400 hover:text-gray-600 dark:hover:text-gray-200" wire:click="toggleSmsApiKeyVisibility">
                                            <x-filament::icon :icon="$this->showSmsApiKey ? 'heroicon-m-eye-slash' : 'heroicon-m-eye'" class="h-6 w-6" />
                                        </button>
                                    @endif
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">OTP Endpoint URL</label>
                                <div class="relative">
                                    <input type="url" wire:model.defer="semaphoreOtpUrl" class="premium-input pl-11" @disabled(! $this->editingSms) />
                                    <x-filament::icon icon="heroicon-m-link" class="absolute left-3.5 top-3 h-5 w-5 text-gray-400" />
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">Verified Sender Name</label>
                                <div class="relative">
                                    <input type="text" wire:model.defer="semaphoreSenderName" class="premium-input pl-11" maxlength="11" @disabled(! $this->editingSms) />
                                    <x-filament::icon icon="heroicon-m-megaphone" class="absolute left-3.5 top-3 h-5 w-5 text-gray-400" />
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- MAINTENANCE TAB -->
                @if ($this->activeTab === 'maintenance')
                    <div class="premium-card p-6 md:p-8">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-8 pb-6 border-b border-gray-100 dark:border-gray-800 gap-4">
                            <div>
                                <h3 class="text-xl font-bold text-gray-900 dark:text-white">Public Access Control</h3>
                                <p class="text-sm text-gray-500 mt-1">Manage the holding page displayed to front-end visitors.</p>
                            </div>
                            <div class="flex-shrink-0">
                                @if (! $this->editingMaintenance)
                                    <x-filament::button size="md" color="primary" wire:click="enableMaintenanceEdit" icon="heroicon-m-pencil-square" class="!rounded-xl shadow-sm w-full sm:w-auto">
                                        Modify Notice
                                    </x-filament::button>
                                @else
                                    <div class="flex gap-3">
                                        <x-filament::button size="md" color="gray" wire:click="cancelMaintenanceEdit" class="!rounded-xl">Cancel</x-filament::button>
                                        <x-filament::button size="md" color="success" wire:click="saveMaintenanceSettings" icon="heroicon-m-check" class="!rounded-xl shadow-md">Deploy Changes</x-filament::button>
                                    </div>
                                @endif
                            </div>
                        </div>

                        @if (! $this->editingMaintenance)
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div class="bg-gray-50 dark:bg-gray-800/50 rounded-2xl p-5 border border-gray-100 dark:border-gray-800">
                                    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-2">Gate Status</p>
                                    <div class="flex items-center gap-3">
                                        <div class="relative">
                                            <div class="w-12 h-6 rounded-full {{ $this->maintenanceModeEnabled ? 'bg-rose-500' : 'bg-emerald-500' }}"></div>
                                            <div class="absolute top-1 bg-white w-4 h-4 rounded-full transition-all {{ $this->maintenanceModeEnabled ? 'right-1' : 'left-1' }} shadow-sm"></div>
                                        </div>
                                        <span class="text-base font-extrabold {{ $this->maintenanceModeEnabled ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }}">{{ $this->maintenanceModeEnabled ? 'OFFLINE (Active)' : 'ONLINE (Disabled)' }}</span>
                                    </div>
                                </div>
                                <div class="bg-gray-50 dark:bg-gray-800/50 rounded-2xl p-5 border border-gray-100 dark:border-gray-800">
                                    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-2">Active Layout</p>
                                    <p class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                        <x-filament::icon icon="heroicon-m-swatch" class="h-5 w-5 text-indigo-500" />
                                        {{ \App\Support\MaintenancePageVariant::labels()[$this->maintenanceVariant] ?? $this->maintenanceVariant }}
                                    </p>
                                </div>
                                <div class="bg-gray-50 dark:bg-gray-800/50 rounded-2xl p-5 border border-gray-100 dark:border-gray-800">
                                    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-2">Estimated Return</p>
                                    <p class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                        <x-filament::icon icon="heroicon-m-clock" class="h-5 w-5 text-amber-500" />
                                        {{ $this->maintenanceEta !== '' ? $this->maintenanceEta : 'Indefinite' }}
                                    </p>
                                </div>
                            </div>
                        @else
                            <div class="space-y-8">
                                <div class="bg-amber-50 dark:bg-amber-500/10 rounded-2xl p-6 border border-amber-200 dark:border-amber-500/20">
                                    <p class="text-[11px] font-bold uppercase tracking-widest text-amber-700 dark:text-amber-400 mb-3 flex items-center gap-2">
                                        <x-filament::icon icon="heroicon-m-bolt" class="h-4 w-4" />
                                        1-Click Presets
                                    </p>
                                    <div class="flex flex-wrap gap-3">
                                        <x-filament::button color="warning" variant="outlined" wire:click="applyMaintenancePreset('quick-fix')" class="!rounded-xl bg-white dark:bg-transparent">Quick Fix (2H)</x-filament::button>
                                        <x-filament::button color="warning" variant="outlined" wire:click="applyMaintenancePreset('scheduled-upgrade')" class="!rounded-xl bg-white dark:bg-transparent">Scheduled Upgrade (1D)</x-filament::button>
                                        <x-filament::button color="danger" variant="outlined" wire:click="applyMaintenancePreset('emergency')" class="!rounded-xl bg-white dark:bg-transparent">Emergency Lockdown</x-filament::button>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <label class="md:col-span-2 relative flex items-start gap-4 p-5 rounded-2xl border-2 {{ $this->maintenanceModeEnabled ? 'border-rose-500 bg-rose-50 dark:bg-rose-500/10' : 'border-gray-200 dark:border-gray-700' }} cursor-pointer transition-all">
                                        <div class="flex flex-col flex-1">
                                            <span class="text-base font-bold text-gray-900 dark:text-white">Enable Maintenance Gateway</span>
                                            <span class="text-sm text-gray-500 dark:text-gray-400 mt-1">Diverts all public traffic to the holding page unconditionally.</span>
                                        </div>
                                        <div class="relative align-middle select-none mt-1">
                                            <input type="checkbox" wire:model.defer="maintenanceModeEnabled" class="sr-only peer" />
                                            <div class="w-14 h-8 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[4px] after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all dark:border-gray-600 peer-checked:bg-rose-500"></div>
                                        </div>
                                    </label>

                                    <div>
                                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">Display Theme</label>
                                        <select wire:model.defer="maintenanceVariant" class="premium-input bg-[url('data:image/svg+xml;charset=utf-8,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 20 20\'%3E%3Cpath stroke=\'%236b7280\' stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'1.5\' d=\'m6 8 4 4 4-4\'/%3E%3C/svg%3E')] appearance-none bg-no-repeat bg-[position:right_0.75rem_center] bg-[length:1.25em_1.25em] pr-10">
                                            @foreach (\App\Support\MaintenancePageVariant::labels() as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">Notice Badge</label>
                                        <input type="text" wire:model.defer="maintenanceBadge" class="premium-input" />
                                    </div>

                                    <div class="md:col-span-2">
                                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">Primary Heading</label>
                                        <input type="text" wire:model.defer="maintenanceTitle" class="premium-input text-lg font-semibold" />
                                    </div>

                                    <div class="md:col-span-2">
                                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">Body Description</label>
                                        <textarea rows="4" wire:model.defer="maintenanceDescription" class="premium-input resize-none"></textarea>
                                    </div>
                                    
                                    <div class="md:col-span-2">
                                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">ETA Text (Optional)</label>
                                        <input type="text" wire:model.defer="maintenanceEta" class="premium-input" placeholder="e.g. Next couple of hours..." />
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                @endif

                <!-- PAYMENT TAB -->
                @if ($this->activeTab === 'payment')
                    <div class="premium-card p-6 md:p-8 space-y-8">
                        <div class="flex flex-col sm:flex-row justify-between sm:items-center pb-6 border-b border-gray-100 dark:border-gray-800 gap-4">
                            <div>
                                <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
                                    Xendit Integration
                                    @if($this->xenditSecretKey !== '' && $this->xenditPublicKey !== '')
                                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-bold text-emerald-700 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-500/20">Configured</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-bold text-rose-700 ring-1 ring-inset ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-500/20">Pending Keys</span>
                                    @endif
                                </h3>
                                <p class="text-sm text-gray-500 mt-1">Manage external payment gateway credentials and checkout flows.</p>
                            </div>
                            <div class="flex-shrink-0">
                                @if (! $this->editingPayment)
                                    <x-filament::button size="md" color="primary" wire:click="enablePaymentEdit" icon="heroicon-m-adjustments-vertical" class="!rounded-xl shadow-sm w-full sm:w-auto">
                                        Configure Gateway
                                    </x-filament::button>
                                @else
                                    <div class="flex gap-3">
                                        <x-filament::button size="md" color="gray" wire:click="cancelPaymentEdit" class="!rounded-xl">Cancel</x-filament::button>
                                        <x-filament::button size="md" color="success" wire:click="savePaymentSettings" icon="heroicon-m-check" class="!rounded-xl shadow-md">Apply Strategy</x-filament::button>
                                    </div>
                                @endif
                            </div>
                        </div>

                        @if (! $this->editingPayment)
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="p-5 rounded-2xl bg-gray-50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-700/50 shadow-sm">
                                    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-2">Checkout Status</p>
                                    <div class="flex items-center gap-3">
                                        <span class="relative flex h-4 w-4">
                                          @if($this->onlinePaymentEnabled)
                                              <span class="relative inline-flex rounded-full h-4 w-4 bg-emerald-500 border-2 border-white dark:border-gray-900"></span>
                                          @else
                                              <span class="relative inline-flex rounded-full h-4 w-4 bg-gray-400 border-2 border-white dark:border-gray-900"></span>
                                          @endif
                                        </span>
                                        <span class="text-lg font-bold text-gray-900 dark:text-white">{{ $this->onlinePaymentEnabled ? 'Live Payments Active' : 'Offline Mode Only' }}</span>
                                    </div>
                                </div>

                                <div class="p-5 rounded-2xl bg-gray-50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-700/50 shadow-sm">
                                    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-3">Deposit Structure</p>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        @foreach(explode(',', $this->partialPaymentOptions) as $opt)
                                            <span class="px-3 py-1 bg-white dark:bg-gray-700 rounded-lg text-sm font-bold text-indigo-600 dark:text-indigo-400 shadow-sm border border-gray-100 dark:border-gray-600">
                                                {{ trim($opt) }}%
                                            </span>
                                        @endforeach
                                        @if($this->allowCustomPartialPayment)
                                            <span class="px-3 py-1 bg-indigo-50 dark:bg-indigo-500/20 rounded-lg text-sm font-bold text-indigo-600 dark:text-indigo-400 border border-indigo-100 dark:border-indigo-500/30 border-dashed">
                                                + Custom Value
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="space-y-6">
                                <label class="relative flex items-start gap-4 p-5 rounded-2xl border-2 {{ $this->onlinePaymentEnabled ? 'border-emerald-500 bg-emerald-50 dark:border-emerald-500/30 dark:bg-emerald-500/10' : 'border-gray-200 dark:border-gray-700' }} cursor-pointer transition-all">
                                    <div class="flex w-full justify-between items-center group">
                                        <div class="flex flex-col">
                                            <span class="text-base font-bold text-gray-900 dark:text-white">Activate Front-End Payments</span>
                                            <span class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Permits clients to pay via Xendit immediately on step 4 of booking.</span>
                                        </div>
                                        <div class="relative align-middle select-none">
                                            <input type="checkbox" wire:model.defer="onlinePaymentEnabled" class="sr-only peer" />
                                            <div class="w-14 h-8 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[4px] after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all dark:border-gray-600 peer-checked:bg-emerald-500"></div>
                                        </div>
                                    </div>
                                </label>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-gray-50 dark:bg-gray-800/40 p-6 rounded-2xl border border-gray-100 dark:border-gray-800">
                                    <div>
                                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">Partial Payment Percentages</label>
                                        <select wire:model.defer="partialPaymentSelection" multiple class="premium-input min-h-[10rem]">
                                            @foreach ([10, 20, 30, 40, 50, 60, 70, 80, 90] as $option)
                                                <option value="{{ $option }}">{{ $option }}%</option>
                                            @endforeach
                                        </select>
                                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                            Hold Ctrl/Cmd to select multiple options. Full payment (100%) is intentionally excluded.
                                        </p>
                                    </div>
                                    
                                    <div class="flex items-center mt-3 md:mt-0">
                                        <label class="inline-flex items-center gap-3 cursor-pointer select-none">
                                            <input
                                                type="checkbox"
                                                wire:model.live="allowCustomPartialPayment"
                                                class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800"
                                            />
                                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">Allow Custom Deposit Inputs</span>
                                        </label>
                                    </div>
                                </div>

                                <div class="space-y-4 pt-4 border-t border-gray-100 dark:border-gray-800">
                                    <p class="text-[11px] font-bold uppercase tracking-widest text-gray-400">API Credentials</p>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                        <div>
                                            <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">Public Identity Key</label>
                                            <input type="text" wire:model.defer="xenditPublicKey" class="premium-input font-mono text-sm" placeholder="xnd_public_..." />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">Secret Identity Key</label>
                                            <input type="password" wire:model.defer="xenditSecretKey" class="premium-input font-mono text-sm text-indigo-600" placeholder="••••••••••••••••" />
                                        </div>
                                        <div class="md:col-span-2">
                                            <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">Secure Webhook Token</label>
                                            <input type="password" wire:model.defer="xenditWebhookToken" class="premium-input font-mono text-sm text-rose-600" placeholder="••••••••••••••••" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- Diagnostics Log section -->
                        <div class="mt-10 rounded-2xl border border-sky-200/60 bg-sky-50/60 dark:bg-sky-900/10 p-6 shadow-sm overflow-hidden relative">
                            <div class="flex sm:items-center flex-col sm:flex-row justify-between gap-4 mb-6">
                                <div>
                                    <h4 class="text-base font-extrabold text-sky-900 dark:text-sky-300 flex items-center gap-2">
                                        <x-filament::icon icon="heroicon-m-code-bracket-square" class="h-5 w-5" />
                                        Live Webhook Trace
                                    </h4>
                                    <p class="text-[11px] text-sky-600/80 dark:text-sky-400/80 mt-1 uppercase font-bold tracking-widest">Diagnostic Telemetry Output</p>
                                </div>
                                <x-filament::button size="sm" color="info" variant="outlined" wire:click="refreshPaymentDebug" class="!rounded-xl bg-white dark:bg-transparent">Pull Latest Event</x-filament::button>
                            </div>

                            @if ($this->lastXenditWebhookEvent)
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                    <div class="bg-white/60 dark:bg-gray-900/50 p-3 rounded-xl border border-white/50 dark:border-gray-800">
                                        <p class="text-[10px] font-bold uppercase text-gray-400">Time Delta</p>
                                        <p class="text-sm font-bold text-gray-900 dark:text-white mt-1">{{ $this->lastXenditWebhookEvent['received_at'] ?? '—' }}</p>
                                    </div>
                                    <div class="bg-white/60 dark:bg-gray-900/50 p-3 rounded-xl border border-white/50 dark:border-gray-800">
                                        <p class="text-[10px] font-bold uppercase text-gray-400">Response Code</p>
                                        <p class="text-sm font-bold text-gray-900 dark:text-white mt-1">{{ $this->lastXenditWebhookEvent['raw_status'] ?? '—' }}</p>
                                    </div>
                                    <div class="bg-white/60 dark:bg-gray-900/50 p-3 rounded-xl border border-white/50 dark:border-gray-800 md:col-span-2">
                                        <p class="text-[10px] font-bold uppercase text-gray-400">Action Resolution</p>
                                        <p class="text-sm font-bold {{ isset($this->lastXenditWebhookEvent['result']) && str_contains(strtolower($this->lastXenditWebhookEvent['result']), 'error') ? 'text-rose-600' : 'text-emerald-600' }} mt-1">
                                            {{ $this->lastXenditWebhookEvent['result'] ?? '—' }}
                                            @if (! empty($this->lastXenditWebhookEvent['reason']))
                                                <span class="opacity-75 font-normal ml-1">({{ $this->lastXenditWebhookEvent['reason'] }})</span>
                                            @endif
                                        </p>
                                    </div>
                                    <div class="bg-white/60 dark:bg-gray-900/50 p-3 rounded-xl border border-white/50 dark:border-gray-800">
                                        <p class="text-[10px] font-bold uppercase text-gray-400">Booking Ref</p>
                                        <p class="text-sm font-bold text-gray-900 dark:text-white mt-1 font-mono">{{ $this->lastXenditWebhookEvent['booking_reference'] ?? '—' }}</p>
                                    </div>
                                    <div class="bg-white/60 dark:bg-gray-900/50 p-3 rounded-xl border border-white/50 dark:border-gray-800">
                                        <p class="text-[10px] font-bold uppercase text-gray-400">Tx Status</p>
                                        <p class="text-sm font-bold text-gray-900 dark:text-white mt-1 uppercase">{{ $this->lastXenditWebhookEvent['booking_status'] ?? '—' }}</p>
                                    </div>
                                    <div class="bg-white/60 dark:bg-gray-900/50 p-3 rounded-xl border border-white/50 dark:border-gray-800 md:col-span-2 flex justify-between items-center">
                                        <div>
                                            <p class="text-[10px] font-bold uppercase text-gray-400">Cleared Volume</p>
                                            <p class="text-sm font-bold text-gray-900 dark:text-white mt-1">₱{{ number_format((float) ($this->lastXenditWebhookEvent['paid_amount'] ?? 0), 2) }}</p>
                                        </div>
                                        <div class="text-right flex flex-col">
                                            <span class="text-[10px] font-mono text-gray-500">inv_{{ substr($this->lastXenditWebhookEvent['invoice_id'] ?? 'xxxx', -6) }}</span>
                                            <span class="text-[10px] font-mono text-gray-400 mt-0.5">ext_{{ substr($this->lastXenditWebhookEvent['external_id'] ?? 'xxxx', -6) }}</span>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div class="text-center p-8 border border-dashed border-sky-300 dark:border-sky-700/50 rounded-2xl">
                                    <x-filament::icon icon="heroicon-o-inbox" class="h-10 w-10 text-sky-300 dark:text-sky-700 mx-auto mb-3" />
                                    <p class="text-sm font-bold tracking-wide text-sky-800/60 dark:text-sky-300/60">Awaiting Submissions...</p>
                                    <p class="text-xs text-sky-600/50 dark:text-sky-400/50 mt-1">Telemetry will display when the gateway triggers an event.</p>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </main>
        </div>
    </div>
</x-filament-panels::page>
