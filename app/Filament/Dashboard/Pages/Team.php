<?php

namespace App\Filament\Dashboard\Pages;

use App\Constants\TenancyPermissionConstants;
use App\Services\TenantPermissionService;
use Filament\Facades\Filament;
use Filament\Pages\Page;

class Team extends Page
{
    protected string $view = 'filament.dashboard.pages.team';

    public static function getNavigationGroup(): ?string
    {
        return __('Team');
    }

    public static function getNavigationLabel(): string
    {
        return __('Team Members');
    }

    public static function canAccess(): bool
    {
        $tenantPermissionService = app(TenantPermissionService::class); // a bit ugly, but this is the Filament way :/

        return config('app.allow_tenant_invitations', false) && $tenantPermissionService->tenantUserHasPermissionTo(
            Filament::getTenant(),
            auth()->user(),
            TenancyPermissionConstants::PERMISSION_MANAGE_TEAM
        );
    }
}
