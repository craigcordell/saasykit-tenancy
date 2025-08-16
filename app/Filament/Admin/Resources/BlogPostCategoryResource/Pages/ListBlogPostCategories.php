<?php

namespace App\Filament\Admin\Resources\BlogPostCategoryResource\Pages;

use App\Filament\Admin\Resources\BlogPostCategoryResource;
use App\Filament\ListDefaults;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBlogPostCategories extends ListRecords
{
    use ListDefaults;

    protected static string $resource = BlogPostCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
