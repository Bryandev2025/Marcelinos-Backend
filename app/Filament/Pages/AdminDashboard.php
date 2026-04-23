<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard;

class AdminDashboard extends Dashboard
{
    protected static bool $isDiscovered = false;

    protected static ?string $slug = 'dashboard';

    protected string $view = 'filament.pages.admin-dashboard';
}
