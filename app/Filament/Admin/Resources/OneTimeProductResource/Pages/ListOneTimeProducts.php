<?php

namespace App\Filament\Admin\Resources\OneTimeProductResource\Pages;

use App\Filament\Admin\Resources\OneTimeProductResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOneTimeProducts extends ListRecords
{
    protected static string $resource = OneTimeProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
