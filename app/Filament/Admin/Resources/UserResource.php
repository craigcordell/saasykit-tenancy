<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\UserResource\RelationManagers\OrdersRelationManager;
use App\Filament\Admin\Resources\UserResource\RelationManagers\SubscriptionsRelationManager;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use STS\FilamentImpersonate\Tables\Actions\Impersonate;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return __('User Management');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()->schema([
                    Forms\Components\TextInput::make('name')
                        ->label(__('Name'))
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('public_name')
                        ->required()
                        ->label(__('Public Name'))
                        ->nullable()
                        ->helperText('This is the name that will be displayed publicly (for example in blog posts).')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('email')
                        ->email()
                        ->label(__('Email'))
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('password')
                        ->password()
                        ->label(__('Password'))
                        ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                        ->dehydrated(fn ($state) => filled($state))
                        ->required(fn (string $context): bool => $context === 'create')
                        ->helperText(fn (string $context): string => ($context !== 'create') ? __('Leave blank to keep the current password.') : '')
                        ->maxLength(255),
                    Forms\Components\RichEditor::make('notes')
                        ->nullable()
                        ->label(__('Notes'))
                        ->helperText('Any notes you want to keep about this user.'),
                    Forms\Components\Select::make('roles')
                        ->multiple()
                        ->label(__('Roles'))
                        ->relationship('roles', 'name', modifyQueryUsing: function ($query) {
                            return $query->where('is_tenant_role', false);
                        })
                        ->preload(),
                    Forms\Components\Checkbox::make('is_admin')
                        ->label('Is Admin?')
                        ->helperText('If checked, this user will be able to access the admin panel. There has to be at least 1 admin user, so if this field is disabled, you will have to create another admin user first before you can disable this one.')
                        // there has to be at least 1 admin user
                        ->disabled(fn (User $user): bool => $user->is_admin && User::where('is_admin', true)->count() === 1)
                        ->default(false),
                    Forms\Components\Checkbox::make('is_blocked')
                        ->label('Is Blocked?')
                        ->disabled(fn (User $user, string $context): bool => $user->is_admin == true || $context === 'create')
                        ->helperText('If checked, this user will not be able to log in or use any services provided.')
                        ->default(false),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->label(__('Email'))
                    ->searchable()->sortable(),
                Tables\Columns\IconColumn::make('email_verified_at')
                    ->label(__('Email Verified'))
                    ->getStateUsing(fn (User $user) => $user->email_verified_at ? true : false)
                    ->boolean(),
                Tables\Columns\TextColumn::make('last_seen_at')
                    ->label(__('Last Seen'))
                    ->sortable()
                    ->dateTime(config('app.datetime_format')),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('Updated At'))
                    ->dateTime(config('app.datetime_format')),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->sortable()
                    ->dateTime(config('app.datetime_format')),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Impersonate::make()->redirectTo(route('home')),
                Tables\Actions\Action::make('resend_verification_email')
                    ->iconButton()
                    ->label(__('Resend Verification Email'))
                    ->icon('heroicon-s-envelope-open')
                    ->requiresConfirmation()
                    ->action(function (User $record) {
                        $record->sendEmailVerificationNotification();

                        Notification::make()
                            ->success()
                            ->body(__('A verification link has been queued to be sent to this user.'))
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            OrdersRelationManager::class,
            SubscriptionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Admin\Resources\UserResource\Pages\ListUsers::route('/'),
            'create' => \App\Filament\Admin\Resources\UserResource\Pages\CreateUser::route('/create'),
            'edit' => \App\Filament\Admin\Resources\UserResource\Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getNavigationLabel(): string
    {
        return __('Users');
    }
}
