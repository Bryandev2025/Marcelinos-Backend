<?php

namespace App\Filament\Pages;

use App\Support\EnvEditor;
use App\Support\MaintenancePageVariant;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class Settings extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'settings';

    protected static ?string $title = 'Settings';

    protected string $view = 'filament.pages.settings';

    public bool $editingMail = false;

    public bool $editingSms = false;

    public bool $editingMaintenance = false;

    public bool $editingPayment = false;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->hasPrivilege('manage_settings') ?? false;
    }

    public string $mailHost = '';

    public string $mailPort = '';

    public string $mailUsername = '';

    public string $mailPassword = '';

    public string $mailEncryption = '';

    public string $mailFromAddress = '';

    public string $mailFromName = '';

    public int $mailDailyLimit = 100;

    public string $semaphoreApiKey = '';

    public string $semaphoreOtpUrl = 'https://api.semaphore.co/api/v4/otp';

    public string $semaphoreAccountUrl = 'https://api.semaphore.co/api/v4/account';

    public string $semaphoreMessagesUrl = 'https://api.semaphore.co/api/v4/messages';

    public string $semaphoreSenderName = '';

    public string $emailHealth = 'Unknown';

    public string $smsHealth = 'Unknown';

    public ?float $smsCredits = null;

    public int $smsSentToday = 0;

    public int $emailsSentToday = 0;

    public int $emailsLeftToday = 0;

    public ?string $lastCheckedAt = null;

    public string $testEmailRecipient = '';

    public string $emailAlertThreshold = '80';

    public string $smsLowCreditThreshold = '50';

    public string $activeTab = 'overview';

    public bool $showMailPassword = false;

    public bool $showSmsApiKey = false;

    public bool $maintenanceModeEnabled = false;

    public string $maintenanceBadge = '';

    public string $maintenanceTitle = '';

    public string $maintenanceDescription = '';

    public string $maintenanceEta = '';

    public string $maintenanceVariant = MaintenancePageVariant::DEFAULT;

    public bool $onlinePaymentEnabled = false;

    public string $partialPaymentOptions = '30';

    public int $partialPaymentSelection = 30;

    public bool $allowCustomPartialPayment = false;

    public int $cancellationFeePercent = 30;

    public string $xenditSecretKey = '';

    public string $xenditPublicKey = '';

    public string $xenditWebhookToken = '';

    public ?array $lastXenditWebhookEvent = null;

    public string $hostingerPlanExpiresAt = '';

    public function mount(): void
    {
        $this->loadFromEnv();
        $this->refreshHealth();
        $this->testEmailRecipient = $this->mailFromAddress;
        $this->normalizeAlertThresholds();
    }

    public function setTab(string $tab): void
    {
        if (! in_array($tab, ['overview', 'actions', 'email', 'sms', 'maintenance', 'payment'], true)) {
            return;
        }

        $this->activeTab = $tab;
    }

    public function enableMailEdit(): void
    {
        $this->editingMail = true;
    }

    public function cancelMailEdit(): void
    {
        $this->editingMail = false;
        $this->showMailPassword = false;
        $this->loadFromEnv();
    }

    public function enableSmsEdit(): void
    {
        $this->editingSms = true;
    }

    public function cancelSmsEdit(): void
    {
        $this->editingSms = false;
        $this->showSmsApiKey = false;
        $this->loadFromEnv();
    }

    public function toggleMailPasswordVisibility(): void
    {
        $this->showMailPassword = ! $this->showMailPassword;
    }

    public function toggleSmsApiKeyVisibility(): void
    {
        $this->showSmsApiKey = ! $this->showSmsApiKey;
    }

    public function saveMailSettings(): void
    {
        if (! $this->editingMail) {
            return;
        }

        $this->validate([
            'mailHost' => ['required', 'string'],
            'mailPort' => ['required', 'string'],
            'mailUsername' => ['required', 'email'],
            'mailPassword' => ['required', 'string'],
            'mailEncryption' => ['nullable', 'string'],
            'mailFromAddress' => ['required', 'email'],
            'mailFromName' => ['required', 'string'],
            'mailDailyLimit' => ['required', 'integer', 'min:0', 'max:100000'],
        ]);

        EnvEditor::updateMany([
            'MAIL_HOST' => $this->mailHost,
            'MAIL_PORT' => $this->mailPort,
            'MAIL_USERNAME' => $this->mailUsername,
            'MAIL_PASSWORD' => $this->mailPassword,
            'MAIL_ENCRYPTION' => $this->mailEncryption,
            'MAIL_FROM_ADDRESS' => $this->mailFromAddress,
            'MAIL_FROM_NAME' => $this->mailFromName,
            'MAIL_DAILY_LIMIT' => $this->mailDailyLimit,
        ]);

        config([
            'mail.mailers.smtp.host' => $this->mailHost,
            'mail.mailers.smtp.port' => (int) $this->mailPort,
            'mail.mailers.smtp.username' => $this->mailUsername,
            'mail.mailers.smtp.password' => $this->mailPassword,
            'mail.mailers.smtp.encryption' => $this->mailEncryption,
            'mail.from.address' => $this->mailFromAddress,
            'mail.from.name' => $this->mailFromName,
            'mail.daily_limit' => $this->mailDailyLimit,
        ]);

        $this->editingMail = false;
        $this->refreshHealth();

        Notification::make()
            ->title('Email settings saved')
            ->body('SMTP and sender settings were updated successfully.')
            ->success()
            ->send();

        $this->showMailPassword = false;
    }

    public function saveSmsSettings(): void
    {
        if (! $this->editingSms) {
            return;
        }

        $this->validate([
            'semaphoreApiKey' => ['required', 'string'],
            'semaphoreOtpUrl' => ['required', 'url'],
            'semaphoreSenderName' => ['required', 'string', 'max:11'],
        ]);

        EnvEditor::updateMany([
            'SEMAPHORE_API_KEY' => $this->semaphoreApiKey,
            'SEMAPHORE_OTP_URL' => $this->semaphoreOtpUrl,
            'SEMAPHORE_SENDER_NAME' => $this->semaphoreSenderName,
        ]);

        config([
            'services.semaphore.api_key' => $this->semaphoreApiKey,
            'services.semaphore.otp_url' => $this->semaphoreOtpUrl,
            'services.semaphore.sender_name' => $this->semaphoreSenderName,
        ]);

        $this->editingSms = false;
        $this->refreshHealth();

        Notification::make()
            ->title('SMS settings saved')
            ->body('Semaphore gateway settings were updated successfully.')
            ->success()
            ->send();

        $this->showSmsApiKey = false;
    }

    public function enableMaintenanceEdit(): void
    {
        $this->editingMaintenance = true;
    }

    public function cancelMaintenanceEdit(): void
    {
        $this->editingMaintenance = false;
        $this->loadFromEnv();
    }

    public function saveMaintenanceSettings(): void
    {
        if (! $this->editingMaintenance) {
            return;
        }

        $this->validate([
            'maintenanceModeEnabled' => ['required', 'boolean'],
            'maintenanceVariant' => ['required', 'string', Rule::in(MaintenancePageVariant::keys())],
            'maintenanceBadge' => ['required', 'string', 'max:100'],
            'maintenanceTitle' => ['required', 'string', 'max:150'],
            'maintenanceDescription' => ['required', 'string', 'max:500'],
            'maintenanceEta' => ['nullable', 'string', 'max:100'],
        ]);

        EnvEditor::updateMany([
            'MAINTENANCE_MODE_ENABLED' => $this->maintenanceModeEnabled ? 'true' : 'false',
            'MAINTENANCE_MODE_VARIANT' => $this->maintenanceVariant,
            'MAINTENANCE_MODE_BADGE' => $this->maintenanceBadge,
            'MAINTENANCE_MODE_TITLE' => $this->maintenanceTitle,
            'MAINTENANCE_MODE_DESCRIPTION' => $this->maintenanceDescription,
            'MAINTENANCE_MODE_ETA' => $this->maintenanceEta,
        ]);

        Cache::forever('maintenance_mode_config', [
            'enabled' => $this->maintenanceModeEnabled,
            'variant' => MaintenancePageVariant::normalize($this->maintenanceVariant),
            'badge' => $this->maintenanceBadge,
            'title' => $this->maintenanceTitle,
            'description' => $this->maintenanceDescription,
            'eta' => $this->maintenanceEta,
        ]);

        $this->editingMaintenance = false;

        Notification::make()
            ->title('Maintenance settings saved')
            ->body('Maintenance mode preferences are now active.')
            ->success()
            ->send();
    }

    public function enablePaymentEdit(): void
    {
        $this->editingPayment = true;
    }

    public function cancelPaymentEdit(): void
    {
        $this->editingPayment = false;
        $this->loadFromEnv();
    }

    public function savePaymentSettings(): void
    {
        if (! $this->editingPayment) {
            return;
        }

        $this->validate([
            'onlinePaymentEnabled' => ['required', 'boolean'],
            'partialPaymentSelection' => $this->partialPaymentRules(),
            'allowCustomPartialPayment' => ['required', 'boolean'],
            'cancellationFeePercent' => ['required', 'integer', 'min:0', 'max:100'],
            'xenditSecretKey' => ['nullable', 'string', 'max:255'],
            'xenditPublicKey' => ['nullable', 'string', 'max:255'],
            'xenditWebhookToken' => ['nullable', 'string', 'max:255'],
        ]);

        $normalizedPartialOption = $this->normalizePartialPaymentSelection(
            $this->partialPaymentSelection,
            $this->allowCustomPartialPayment
        );

        EnvEditor::updateMany([
            'PAYMENT_ONLINE_ENABLED' => $this->onlinePaymentEnabled ? 'true' : 'false',
            'PAYMENT_PARTIAL_OPTIONS' => (string) $normalizedPartialOption,
            'PAYMENT_PARTIAL_ALLOW_CUSTOM' => $this->allowCustomPartialPayment ? 'true' : 'false',
            'PAYMENT_CANCELLATION_FEE_PERCENT' => (string) $this->cancellationFeePercent,
            'XENDIT_SECRET_KEY' => $this->xenditSecretKey,
            'XENDIT_PUBLIC_KEY' => $this->xenditPublicKey,
            'XENDIT_WEBHOOK_TOKEN' => $this->xenditWebhookToken,
        ]);

        Cache::forever('payment_settings_config', [
            'online_payment_enabled' => $this->onlinePaymentEnabled,
            'partial_payment_options' => [$normalizedPartialOption],
            'allow_custom_partial_payment' => $this->allowCustomPartialPayment,
            'cancellation_fee_percent' => $this->cancellationFeePercent,
        ]);

        $this->partialPaymentOptions = (string) $normalizedPartialOption;
        $this->partialPaymentSelection = $normalizedPartialOption;

        $this->editingPayment = false;

        Notification::make()
            ->title('Payment settings saved')
            ->body('Payment and gateway configuration were updated.')
            ->success()
            ->send();
    }

    public function refreshPaymentDebug(): void
    {
        $event = Cache::get('xendit_webhook_last_event');
        $this->lastXenditWebhookEvent = is_array($event) ? $event : null;

        Notification::make()
            ->title('Payment diagnostics refreshed')
            ->success()
            ->send();
    }

    public function applyMaintenancePreset(string $preset): void
    {
        if (! $this->editingMaintenance) {
            return;
        }

        $presets = [
            'quick-fix' => [
                'badge' => 'Maintenance Update',
                'title' => 'Quick Service Adjustment',
                'description' => 'We are applying a quick system adjustment to improve performance. Please check back shortly.',
                'eta' => now()->addHours(2)->format('M d, Y h:i A'),
            ],
            'scheduled-upgrade' => [
                'badge' => 'Scheduled Maintenance',
                'title' => 'Platform Upgrade in Progress',
                'description' => 'Our team is rolling out planned platform improvements for better reliability and booking experience.',
                'eta' => now()->addDay()->format('M d, Y'),
            ],
            'emergency' => [
                'badge' => 'Service Notice',
                'title' => 'Temporary Service Interruption',
                'description' => 'We are addressing an unexpected issue and restoring services as quickly as possible.',
                'eta' => 'To be announced',
            ],
        ];

        if (! array_key_exists($preset, $presets)) {
            return;
        }

        $selected = $presets[$preset];

        $this->maintenanceBadge = $selected['badge'];
        $this->maintenanceTitle = $selected['title'];
        $this->maintenanceDescription = $selected['description'];
        $this->maintenanceEta = $selected['eta'];

        Notification::make()
            ->title('Preset applied')
            ->success()
            ->send();
    }

    public function refreshHealth(): void
    {
        $this->normalizeAlertThresholds();
        $this->emailHealth = $this->checkEmailHealth();
        $this->smsHealth = $this->checkSmsHealth();
        $this->emailsSentToday = $this->resolveEmailsSentToday();
        $this->emailsLeftToday = $this->mailDailyLimit > 0
            ? max(0, $this->mailDailyLimit - $this->emailsSentToday)
            : 0;
        $this->lastCheckedAt = now()->format('Y-m-d H:i:s');
    }

    public function updatedEmailAlertThreshold($value): void
    {
        $this->emailAlertThreshold = (string) max(1, min(100, (int) $value));
    }

    public function updatedSmsLowCreditThreshold($value): void
    {
        $this->smsLowCreditThreshold = number_format(max(0, (float) $value), 2, '.', '');
    }

    public function updatedAllowCustomPartialPayment($value): void
    {
        $this->allowCustomPartialPayment = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        $this->partialPaymentSelection = $this->normalizePartialPaymentSelection(
            $this->partialPaymentSelection,
            $this->allowCustomPartialPayment
        );
    }

    public function sendTestEmail(): void
    {
        $this->validate([
            'testEmailRecipient' => ['required', 'email'],
        ]);

        try {
            Mail::raw('This is a test email from the Marcelinos Settings dashboard.', function ($message): void {
                $message->to($this->testEmailRecipient)
                    ->subject('Marcelinos Email Health Test');
            });

            Notification::make()
                ->title('Test email sent')
                ->body('Email was sent to '.$this->testEmailRecipient)
                ->success()
                ->send();
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('Test email failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }

        $this->refreshHealth();
    }

    public function testSmsConnectivity(): void
    {
        if (trim($this->semaphoreApiKey) === '') {
            Notification::make()
                ->title('SMS API key missing')
                ->warning()
                ->send();

            return;
        }

        $apiHash = md5($this->semaphoreApiKey);
        $cooldownKey = "semaphore_connectivity_test_cooldown_{$apiHash}";
        if (! Cache::add($cooldownKey, 1, now()->addSeconds(20))) {
            Notification::make()
                ->title('Please wait')
                ->body('SMS connectivity test is cooling down to avoid rate limits.')
                ->info()
                ->send();

            return;
        }

        try {
            $response = Http::timeout(10)->get($this->semaphoreAccountUrl, [
                'apikey' => $this->semaphoreApiKey,
            ]);

            if (! $response->successful()) {
                if ($response->status() === 429) {
                    $retryAfter = $this->parseRetryAfterSeconds($response->header('Retry-After'));
                    $until = now()->addSeconds($retryAfter);
                    Cache::put("semaphore_rate_limited_until_{$apiHash}", $until->timestamp, $until);

                    Notification::make()
                        ->title('Semaphore rate limited')
                        ->body('Too many requests. Try again in ~'.$retryAfter.'s.')
                        ->warning()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Semaphore connectivity failed')
                    ->body('HTTP '.$response->status())
                    ->danger()
                    ->send();

                return;
            }

            Notification::make()
                ->title('Semaphore connectivity OK')
                ->body('API access is healthy.')
                ->success()
                ->send();
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('Semaphore connectivity failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }

        $this->refreshHealth();
    }

    public function getAlertsProperty(): array
    {
        $alerts = [];

        if (! str_starts_with(strtolower($this->emailHealth), 'online')) {
            $alerts[] = [
                'title' => 'Email service issue',
                'detail' => $this->emailHealth,
                'level' => 'danger',
            ];
        }

        if (! str_starts_with(strtolower($this->smsHealth), 'online')) {
            $smsLevel = Str::contains(strtolower($this->smsHealth), ['rate limit', 'rate-limited', 'throttle'])
                ? 'warning'
                : 'danger';

            $alerts[] = [
                'title' => 'SMS service issue',
                'detail' => $this->smsHealth,
                'level' => $smsLevel,
            ];
        }

        $emailUsagePercent = $this->mailDailyLimit > 0
            ? (int) round(($this->emailsSentToday / $this->mailDailyLimit) * 100)
            : 0;

        if ($this->mailDailyLimit > 0 && $emailUsagePercent >= $this->emailAlertThresholdValue()) {
            $alerts[] = [
                'title' => 'Email quota is high',
                'detail' => "Usage is at {$emailUsagePercent}% today.",
                'level' => 'warning',
            ];
        }

        if ($this->smsCredits !== null && $this->smsCredits <= $this->smsLowCreditThresholdValue()) {
            $alerts[] = [
                'title' => 'SMS credits are low',
                'detail' => 'Current credits: '.number_format($this->smsCredits, 2),
                'level' => 'warning',
            ];
        }

        $hostingerDaysLeft = $this->hostingerPlanDaysLeft();
        if ($hostingerDaysLeft !== null && $hostingerDaysLeft <= 30) {
            $alerts[] = [
                'title' => 'Hosting plan is nearing expiry',
                'detail' => $hostingerDaysLeft <= 0
                    ? 'Your Hostinger plan expiry date has passed. Please renew immediately.'
                    : "Hostinger plan expires in {$hostingerDaysLeft} day(s).",
                'level' => 'warning',
            ];
        }

        return $alerts;
    }

    public function getRecommendationsProperty(): array
    {
        $items = [];

        if (! str_starts_with(strtolower($this->emailHealth), 'online')) {
            $items[] = 'Check Hostinger SMTP host, port, and encryption settings.';
        }

        if (! str_starts_with(strtolower($this->smsHealth), 'online')) {
            $items[] = 'Verify Semaphore API key and try SMS connectivity test.';
        }

        if ($this->mailDailyLimit > 0 && ((int) round(($this->emailsSentToday / $this->mailDailyLimit) * 100)) >= 85) {
            $items[] = 'Email quota is nearing limit. Consider spreading sends or increasing mailbox tier.';
        }

        if ($this->smsCredits !== null && $this->smsCredits <= $this->smsLowCreditThresholdValue()) {
            $items[] = 'SMS credits are low. Top up Semaphore credits to avoid delivery failures.';
        }

        $hostingerDaysLeft = $this->hostingerPlanDaysLeft();
        if ($hostingerDaysLeft !== null && $hostingerDaysLeft <= 30) {
            $items[] = $hostingerDaysLeft <= 0
                ? 'Hostinger plan appears expired. Renew now to avoid downtime.'
                : "Hostinger plan expires in {$hostingerDaysLeft} day(s). Plan a renewal early.";
        }

        if ($items === []) {
            $items[] = 'All systems look healthy. Keep credentials locked until needed.';
        }

        return $items;
    }

    private function loadFromEnv(): void
    {
        $this->mailHost = (string) config('mail.mailers.smtp.host', 'smtp.hostinger.com');
        $this->mailPort = (string) config('mail.mailers.smtp.port', '465');
        $this->mailUsername = (string) config('mail.mailers.smtp.username', '');
        $this->mailPassword = (string) config('mail.mailers.smtp.password', '');
        $this->mailEncryption = (string) config('mail.mailers.smtp.encryption', 'ssl');
        $this->mailFromAddress = (string) config('mail.from.address', '');
        $this->mailFromName = (string) config('mail.from.name', '');
        $this->mailDailyLimit = max(0, (int) config('mail.daily_limit', 100));

        $this->semaphoreApiKey = (string) config('services.semaphore.api_key', '');
        $this->semaphoreAccountUrl = (string) config('services.semaphore.account_url', 'https://api.semaphore.co/api/v4/account');
        $this->semaphoreOtpUrl = (string) config('services.semaphore.otp_url', 'https://api.semaphore.co/api/v4/otp');
        $this->semaphoreMessagesUrl = (string) config('services.semaphore.messages_url', 'https://api.semaphore.co/api/v4/messages');
        $this->semaphoreSenderName = (string) config('services.semaphore.sender_name', '');

        $this->maintenanceModeEnabled = filter_var(env('MAINTENANCE_MODE_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
        $this->maintenanceVariant = MaintenancePageVariant::normalize((string) env('MAINTENANCE_MODE_VARIANT', MaintenancePageVariant::DEFAULT));
        $this->maintenanceBadge = (string) env('MAINTENANCE_MODE_BADGE', 'Scheduled Maintenance');
        $this->maintenanceTitle = (string) env('MAINTENANCE_MODE_TITLE', 'We are improving your experience');
        $this->maintenanceDescription = (string) env('MAINTENANCE_MODE_DESCRIPTION', 'Our website is currently under maintenance. Please check back again shortly.');
        $this->maintenanceEta = (string) env('MAINTENANCE_MODE_ETA', '');
        $this->onlinePaymentEnabled = filter_var(env('PAYMENT_ONLINE_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
        $this->allowCustomPartialPayment = filter_var(env('PAYMENT_PARTIAL_ALLOW_CUSTOM', false), FILTER_VALIDATE_BOOLEAN);
        $this->cancellationFeePercent = max(0, min(100, (int) env('PAYMENT_CANCELLATION_FEE_PERCENT', 30)));
        $this->partialPaymentOptions = (string) env('PAYMENT_PARTIAL_OPTIONS', '30');
        $this->partialPaymentSelection = $this->normalizePartialPaymentSelection(
            $this->partialPaymentOptions,
            $this->allowCustomPartialPayment
        );
        $this->partialPaymentOptions = (string) $this->partialPaymentSelection;
        $this->xenditSecretKey = (string) env('XENDIT_SECRET_KEY', '');
        $this->xenditPublicKey = (string) env('XENDIT_PUBLIC_KEY', '');
        $this->xenditWebhookToken = (string) env('XENDIT_WEBHOOK_TOKEN', '');
        $webhookEvent = Cache::get('xendit_webhook_last_event');
        $this->lastXenditWebhookEvent = is_array($webhookEvent) ? $webhookEvent : null;
        $this->hostingerPlanExpiresAt = (string) env('HOSTINGER_PLAN_EXPIRES_AT', '');
    }

    public function hostingerPlanExpiryDisplay(): string
    {
        if (trim($this->hostingerPlanExpiresAt) === '') {
            return 'Not set';
        }

        try {
            return Carbon::parse($this->hostingerPlanExpiresAt)->format('M d, Y');
        } catch (\Throwable) {
            return 'Invalid date format';
        }
    }

    public function hostingerPlanDaysLeft(): ?int
    {
        if (trim($this->hostingerPlanExpiresAt) === '') {
            return null;
        }

        try {
            return now()->startOfDay()->diffInDays(Carbon::parse($this->hostingerPlanExpiresAt)->startOfDay(), false);
        } catch (\Throwable) {
            return null;
        }
    }

    private function checkEmailHealth(): string
    {
        $host = trim($this->mailHost);
        $port = (int) $this->mailPort;

        if ($host === '' || $port <= 0) {
            return 'Misconfigured';
        }

        $cacheKey = 'mail_health_'.md5(strtolower($host).'|'.$port.'|'.strtolower((string) $this->mailEncryption));

        return (string) Cache::remember($cacheKey, now()->addSeconds(45), function () use ($host, $port): string {
            $transport = strtolower($this->mailEncryption) === 'ssl' ? 'ssl://' : '';

            $connection = @fsockopen($transport.$host, $port, $errorNumber, $errorMessage, 3);

            if ($connection === false) {
                return 'Offline ('.$errorNumber.' '.$errorMessage.')';
            }

            fclose($connection);

            return 'Online';
        });
    }

    private function checkSmsHealth(): string
    {
        if (trim($this->semaphoreApiKey) === '') {
            $this->smsCredits = null;
            $this->smsSentToday = 0;

            return 'Missing API key';
        }

        try {
            $apiHash = md5($this->semaphoreApiKey);
            $today = now()->toDateString();

            $rateLimitedUntil = Cache::get("semaphore_rate_limited_until_{$apiHash}");
            if (is_numeric($rateLimitedUntil) && (int) $rateLimitedUntil > now()->timestamp) {
                $this->applyCachedSmsSnapshot($apiHash, $today);

                return 'Rate limited (retry soon)';
            }

            $accountPayload = Cache::remember(
                "semaphore_account_{$apiHash}",
                now()->addMinutes(5),
                function (): array {
                    $response = Http::timeout(10)
                        ->get($this->semaphoreAccountUrl, [
                            'apikey' => $this->semaphoreApiKey,
                        ]);

                    return [
                        'ok' => $response->successful(),
                        'status' => $response->status(),
                        'retry_after' => $this->parseRetryAfterSeconds($response->header('Retry-After')),
                        'data' => $response->successful() ? $response->json() : null,
                    ];
                }
            );

            if (! ($accountPayload['ok'] ?? false)) {
                if ((int) ($accountPayload['status'] ?? 0) === 429) {
                    $retryAfter = (int) ($accountPayload['retry_after'] ?? 60);
                    $until = now()->addSeconds(max(15, $retryAfter));
                    Cache::put("semaphore_rate_limited_until_{$apiHash}", $until->timestamp, $until);

                    $this->applyCachedSmsSnapshot($apiHash, $today);

                    return 'Rate limited (HTTP 429)';
                }

                $this->smsCredits = null;
                $this->smsSentToday = 0;

                return 'Offline (HTTP '.(int) ($accountPayload['status'] ?? 0).')';
            }

            $account = is_array($accountPayload['data'] ?? null) ? $accountPayload['data'] : [];
            $this->smsCredits = isset($account['credit_balance']) ? (float) $account['credit_balance'] : null;
            Cache::put("semaphore_last_ok_account_{$apiHash}", ['credits' => $this->smsCredits], now()->addHours(6));

            $messagesPayload = Cache::remember(
                "semaphore_messages_{$apiHash}_{$today}",
                now()->addMinutes(2),
                function () use ($today): array {
                    $response = Http::timeout(10)
                        ->get($this->semaphoreMessagesUrl, [
                            'apikey' => $this->semaphoreApiKey,
                            'startDate' => $today,
                            'endDate' => $today,
                            'limit' => 1000,
                        ]);

                    return [
                        'ok' => $response->successful(),
                        'status' => $response->status(),
                        'retry_after' => $this->parseRetryAfterSeconds($response->header('Retry-After')),
                        'data' => $response->successful() ? $response->json() : null,
                    ];
                }
            );

            if (! ($messagesPayload['ok'] ?? false) && (int) ($messagesPayload['status'] ?? 0) === 429) {
                $retryAfter = (int) ($messagesPayload['retry_after'] ?? 60);
                $until = now()->addSeconds(max(15, $retryAfter));
                Cache::put("semaphore_rate_limited_until_{$apiHash}", $until->timestamp, $until);

                $this->applyCachedSmsSnapshot($apiHash, $today);

                return 'Rate limited (HTTP 429)';
            }

            $messages = $messagesPayload['data'] ?? null;
            $this->smsSentToday = is_array($messages) ? count($messages) : 0;
            Cache::put("semaphore_last_ok_sent_today_{$apiHash}_{$today}", $this->smsSentToday, now()->addHours(6));

            return 'Online';
        } catch (\Throwable) {
            $this->smsCredits = null;
            $this->smsSentToday = 0;

            return 'Offline';
        }
    }

    private function applyCachedSmsSnapshot(string $apiHash, string $today): void
    {
        $account = Cache::get("semaphore_last_ok_account_{$apiHash}");
        if (is_array($account) && array_key_exists('credits', $account)) {
            $this->smsCredits = is_numeric($account['credits']) ? (float) $account['credits'] : null;
        } else {
            $this->smsCredits = null;
        }

        $sentToday = Cache::get("semaphore_last_ok_sent_today_{$apiHash}_{$today}");
        $this->smsSentToday = is_numeric($sentToday) ? (int) $sentToday : 0;
    }

    private function parseRetryAfterSeconds(?string $retryAfterHeader): int
    {
        $value = trim((string) $retryAfterHeader);

        if ($value === '') {
            return 60;
        }

        if (ctype_digit($value)) {
            return max(15, min(3600, (int) $value));
        }

        try {
            $parsed = Carbon::parse($value);
            $diff = now()->diffInSeconds($parsed, false);

            return max(15, min(3600, $diff > 0 ? $diff : 60));
        } catch (\Throwable) {
            return 60;
        }
    }

    private function resolveEmailsSentToday(): int
    {
        $key = 'mail_sent_count_'.now()->toDateString();

        return (int) Cache::get($key, 0);
    }

    private function normalizeAlertThresholds(): void
    {
        $this->emailAlertThreshold = (string) $this->emailAlertThresholdValue();
        $this->smsLowCreditThreshold = number_format($this->smsLowCreditThresholdValue(), 2, '.', '');
    }

    private function emailAlertThresholdValue(): int
    {
        return max(1, min(100, (int) $this->emailAlertThreshold));
    }

    private function smsLowCreditThresholdValue(): float
    {
        return max(0, (float) $this->smsLowCreditThreshold);
    }

    /**
     * @return array<int>
     */
    private function normalizePartialPaymentOptions(?string $raw): array
    {
        $values = collect(explode(',', (string) $raw))
            ->map(fn (string $v): int => (int) trim($v))
            ->filter(fn (int $v): bool => $v > 0 && $v < 100)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return isset($values[0]) ? [(int) $values[0]] : [30];
    }

    private function normalizePartialPaymentSelection(int|string $raw, bool $allowCustom = false): int
    {
        $value = (int) $raw;

        if ($allowCustom) {
            return max(1, min(99, $value));
        }

        $allowed = $this->availablePartialPaymentOptions();

        return in_array($value, $allowed, true) ? $value : 30;
    }

    private function partialPaymentRules(): array
    {
        $rules = ['required', 'integer', 'min:1', 'max:99'];

        if (! $this->allowCustomPartialPayment) {
            $rules[] = Rule::in($this->availablePartialPaymentOptions());
        }

        return $rules;
    }

    /**
     * @return array<int>
     */
    private function availablePartialPaymentOptions(): array
    {
        return [10, 20, 30, 40, 50, 60, 70, 80, 90];
    }
}
