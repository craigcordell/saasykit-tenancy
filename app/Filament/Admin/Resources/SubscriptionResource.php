<?php

namespace App\Filament\Admin\Resources;

use App\Constants\DiscountConstants;
use App\Constants\PlanPriceTierConstants;
use App\Constants\PlanPriceType;
use App\Constants\PlanType;
use App\Constants\SubscriptionStatus;
use App\Constants\SubscriptionType;
use App\Exceptions\SubscriptionCreationNotAllowedException;
use App\Filament\Admin\Resources\TenantResource\Pages\EditTenant;
use App\Mapper\SubscriptionStatusMapper;
use App\Models\Subscription;
use App\Models\User;
use App\Services\CurrencyService;
use App\Services\PlanService;
use App\Services\SubscriptionService;
use App\Services\TenantCreationService;
use App\Services\TenantService;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static array $cachedSubscriptionHistoryComponents = [];

    public static function getNavigationGroup(): ?string
    {
        return __('Revenue');
    }

    public static function form(Form $form): Form
    {
        /** @var CurrencyService $currencyService */
        $currencyService = resolve(CurrencyService::class);

        return $form
            ->schema([
                Forms\Components\Tabs::make('Subscription')
                    ->columnSpan('full')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make(__('Details'))
                            ->schema([
                                Forms\Components\Section::make()->schema([
                                    Forms\Components\Select::make('user_id')
                                        ->label(__('User'))
                                        ->relationship('user', 'name')
                                        ->preload()
                                        ->required(),
                                    Forms\Components\Select::make('plan_id')
                                        ->label(__('Plan'))
                                        ->relationship('plan', 'name')
                                        ->preload()
                                        ->required(),
                                    Forms\Components\TextInput::make('price')
                                        ->label(__('Price'))
                                        ->required(),
                                    Forms\Components\Select::make('currency_id')
                                        ->options(
                                            $currencyService->getAllCurrencies()
                                                ->mapWithKeys(function ($currency) {
                                                    return [$currency->id => $currency->name.' ('.$currency->symbol.')'];
                                                })
                                                ->toArray()
                                        )
                                        ->label(__('Currency'))
                                        ->required(),
                                    Forms\Components\DateTimePicker::make('renew_at')
                                        ->label(__('Next Renewal'))
                                        ->displayFormat(config('app.datetime_format')),
                                    Forms\Components\DateTimePicker::make('cancelled_at')
                                        ->label(__('Cancelled At'))
                                        ->displayFormat(config('app.datetime_format')),
                                    Forms\Components\DateTimePicker::make('grace_period_ends_at')
                                        ->label(__('Grace Period Ends At'))
                                        ->displayFormat(config('app.datetime_format')),
                                    Forms\Components\Toggle::make('is_active')
                                        ->label(__('Active'))
                                        ->required(),
                                    Forms\Components\Toggle::make('is_trial_active')
                                        ->label(__('Trial Active'))
                                        ->required(),
                                ]),
                            ]),
                    ]),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('tenant.name')->label(__('Tenant'))->searchable(),
                Tables\Columns\TextColumn::make('plan.name')->label(__('Plan'))->searchable(),
                Tables\Columns\TextColumn::make('price')
                    ->label(__('Price'))
                    ->formatStateUsing(function (string $state, $record) {
                        $interval = $record->interval->name;
                        if ($record->interval_count > 1) {
                            $interval = $record->interval_count.' '.__(str()->of($record->interval->name)->plural()->toString());
                        }

                        if ($record->plan->type === PlanType::SEAT_BASED->value) {
                            $interval .= ' / '.__('seat');
                        }

                        return money($state, $record->currency->code).' / '.$interval;
                    }),
                Tables\Columns\TextColumn::make('payment_provider_id')
                    ->formatStateUsing(function (string $state, $record) {
                        return $record->paymentProvider->name;
                    })
                    ->label(__('Payment Provider'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->color(fn (Subscription $record, SubscriptionStatusMapper $mapper): string => $mapper->mapColor($record->status))
                    ->formatStateUsing(
                        function (string $state, $record, SubscriptionStatusMapper $mapper) {
                            return $mapper->mapForDisplay($state);
                        })
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')->label(__('Created At'))
                    ->dateTime(config('app.datetime_format'))
                    ->searchable()->sortable(),
                Tables\Columns\TextColumn::make('updated_at')->label(__('Updated At'))
                    ->dateTime(config('app.datetime_format'))
                    ->searchable()->sortable(),

            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('create')
                    ->label(__('Create Subscription'))
                    ->form([
                        Forms\Components\Select::make('user_id')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->label(__('User'))
                            ->getSearchResultsUsing(function (string $query) {
                                return \App\Models\User::query()
                                    ->where('name', 'like', '%'.$query.'%')
                                    ->orWhere('email', 'like', '%'.$query.'%')
                                    ->limit(20)
                                    ->get()
                                    ->mapWithKeys(fn ($user) => [$user->id => "{$user->name} <{$user->email}>"])->toArray();
                            })
                            ->helperText(__('Adding a subscription to a user will create a "locally managed" subscription, which means the user will be able to use subscription features without being billed, and they can later convert to a "payment provider managed" subscription from their dashboard.'))
                            ->live()
                            ->required(),
                        Forms\Components\Select::make('tenant_uuid')
                            ->label(__('Tenant'))
                            ->helperText(__('Select the tenant for which you want to create a subscription. If the user has multiple tenants, you can select one of them. If you do not select a tenant, a new tenant will be created for the user.'))
                            ->options(function (Forms\Get $get, TenantCreationService $tenantCreationService) {
                                $userId = $get('user_id');
                                if (! $userId) {
                                    return [];
                                }

                                return $tenantCreationService->findUserTenantsForNewSubscription(User::find($userId))
                                    ->pluck('name', 'uuid')
                                    ->toArray();
                            }),
                        \Filament\Forms\Components\Select::make('plan_id')
                            ->label(__('Plan'))
                            ->options(function (PlanService $planService) {
                                return $planService->getAllPlansWithPrices()->mapWithKeys(function ($plan) {
                                    return [$plan->id => $plan->name];
                                });
                            })
                            ->required(),
                        Forms\Components\DateTimePicker::make('ends_at')
                            ->displayFormat(config('app.datetime_format'))
                            ->label(__('Ends At'))
                            ->afterOrEqual('now')
                            ->helperText(__('The date when the subscription will end.'))
                            ->required(),
                    ])
                    ->action(function (
                        array $data,
                        SubscriptionService $subscriptionService,
                        PlanService $planService,
                        TenantCreationService $tenantCreationService,
                        TenantService $tenantService,
                    ) {
                        $user = User::find($data['user_id']);
                        $plan = $planService->getActivePlanById($data['plan_id']);
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

                        try {
                            $subscriptionService->create(
                                $plan->slug,
                                $user->id,
                                quantity: 1,
                                tenant: $tenant,
                                localSubscription: true,
                                endsAt: Carbon::parse($data['ends_at'])
                            );
                        } catch (SubscriptionCreationNotAllowedException $e) {
                            Notification::make()
                                ->title(__('Failed to create subscription. User already has an active subscription and cannot have more than one.'))
                                ->danger()
                                ->send();
                        }

                        Notification::make()
                            ->title(__('Subscription created successfully.'))
                            ->success()
                            ->send();
                    }),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'user',
                'currency',
                'paymentProvider',
                'interval',
            ]))
            ->bulkActions([
            ])->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            'usages' => \App\Filament\Admin\Resources\SubscriptionResource\RelationManagers\UsagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Admin\Resources\SubscriptionResource\Pages\ListSubscriptions::route('/'),
            'view' => \App\Filament\Admin\Resources\SubscriptionResource\Pages\ViewSubscription::route('/{record}'),
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

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    /**
     * Filament schema is called multiple times for some reason, so we need to cache the components to avoid performance issues.
     */
    public static function subscriptionHistoryComponents($record): array
    {
        if (! empty(static::$cachedSubscriptionHistoryComponents)) {
            return static::$cachedSubscriptionHistoryComponents;
        }

        $i = 0;
        foreach ($record->versions->reverse() as $version) {
            $versionModel = $version->getModel();

            $user = $versionModel->user;
            $plan = $versionModel->plan;

            static::$cachedSubscriptionHistoryComponents[] = Section::make([
                TextEntry::make('plan_name_'.$i)
                    ->label(__('Plan'))
                    ->getStateUsing(fn () => $plan->name),

                TextEntry::make('status_'.$i)
                    ->label(__('Status'))
                    ->color(fn ($record, SubscriptionStatusMapper $mapper): string => $mapper->mapColor($record->status))
                    ->badge()
                    ->getStateUsing(fn ($record, SubscriptionStatusMapper $mapper): string => $mapper->mapForDisplay($record->status)),

                TextEntry::make('changed_by_'.$i)
                    ->label(__('Changed By'))
                    ->getStateUsing(fn () => $user->name),

                TextEntry::make('ends_at_'.$i)
                    ->label(__('Ends At'))
                    ->getStateUsing(fn () => date(config('app.datetime_format'), strtotime($versionModel->ends_at))),

                TextEntry::make('payment_provider_status_'.$i)
                    ->color('info')
                    ->label(__('Payment Provider Status'))
                    ->badge()
                    ->getStateUsing(fn () => $versionModel->payment_provider_status ?? '-'),

            ])->columns(5)->collapsible()->heading(
                date(config('app.datetime_format'), strtotime($version->created_at))
            );

            $i++;
        }

        return static::$cachedSubscriptionHistoryComponents;
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                \Filament\Infolists\Components\Tabs::make('Subscription')
                    ->columnSpan('full')
                    ->tabs([
                        \Filament\Infolists\Components\Tabs\Tab::make(__('Details'))
                            ->schema([
                                Section::make(__('Subscription Details'))
                                    ->description(__('View details about subscription.'))
                                    ->schema([
                                        ViewEntry::make('status')
                                            ->label(__('Status'))
                                            ->visible(fn (Subscription $record): bool => $record->status === SubscriptionStatus::PAST_DUE->value)
                                            ->view('filament.common.infolists.entries.warning', [
                                                'message' => __('Subscription is past due.'),
                                            ]),
                                        TextEntry::make('plan.name')
                                            ->label(__('Plan')),
                                        TextEntry::make('price')
                                            ->label(__('Price'))
                                            ->formatStateUsing(function (string $state, $record) {
                                                $interval = $record->interval->name;
                                                if ($record->interval_count > 1) {
                                                    $interval = $record->interval_count.' '.__(str()->of($record->interval->name)->plural()->toString());
                                                }

                                                if ($record->plan->type === PlanType::SEAT_BASED->value) {
                                                    $interval .= ' / '.__('seat');
                                                }

                                                return money($state, $record->currency->code).' / '.$interval;
                                            }),
                                        TextEntry::make('price_per_unit')
                                            ->label(__('Price Per Unit'))
                                            ->visible(fn (Subscription $record): bool => $record->price_type === PlanPriceType::USAGE_BASED_PER_UNIT->value && $record->price_per_unit !== null)
                                            ->formatStateUsing(function (string $state, $record) {
                                                return money($state, $record->currency->code).' / '.__($record->plan->meter->name);
                                            }),
                                        TextEntry::make('price_tiers')
                                            ->label(__('Price Tiers'))
                                            ->visible(fn (Subscription $record): bool => in_array($record->price_type, [PlanPriceType::USAGE_BASED_TIERED_VOLUME->value, PlanPriceType::USAGE_BASED_TIERED_GRADUATED->value]) && $record->price_tiers !== null)
                                            ->getStateUsing(function (Subscription $record) {
                                                $start = 0;
                                                $unitMeterName = $record->plan->meter->name;
                                                $currencyCode = $record->currency->code;
                                                $output = '';
                                                $startingPhrase = __('From');
                                                foreach ($record->price_tiers as $tier) {
                                                    $output .= $startingPhrase.' '.$start.' - '.$tier[PlanPriceTierConstants::UNTIL_UNIT].' '.__(str()->plural($unitMeterName)).' → '.money($tier[PlanPriceTierConstants::PER_UNIT], $currencyCode).' / '.__($unitMeterName);
                                                    if ($tier[PlanPriceTierConstants::FLAT_FEE] > 0) {
                                                        $output .= ' + '.money($tier[PlanPriceTierConstants::FLAT_FEE], $currencyCode);
                                                    }
                                                    $start = intval($tier[PlanPriceTierConstants::UNTIL_UNIT]) + 1;
                                                    $output .= '<br>';

                                                    if ($record->price_type === PlanPriceType::USAGE_BASED_TIERED_GRADUATED->value) {
                                                        $startingPhrase = __('Next');
                                                    }
                                                }

                                                return new HtmlString($output);
                                            }),
                                        TextEntry::make('payment_provider_id')
                                            ->formatStateUsing(function (string $state, $record) {
                                                return $record->paymentProvider->name;
                                            })
                                            ->label(__('Payment Provider')),
                                        TextEntry::make('payment_provider_subscription_id')
                                            ->label(__('Payment Provider Subscription ID')),
                                        TextEntry::make('ends_at')->dateTime(config('app.datetime_format'))->label(__('Next Renewal'))->visible(fn (Subscription $record): bool => ! $record->is_canceled_at_end_of_cycle),
                                        TextEntry::make('trial_ends_at')->dateTime(config('app.datetime_format'))->label(__('Trial Ends At'))->visible(fn (Subscription $record): bool => $record->trial_ends_at !== null),
                                        TextEntry::make('status')
                                            ->label(__('Status'))
                                            ->badge()
                                            ->color(fn (Subscription $record, SubscriptionStatusMapper $mapper): string => $mapper->mapColor($record->status))
                                            ->formatStateUsing(fn (string $state, SubscriptionStatusMapper $mapper): string => $mapper->mapForDisplay($state)),
                                        TextEntry::make('type')->badge()->color('info')
                                            ->label(__('Type'))
                                            ->formatStateUsing(
                                                function (string $state) {
                                                    switch ($state) {
                                                        case SubscriptionType::PAYMENT_PROVIDER_MANAGED:
                                                            return __('Payment Provider Managed');
                                                        case SubscriptionType::LOCALLY_MANAGED:
                                                            return __('Locally Managed');
                                                    }

                                                    return $state;
                                                }),
                                        TextEntry::make('is_canceled_at_end_of_cycle')
                                            ->label(__('Renews automatically'))
                                            ->visible(fn (Subscription $record, SubscriptionService $subscriptionService): bool => $subscriptionService->canCancelSubscription($record))
                                            ->icon(function ($state) {
                                                $state = boolval($state);

                                                return $state ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle';
                                            })->formatStateUsing(
                                                function ($state) {
                                                    return boolval($state) ? __('No') : __('Yes');
                                                }),
                                        TextEntry::make('cancellation_reason')
                                            ->label(__('Cancellation Reason'))
                                            ->visible(fn (Subscription $record): bool => $record->cancellation_reason !== null),
                                        TextEntry::make('cancellation_additional_info')
                                            ->label(__('Cancellation Additional Info'))
                                            ->visible(fn (Subscription $record): bool => $record->cancellation_additional_info !== null),
                                        TextEntry::make('tenant.name')
                                            ->url(fn (Subscription $record) => EditTenant::getUrl(['record' => $record->tenant]))
                                            ->label(__('Tenant')),
                                        TextEntry::make('quantity')
                                            ->label(__('Quantity'))
                                            ->visible(fn (Subscription $record): bool => $record->plan->type === PlanType::SEAT_BASED->value),
                                        TextEntry::make('comments')
                                            ->label(__('Comments'))
                                            ->html()
                                            ->visible(fn (Subscription $record): bool => $record->comments !== null && $record->comments !== ''),
                                    ]),
                                Section::make(__('Discount Details'))
                                    ->hidden(fn (Subscription $record): bool => $record->discounts->isEmpty() ||
                                        ($record->discounts[0]->valid_until !== null && $record->discounts[0]->valid_until < now())
                                    )
                                    ->description(__('View details about subscription discount.'))
                                    ->schema([
                                        TextEntry::make('discounts.amount')
                                            ->label(__('Discount Amount'))
                                            ->formatStateUsing(function (string $state, $record) {
                                                if ($record->discounts[0]->type === DiscountConstants::TYPE_PERCENTAGE) {
                                                    return $state.'%';
                                                }

                                                return money($state, $record->discounts[0]->code);
                                            }),

                                        TextEntry::make('discounts.valid_until')->dateTime(config('app.datetime_format'))->label(__('Valid Until')),
                                    ]),

                            ]),
                        \Filament\Infolists\Components\Tabs\Tab::make(__('Changes'))
                            ->schema(
                                function ($record) {
                                    // Filament schema is called multiple times for some reason, so we need to cache the components to avoid performance issues.
                                    return static::subscriptionHistoryComponents($record);
                                },
                            ),
                    ]),

            ]);

    }

    public static function getNavigationLabel(): string
    {
        return __('Subscriptions');
    }
}
