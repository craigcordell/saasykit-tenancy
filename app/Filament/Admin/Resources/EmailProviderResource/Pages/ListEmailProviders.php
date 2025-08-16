<?php

namespace App\Filament\Admin\Resources\EmailProviderResource\Pages;

use App\Filament\Admin\Resources\EmailProviderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEmailProviders extends ListRecords
{
    protected static string $resource = EmailProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
