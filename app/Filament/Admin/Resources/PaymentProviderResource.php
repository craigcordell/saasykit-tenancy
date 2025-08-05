<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PaymentProviderResource\Pages;
use App\Models\PaymentProvider;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class PaymentProviderResource extends Resource
{
    protected static ?string $model = PaymentProvider::class;

    public static function getNavigationGroup(): ?string
    {
        return __('Settings');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()->schema([
                    Forms\Components\TextInput::make('name')
                        ->label(__('Name'))
                        ->required()
                        ->helperText(__('The name of the payment provider (shown on checkout page).'))
                        ->maxLength(255),
                    Forms\Components\Toggle::make('is_active')
                        ->label(__('Active'))
                        ->helperText(__('Deactivating this payment provider will prevent it from being used for new & old subscriptions. Customers will not be able to pay for their services so USE WITH CAUTION.'))
                        ->required(),
                    Forms\Components\Toggle::make('is_enabled_for_new_payments')
                        ->label(__('Enabled for new payments'))
                        ->helperText(__('If disabled, this payment provider will not be shown on the checkout page, but will still be available for existing subscriptions and receiving webhooks.'))
                        ->required(),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort')
            ->columns([
                Tables\Columns\TextColumn::make('icon')
                    ->label(__('Icon'))
                    ->getStateUsing(function (PaymentProvider $record) {
                        return new HtmlString(
                            '<div class="flex gap-2">'.
                            ' <img src="'.asset('images/payment-providers/'.$record->slug.'.png').'" alt="'.$record->name.'" class="h-6"> '
                            .'</div>'
                        );
                    }),
                Tables\Columns\TextColumn::make('name')->label(__('Name')),
                Tables\Columns\TextColumn::make('slug')
                    ->label(__('Slug'))
                    ->searchable(),
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label(__('Active')),
                Tables\Columns\ToggleColumn::make('is_enabled_for_new_payments')
                    ->label(__('Enabled for new payments')),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
            ])
            ->defaultSort('sort', 'asc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaymentProviders::route('/'),
            'edit' => Pages\EditPaymentProvider::route('/{record}/edit'),
            'stripe-settings' => Pages\StripeSettings::route('/stripe-settings'),
            'paddle-settings' => Pages\PaddleSettings::route('/paddle-settings'),
            'lemon-squeezy-settings' => Pages\LemonSqueezySettings::route('/lemon-squeezy-settings'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getNavigationLabel(): string
    {
        return __('Payment Providers');
    }
}
