<?php

namespace App\Filament\Admin\Resources\PaymentProviderResource\Pages;

use App\Filament\Admin\Resources\PaymentProviderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPaymentProviders extends ListRecords
{
    protected static string $resource = PaymentProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
