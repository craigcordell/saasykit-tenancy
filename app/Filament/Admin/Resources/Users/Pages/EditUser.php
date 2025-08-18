<?php

namespace App\Filament\Admin\Resources\Users\Pages;

use App\Filament\Admin\Resources\Users\UserResource;
use App\Filament\CrudDefaults;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    use CrudDefaults;

    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('disable-two-factor-authentication')
                ->label(__('Disable Two-Factor Authentication'))
                ->color('gray')
                ->icon('heroicon-s-shield-exclamation')
                ->visible(function (User $record): bool {
                    return config('app.two_factor_auth_enabled') && $record->hasTwoFactorEnabled();
                })
                ->requiresConfirmation()
                ->action(function (User $record) {
                    $record->disableTwoFactorAuth();

                    Notification::make()
                        ->success()
                        ->title(__('Two-Factor Authentication Disabled'))
                        ->send();
                }),
        ];
    }
}
