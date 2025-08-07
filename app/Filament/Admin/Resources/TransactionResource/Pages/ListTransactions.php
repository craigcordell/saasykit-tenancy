<?php

namespace App\Filament\Admin\Resources\TransactionResource\Pages;

use App\Constants\TransactionStatus;
use App\Filament\Admin\Resources\TransactionResource;
use App\Filament\Admin\Resources\TransactionResource\Widgets\TransactionOverview;
use App\Filament\ListDefaults;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListTransactions extends ListRecords
{
    use ListDefaults;

    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            TransactionOverview::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            __('all') => Tab::make(),
            __('success') => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', TransactionStatus::SUCCESS)),
            __('refunded') => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', TransactionStatus::REFUNDED)),
            __('failed') => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', TransactionStatus::FAILED)),
            __('pending') => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', TransactionStatus::PENDING)),
            __('disputed') => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', TransactionStatus::DISPUTED)),
        ];
    }
}
