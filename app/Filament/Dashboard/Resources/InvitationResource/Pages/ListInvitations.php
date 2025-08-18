<?php

namespace App\Filament\Dashboard\Resources\InvitationResource\Pages;

use App\Filament\Dashboard\Resources\InvitationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInvitations extends ListRecords
{
    protected static string $resource = InvitationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
