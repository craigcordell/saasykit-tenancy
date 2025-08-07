<?php

namespace App\Filament\Admin\Resources;

use App\Constants\DiscountConstants;
use App\Filament\Admin\Resources\OrderResource\Pages;
use App\Filament\Admin\Resources\TenantResource\Pages\EditTenant;
use App\Mapper\OrderStatusMapper;
use App\Models\Order;
use App\Models\User;
use App\Services\CurrencyService;
use App\Services\OneTimeProductService;
use App\Services\OrderService;
use App\Services\TenantCreationService;
use App\Services\TenantService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    public static function getNavigationGroup(): ?string
    {
        return __('Revenue');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('tenant.name')->label(__('Tenant'))->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->label(__('Status'))
                    ->color(fn (Order $record, OrderStatusMapper $mapper): string => $mapper->mapColor($record->status))
                    ->formatStateUsing(
                        function (string $state, $record, OrderStatusMapper $mapper) {
                            return $mapper->mapForDisplay($state);
                        })
                    ->searchable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label(__('Total Amount'))
                    ->formatStateUsing(function (string $state, $record) {
                        return money($state, $record->currency->code);
                    }),
                Tables\Columns\TextColumn::make('total_amount_after_discount')
                    ->label(__('Total Amount After Discount'))
                    ->formatStateUsing(function (string $state, $record) {
                        return money($state, $record->currency->code);
                    }),
                Tables\Columns\TextColumn::make('total_discount_amount')
                    ->label(__('Total Discount Amount'))
                    ->formatStateUsing(function (string $state, $record) {
                        return money($state, $record->currency->code);
                    }),
                Tables\Columns\TextColumn::make('payment_provider_id')
                    ->formatStateUsing(function (string $state, $record) {
                        return $record->paymentProvider->name;
                    })
                    ->label(__('Payment Provider'))
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_local')
                    ->label(__('Is Local Order (Manual)'))
                    ->toggleable()
                    ->toggledHiddenByDefault()
                    ->boolean(),
                Tables\Columns\TextColumn::make('updated_at')
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
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('create')
                    ->label(__('Create Order'))
                    ->form([
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
                            ->live()
                            ->label(__('User'))
                            ->required(),
                        Select::make('tenant_uuid')
                            ->label(__('Tenant'))
                            ->helperText(__('Select the tenant for which you want to create a order. If the user has multiple tenants, you can select one of them. If you do not select a tenant, a new tenant will be created for the user.'))
                            ->options(function (Get $get, TenantCreationService $tenantCreationService) {
                                $userId = $get('user_id');
                                if (! $userId) {
                                    return [];
                                }

                                return $tenantCreationService->findUserTenantsForNewOrder(User::find($userId))
                                    ->pluck('name', 'uuid')
                                    ->toArray();
                            }),
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
                        TenantCreationService $tenantCreationService,
                        TenantService $tenantService,
                    ) {
                        $user = User::find($data['user_id']);
                        $product = $oneTimeProductService->getActiveOneTimeProductById($data['one_time_product_id']);
                        $orderItem = [
                            'one_time_product_id' => $product->id,
                            'quantity' => $data['quantity'],
                            'price_per_unit' => 0,
                        ];

                        $selectedTenantUuid = $data['tenant_uuid'] ?? null;

                        if ($selectedTenantUuid !== null) {
                            $tenant = $tenantService->getTenantByUuid($selectedTenantUuid);
                            if (! $tenant) {
                                Notification::make()
                                    ->title(__('Selected tenant not found.'))
                                    ->danger()
                                    ->send();

                                return;
                            }
                        } else {
                            $tenant = $tenantCreationService->createTenant($user);
                        }

                        $orderService->create(
                            user: $user,
                            tenant: $tenant,
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
            ->bulkActions([

            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                \Filament\Infolists\Components\Tabs::make('Order')
                    ->columnSpan('full')
                    ->tabs([
                        \Filament\Infolists\Components\Tabs\Tab::make(__('Details'))
                            ->schema([
                                Section::make(__('Order Details'))
                                    ->description(__('View details about this order.'))
                                    ->schema([
                                        TextEntry::make('uuid')->copyable(),
                                        TextEntry::make('tenant.name')
                                            ->url(fn (Order $record) => EditTenant::getUrl(['record' => $record->tenant]))
                                            ->label(__('Tenant')),
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
            'index' => Pages\ListOrders::route('/'),
            'view' => Pages\ViewOrder::route('/{record}'),
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
