<?php

return [
    App\Providers\ConfigProvider::class,
    App\Providers\BladeProvider::class,
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    App\Providers\Filament\AdminPanelProvider::class,
    App\Providers\Filament\DashboardPanelProvider::class,
    App\Providers\HorizonServiceProvider::class,
    App\Providers\RouteServiceProvider::class,
    Spatie\Permission\PermissionServiceProvider::class,
];
