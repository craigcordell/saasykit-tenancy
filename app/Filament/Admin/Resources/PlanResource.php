<?php

namespace App\Filament\Admin\Resources;

use App\Constants\PlanType;
use App\Mapper\PlanTypeMapper;
use App\Models\Interval;
use App\Models\Plan;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return __('Product Management');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->label(__('Name'))
                        ->maxLength(255),
                    Forms\Components\TextInput::make('slug')
                        ->nullable()
                        ->label(__('Slug'))
                        ->dehydrateStateUsing(function ($state, \Filament\Forms\Get $get) {
                            if (empty($state)) {
                                $product = Product::find($get('product_id'));
                                $interval = Interval::find($get('interval_id'));
                                $intervalCount = $get('interval_count');
                                $intervalCountPart = $intervalCount > 1 ? '-'.$intervalCount : '';
                                $intervalPart = $interval ? $intervalCountPart.'-'.$interval->adverb : '';

                                // add a random string if there is a plan with the same slug
                                $state = Str::slug($product->name.$intervalPart);
                                if (Plan::where('slug', $state)->exists()) {
                                    $state .= '-'.Str::random(5);
                                }

                                return Str::slug($state);
                            }

                            return $state;
                        })
                        ->helperText(__('Leave empty to generate slug automatically from product name & interval.'))
                        ->maxLength(255)
                        ->rules(['alpha_dash'])
                        ->unique(ignoreRecord: true)
                        ->disabledOn('edit'),
                    Forms\Components\Radio::make('type')
                        ->label(__('Type'))
                        ->helperText(
                            new HtmlString(
                                __('Flat Rate: Fixed price per billing cycle. Seat Based: Charge per seat/user for each billing cycle. Usage Based: Price per unit with optional tiers.').'<br><strong>'.__('Important').'</strong>: '.__('Usage-based pricing is not supported for Paddle.')
                            )
                        )
                        ->options([
                            PlanType::FLAT_RATE->value => __('Flat Rate'),
                            PlanType::SEAT_BASED->value => __('Seat-Based'),
                            PlanType::USAGE_BASED->value => __('Usage-based'),
                        ])
                        ->default(PlanType::FLAT_RATE->value)
                        ->disabledOn('edit')
                        ->live()
                        ->required(),
                    Forms\Components\TextInput::make('max_users_per_tenant')
                        ->label(__('Max Users Per Tenant'))
                        ->helperText(__('The maximum number of users that can be added to a tenant (team). Use that to limited the number of users that can use this plan for each tenant. Use "0" for unlimited.'))
                        ->integer()
                        ->default(0)
                        ->minValue(0)
                        ->required(),
                    Forms\Components\Select::make('meter_id')
                        ->label(__('Meter'))
                        ->relationship('meter', 'name')
                        ->searchable()
                        ->preload()
                        ->createOptionForm([
                            Forms\Components\TextInput::make('name')
                                ->helperText(__('The name of the meter. Please use singular form, for example: "Token" instead of "Tokens".'))
                                ->required()
                                ->maxLength(255),
                        ])
                        ->visible(function (\Filament\Forms\Get $get) {
                            return $get('type') === PlanType::USAGE_BASED->value;
                        })
                        ->required(function (\Filament\Forms\Get $get) {
                            return $get('type') === PlanType::USAGE_BASED->value;
                        }),
                    Forms\Components\Select::make('product_id')
                        // only products with is_default = false can be selected
                        ->relationship('product', 'name', modifyQueryUsing: fn (Builder $query) => $query->where('is_default', false))
                        ->label(__('Product'))
                        ->required()
                        ->preload(),
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('interval_count')
                            ->label(__('Interval Count'))
                            ->required()
                            ->integer()
                            ->minValue(1)
                            ->default(1)
                            ->helperText(__('The number of intervals (weeks, months, etc) between each billing cycle.')),
                        Forms\Components\Select::make('interval_id')
                            ->label(__('Interval'))
                            ->relationship('interval', 'name')
                            ->options(function () {
                                return Interval::all()->mapWithKeys(fn ($interval) => [$interval->id => __($interval->name)]);
                            })
                            ->helperText(__('The interval (week, month, etc) between each billing cycle.'))
                            ->required()
                            ->preload(),
                    ])->hidden(
                        fn (\Filament\Forms\Get $get): bool => $get('is_default') === true
                    ),
                    Forms\Components\Toggle::make('has_trial')
                        ->reactive()
                        ->label(__('Has Trial'))
                        ->requiredWith('trial_interval_id')
                        ->afterStateUpdated(
                            fn ($state, callable $set) => $state ? $set('trial_interval_id', null) : $set('trial_interval_id', 'hidden')
                        )
                        ->hidden(
                            fn (\Filament\Forms\Get $get): bool => $get('is_default') === true
                        ),
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('trial_interval_count')
                            ->required()
                            ->integer()
                            ->label(__('Trial Interval Count'))
                            ->minValue(1)
                            ->required(
                                fn (\Filament\Forms\Get $get): bool => $get('has_trial') === true
                            )
                            ->hidden(
                                fn (\Filament\Forms\Get $get): bool => $get('has_trial') === false
                            ),
                        Forms\Components\Select::make('trial_interval_id')
                            ->relationship('trialInterval', 'name')
                            ->label(__('Trial Interval'))
                            ->options(function () {
                                return Interval::all()->mapWithKeys(fn ($interval) => [$interval->id => __($interval->name)]);
                            })
                            ->requiredWith('has_trial')
                            ->preload()
                            ->required(
                                fn (\Filament\Forms\Get $get): bool => $get('has_trial') === true
                            )
                            ->hidden(
                                fn (\Filament\Forms\Get $get): bool => $get('has_trial') === false
                            ),
                    ])->hidden(
                        fn (\Filament\Forms\Get $get): bool => $get('is_default') === true
                    ),
                    Forms\Components\Toggle::make('is_active')
                        ->label(__('Is Active'))
                        ->default(true)
                        ->required(),
                    Forms\Components\RichEditor::make('description'),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->heading(__('Plans are the different tiers of your product that you offer to your customers.'))
            ->description(__('For example: if you have Starter, Pro and Premium products, you would create a monthly and yearly plans for each of those to offer them in different intervals.'))
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable()->label(__('Name')),
                Tables\Columns\TextColumn::make('slug')->searchable()->sortable()->label(__('Slug')),
                Tables\Columns\TextColumn::make('product.name')
                    ->label(__('Product')),
                Tables\Columns\TextColumn::make('interval')->formatStateUsing(function (string $state, $record) {
                    return $record->interval_count.' '.$record->interval->name;
                })->label(__('Interval')),
                Tables\Columns\TextColumn::make('type')->formatStateUsing(function (string $state, $record, PlanTypeMapper $mapper) {
                    return $mapper->mapForDisplay($state);
                })->label(__('Type')),
                Tables\Columns\TextColumn::make('has_trial')->formatStateUsing(function (string $state, $record) {
                    if ($record->has_trial) {
                        return $record->trial_interval_count.' '.$record->trialInterval->name;
                    }

                    return '-';
                })->label(__('Trial')),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('Active'))
                    ->boolean(),
                Tables\Columns\IconColumn::make('prices_exists')
                    ->exists('prices')
                    ->label(__('Has Prices'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('Updated At'))
                    ->dateTime(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'interval',
                'trialInterval',
            ]))
            ->bulkActions([
            ]);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Admin\Resources\PlanResource\RelationManagers\PricesRelationManager::class,
            \App\Filament\Admin\Resources\PlanResource\RelationManagers\PaymentProviderDataRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Admin\Resources\PlanResource\Pages\ListPlans::route('/'),
            'create' => \App\Filament\Admin\Resources\PlanResource\Pages\CreatePlan::route('/create'),
            'edit' => \App\Filament\Admin\Resources\PlanResource\Pages\EditPlan::route('/{record}/edit'),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return __('Plans');
    }
}
