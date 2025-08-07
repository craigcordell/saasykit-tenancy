<?php

namespace App\Filament\Admin\Resources\SubscriptionResource\Pages;

use App\Constants\SubscriptionStatus;
use App\Filament\Admin\Resources\SubscriptionResource;
use App\Filament\ListDefaults;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListSubscriptions extends ListRecords
{
    use ListDefaults;

    protected static string $resource = SubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            __('all') => Tab::make(),
            __('active') => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', SubscriptionStatus::ACTIVE)),
            __('inactive') => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', SubscriptionStatus::INACTIVE)),
            __('pending') => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', SubscriptionStatus::PENDING)),
            __('canceled') => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', SubscriptionStatus::CANCELED)),
            __('past Due') => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', SubscriptionStatus::PAST_DUE)),
        ];
    }
}
