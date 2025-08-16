<?php

namespace App\Filament\Admin\Resources\VerificationProviderResource\Pages;

use App\Filament\Admin\Resources\VerificationProviderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVerificationProviders extends ListRecords
{
    protected static string $resource = VerificationProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
