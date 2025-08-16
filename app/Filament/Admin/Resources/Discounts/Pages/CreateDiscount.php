<?php

namespace App\Filament\Admin\Resources\Discounts\Pages;

use App\Filament\Admin\Resources\Discounts\DiscountResource;
use App\Filament\CrudDefaults;
use Filament\Resources\Pages\CreateRecord;

class CreateDiscount extends CreateRecord
{
    use CrudDefaults;

    protected static string $resource = DiscountResource::class;
}
