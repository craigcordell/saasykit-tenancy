<?php

namespace App\Filament\Admin\Resources\Orders;

use App\Constants\DiscountConstants;
use App\Filament\Admin\Resources\Orders\Pages\ListOrders;
use App\Filament\Admin\Resources\Orders\Pages\ViewOrder;
use App\Filament\Admin\Resources\Users\Pages\EditUser;
use App\Mapper\OrderStatusMapper;
use App\Models\Order;
use App\Models\User;
use App\Services\CurrencyService;
use App\Services\OneTimeProductService;
use App\Services\OrderService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    public static function getNavigationGroup(): ?string
    {
        return __('Revenue');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label(__('User'))
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->label(__('Status'))
                    ->color(fn (Order $record, OrderStatusMapper $mapper): string => $mapper->mapColor($record->status))
                    ->formatStateUsing(
                        function (string $state, $record, OrderStatusMapper $mapper) {
                            return $mapper->mapForDisplay($state);
                        })
                    ->searchable(),
                TextColumn::make('total_amount')
                    ->label(__('Total Amount'))
                    ->formatStateUsing(function (string $state, $record) {
                        return money($state, $record->currency->code);
                    }),
                TextColumn::make('total_amount_after_discount')
                    ->label(__('Total Amount After Discount'))
                    ->formatStateUsing(function (string $state, $record) {
                        return money($state, $record->currency->code);
                    }),
                TextColumn::make('total_discount_amount')
                    ->label(__('Total Discount Amount'))
                    ->formatStateUsing(function (string $state, $record) {
                        return money($state, $record->currency->code);
                    }),
                TextColumn::make('payment_provider_id')
                    ->formatStateUsing(function (string $state, $record) {
                        return $record->paymentProvider->name;
                    })
                    ->label(__('Payment Provider'))
                    ->searchable(),
                IconColumn::make('is_local')
                    ->label(__('Is Local Order (Manual)'))
                    ->toggleable()
                    ->toggledHiddenByDefault()
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label(__('Updated At'))
                    ->dateTime(config('app.datetime_format'))
                    ->searchable()->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'user',
                'currency',
                'paymentProvider',
            ]))
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->headerActions([
                Action::make('create')
                    ->label(__('Create Order'))
                    ->schema([
                        Select::make('user_id')
                            ->label(__('User'))
                            ->searchable()
                            ->getSearchResultsUsing(function (string $query) {
                                return User::query()
                                    ->where('name', 'like', '%'.$query.'%')
                                    ->orWhere('email', 'like', '%'.$query.'%')
                                    ->limit(20)
                                    ->get()
                                    ->mapWithKeys(fn ($user) => [$user->id => "{$user->name} <{$user->email}>"])->toArray();
                            })
                            ->helperText(__('Adding an order manually to a user will add a zero amount order to the user\'s account, and user will be able to have access to any parts of your application that require a user to have ordered that product.'))
                            ->required(),
                        Select::make('one_time_product_id')
                            ->label(__('Product'))
                            ->options(function (OneTimeProductService $productService) {
                                return $productService->getAllProductsWithPrices()->mapWithKeys(function ($product) {
                                    return [$product->id => $product->name];
                                });
                            })
                            ->required(),
                        TextInput::make('quantity')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->required()
                            ->label(__('Quantity')),
                    ])
                    ->action(function (
                        array $data,
                        OrderService $orderService,
                        OneTimeProductService $oneTimeProductService,
                        CurrencyService $currencyService,
                    ) {
                        $user = User::find($data['user_id']);
                        $product = $oneTimeProductService->getActiveOneTimeProductById($data['one_time_product_id']);
                        $orderItem = [
                            'one_time_product_id' => $product->id,
                            'quantity' => $data['quantity'],
                            'price_per_unit' => 0,
                        ];

                        $orderService->create(
                            user: $user,
                            currency: $currencyService->getCurrency(),
                            orderItems: [$orderItem],
                            isLocal: true,
                        );

                        Notification::make()
                            ->title(__('Order created successfully.'))
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([

            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Order')
                    ->columnSpan('full')
                    ->tabs([
                        Tab::make(__('Details'))
                            ->schema([
                                Section::make(__('Order Details'))
                                    ->description(__('View details about this order.'))
                                    ->schema([
                                        TextEntry::make('uuid')->copyable(),
                                        // user
                                        TextEntry::make('user.name')
                                            ->url(fn (Order $record) => EditUser::getUrl(['record' => $record->user]))
                                            ->label(__('User')),
                                        TextEntry::make('payment_provider_id')
                                            ->formatStateUsing(function (string $state, $record) {
                                                return $record->paymentProvider->name;
                                            })
                                            ->label(__('Payment Provider')),
                                        TextEntry::make('total_amount')
                                            ->label(__('Total Amount'))
                                            ->formatStateUsing(function (string $state, $record) {
                                                return money($state, $record->currency->code);
                                            }),
                                        TextEntry::make('total_amount_after_discount')
                                            ->label(__('Total Amount After Discount'))
                                            ->formatStateUsing(function (string $state, $record) {
                                                return money($state, $record->currency->code);
                                            }),
                                        TextEntry::make('total_discount_amount')
                                            ->label(__('Total Discount Amount'))
                                            ->formatStateUsing(function (string $state, $record) {
                                                return money($state, $record->currency->code);
                                            }),
                                        TextEntry::make('status')
                                            ->badge()
                                            ->label(__('Status'))
                                            ->color(fn (Order $record, OrderStatusMapper $mapper): string => $mapper->mapColor($record->status))
                                            ->formatStateUsing(
                                                function (string $state, $record, OrderStatusMapper $mapper) {
                                                    return $mapper->mapForDisplay($state);
                                                }),
                                        TextEntry::make('discounts.amount')
                                            ->hidden(fn (Order $record): bool => $record->discounts()->count() === 0)
                                            ->formatStateUsing(function (string $state, $record) {
                                                if ($record->discounts[0]->type === DiscountConstants::TYPE_PERCENTAGE) {
                                                    return $state.'%';
                                                }

                                                return money($state, $record->discounts[0]->code);
                                            })->label(__('Discount Amount')),
                                        TextEntry::make('is_local')
                                            ->badge()
                                            ->formatStateUsing(function (string $state, $record) {
                                                return $state ? __('Yes') : __('No');
                                            })
                                            ->label(__('Is Local Order (Manual)')),
                                        TextEntry::make('comments')
                                            ->label(__('Comments'))
                                            ->html()
                                            ->visible(fn (Order $record): bool => $record->comments !== null && $record->comments !== ''),
                                        TextEntry::make('created_at')->dateTime(config('app.datetime_format'))->label(__('Created At')),
                                        TextEntry::make('updated_at')->dateTime(config('app.datetime_format'))->label(__('Updated At')),
                                    ])->columns(3),
                                Section::make(__('Order Items'))
                                    ->description(__('View details about order items.'))
                                    ->schema(
                                        function ($record) {
                                            // Filament schema is called multiple times for some reason, so we need to cache the components to avoid performance issues.
                                            return static::orderItems($record);
                                        },
                                    ),
                            ]),
                    ]),

            ]);

    }

    public static function orderItems(Order $order): array
    {
        $result = [];

        $i = 0;
        foreach ($order->items()->get() as $item) {
            $section = Section::make(function () use ($item) {
                return $item->oneTimeProduct->name;
            })
                ->schema([
                    TextEntry::make('items.quantity_'.$i)->getStateUsing(fn () => $item->quantity)->label(__('Quantity')),
                    TextEntry::make('items.price_per_unit_'.$i)->getStateUsing(fn () => money($item->price_per_unit, $order->currency->code))->label(__('Price Per Unit')),
                    TextEntry::make('items.price_per_unit_after_discount_'.$i)->getStateUsing(fn () => money($item->price_per_unit_after_discount, $order->currency->code))->label(__('Price Per Unit After Discount')),
                    TextEntry::make('items.discount_per_unit_'.$i)->getStateUsing(fn () => money($item->discount_per_unit, $order->currency->code))->label(__('Discount Per Unit')),
                ])
                ->columns(4);

            $result[] = $section;
            $i++;
        }

        return $result;
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
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function getNavigationLabel(): string
    {
        return __('Orders');
    }

    public static function getModelLabel(): string
    {
        return __('Order');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Orders');
    }
}
