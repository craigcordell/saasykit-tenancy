<?php

namespace App\Filament\Dashboard\Resources;

use App\Constants\InvitationStatus;
use App\Constants\TenancyPermissionConstants;
use App\Filament\Dashboard\Resources\InvitationResource\Pages;
use App\Mapper\InvitationStatusMapper;
use App\Models\Invitation;
use App\Services\TenantPermissionService;
use App\Services\TenantService;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class InvitationResource extends Resource
{
    protected static ?string $model = Invitation::class;

    public static function getNavigationGroup(): ?string
    {
        return __('Team');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('email')
                    ->label(__('Email'))
                    ->email()
                    ->required()
                    ->helperText(__('Enter the email address of the person you want to invite.'))
                    ->rules([
                        fn (): \Closure => function (string $attribute, $value, \Closure $fail) {
                            // if there is a user with this email address in the tenant and status is pending and expires_at is greater than now, fail
                            if (Filament::getTenant()->invitations()
                                ->where('email', $value)
                                ->where('status', InvitationStatus::PENDING->value)
                                ->where('expires_at', '>', now())
                                ->exists()
                            ) {
                                $fail(__('This email address has already been invited.'));
                            }

                            if (Filament::getTenant()->users()->where('email', $value)->exists()) {
                                $fail(__('This user is already in the team.'));
                            }

                            /** @var TenantService $tenantService */
                            $tenantService = app(TenantService::class);

                            if (! $tenantService->canInviteUser(Filament::getTenant(), auth()->user())) {
                                $fail(__('You have reached the maximum number of users allowed for your subscription.'));
                            }
                        },
                    ])
                    ->maxLength(255),
                Forms\Components\Select::make('role')
                    ->options(function (TenantPermissionService $tenantPermissionService) {
                        return $tenantPermissionService->getAllAvailableTenantRolesForDisplay(Filament::getTenant());
                    })
                    ->default(TenancyPermissionConstants::ROLE_USER)
                    ->label(__('Role'))
                    ->required()
                    ->helperText(__('Choose the role for this user.')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description(__('Send invitations to your team members.'))
            ->columns([
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('user_id')
                    ->label(__('Inviter'))
                    ->formatStateUsing(function ($state, $record) {
                        return $record->user->name;
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(function ($state, InvitationStatusMapper $invitationStatusMapper) {
                        return $invitationStatusMapper->mapForDisplay($state);
                    }),
                Tables\Columns\TextColumn::make('role')
                    ->label(__('Role'))
                    ->formatStateUsing(function ($state) {
                        return Str::of($state)->title();
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label(__('Expires At'))
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        /** @var TenantPermissionService $tenantPermissionService */
        $tenantPermissionService = app(TenantPermissionService::class); // a bit ugly, but this is the Filament way :/

        return config('app.allow_tenant_invitations', false) && $tenantPermissionService->tenantUserHasPermissionTo(
            Filament::getTenant(),
            auth()->user(),
            TenancyPermissionConstants::PERMISSION_INVITE_MEMBERS,
        );
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvitations::route('/'),
            'create' => Pages\CreateInvitation::route('/create'),
            'edit' => Pages\EditInvitation::route('/{record}/edit'),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return __('Invite People');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('tenant_id', Filament::getTenant()->id)->where('expires_at', '>', now())->where('status', InvitationStatus::PENDING->value);
    }
}
