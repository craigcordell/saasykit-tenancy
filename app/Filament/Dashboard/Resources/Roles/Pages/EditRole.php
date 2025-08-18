<?php

namespace App\Filament\Dashboard\Resources\Roles\Pages;

use App\Filament\CrudDefaults;
use App\Filament\Dashboard\Resources\Roles\RoleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRole extends EditRecord
{
    use CrudDefaults;

    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
