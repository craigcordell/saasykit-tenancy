<?php

namespace App\Filament\Dashboard\Resources\Invitations;

use App\Constants\InvitationStatus;
use App\Constants\TenancyPermissionConstants;
use App\Filament\Dashboard\Resources\Invitations\Pages\CreateInvitation;
use App\Filament\Dashboard\Resources\Invitations\Pages\EditInvitation;
use App\Filament\Dashboard\Resources\Invitations\Pages\ListInvitations;
use App\Mapper\InvitationStatusMapper;
use App\Models\Invitation;
use App\Services\TenantPermissionService;
use App\Services\TenantService;
use Closure;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
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

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('email')
                    ->label(__('Email'))
                    ->email()
                    ->required()
                    ->helperText(__('Enter the email address of the person you want to invite.'))
                    ->rules([
                        fn (): Closure => function (string $attribute, $value, Closure $fail) {
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
                Select::make('role')
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
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('user_id')
                    ->label(__('Inviter'))
                    ->formatStateUsing(function ($state, $record) {
                        return $record->user->name;
                    })
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(function ($state, InvitationStatusMapper $invitationStatusMapper) {
                        return $invitationStatusMapper->mapForDisplay($state);
                    }),
                TextColumn::make('role')
                    ->label(__('Role'))
                    ->formatStateUsing(function ($state) {
                        return Str::of($state)->title();
                    })
                    ->sortable(),
                TextColumn::make('expires_at')
                    ->label(__('Expires At'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
            'index' => ListInvitations::route('/'),
            'create' => CreateInvitation::route('/create'),
            'edit' => EditInvitation::route('/{record}/edit'),
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
