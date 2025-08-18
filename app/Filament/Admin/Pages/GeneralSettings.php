<?php

namespace App\Filament\Admin\Pages;

use App\Services\ConfigService;
use Filament\Pages\Page;

class GeneralSettings extends Page
{
    protected string $view = 'filament.admin.pages.general-settings';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    public static function canAccess(): bool
    {
        $configService = app()->make(ConfigService::class);

        return $configService->isAdminSettingsEnabled()
            && auth()->user()
            && auth()->user()->hasPermissionTo('update settings');
    }
}
