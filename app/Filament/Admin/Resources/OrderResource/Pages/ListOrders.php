<?php

namespace App\Filament\Admin\Resources\OrderResource\Pages;

use App\Constants\OrderStatus;
use App\Filament\Admin\Resources\OrderResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            __('all') => Tab::make(),
            __('success') => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', OrderStatus::SUCCESS)),
            __('refunded') => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', OrderStatus::REFUNDED)),
            __('pending') => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', OrderStatus::PENDING)),
            __('failed') => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', OrderStatus::FAILED)),
            __('disputed') => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', OrderStatus::DISPUTED)),
        ];
    }
}
