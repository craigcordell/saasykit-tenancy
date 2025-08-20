<?php

namespace App\Livewire\Filament;

use App\Services\ConfigService;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Livewire\Component;

class TenancySettings extends Component implements HasForms
{
    private ConfigService $configService;

    use InteractsWithForms;

    public ?array $data = [];

    public function render()
    {
        return view('livewire.filament.tenancy-settings');
    }

    public function boot(ConfigService $configService): void
    {
        $this->configService = $configService;
    }

    public function mount(): void
    {
        $this->form->fill([
            'allow_tenant_invitations' => $this->configService->get('app.allow_tenant_invitations', false),
            'tenant_multiple_subscriptions_enabled' => $this->configService->get('app.tenant_multiple_subscriptions_enabled', false),
            'can_add_tenant_specific_roles_from_tenant_dashboard' => $this->configService->get('app.can_add_tenant_specific_roles_from_tenant_dashboard', false),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Tenancy Settings'))
                    ->schema([
                        Toggle::make('allow_tenant_invitations')
                            ->label(__('Allow Tenant Invitations'))
                            ->helperText(__('If enabled, tenants users with an "admin" tenant role will be able to invite users to their tenant.'))
                            ->required(),
                        Toggle::make('tenant_multiple_subscriptions_enabled')
                            ->label(__('Allow Multiple Subscriptions Per Tenant'))
                            ->helperText(__('If enabled, tenants will be able to have multiple subscriptions. If disabled, tenants can only have one active subscription at a time.'))
                            ->required(),
                        Toggle::make('can_add_tenant_specific_roles_from_tenant_dashboard')
                            ->label(__('Allow Adding Tenant Specific Roles from Tenant Dashboard'))
                            ->helperText(__('If enabled, tenant admins can add roles that are specific to their tenant. If disabled, only tenant roles defined in the admin dashboard can be assigned to tenant users.'))
                            ->required(),
                    ]),

            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $this->configService->set('app.allow_tenant_invitations', $data['allow_tenant_invitations']);
        $this->configService->set('app.tenant_multiple_subscriptions_enabled', $data['tenant_multiple_subscriptions_enabled']);
        $this->configService->set('app.can_add_tenant_specific_roles_from_tenant_dashboard', $data['can_add_tenant_specific_roles_from_tenant_dashboard']);

        Notification::make()
            ->title(__('Settings Saved'))
            ->success()
            ->send();
    }
}
