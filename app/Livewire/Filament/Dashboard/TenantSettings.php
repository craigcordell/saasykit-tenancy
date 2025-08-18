<?php

namespace App\Livewire\Filament\Dashboard;

use App\Services\TenantService;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Livewire\Component;

class TenantSettings extends Component implements HasForms
{
    use InteractsWithForms;

    private TenantService $tenantService;

    public ?array $data = [];

    public function render()
    {
        return view('livewire.filament.dashboard.tenant-settings');
    }

    public function boot(TenantService $tenantService): void
    {
        $this->tenantService = $tenantService;
    }

    public function mount(): void
    {
        $tenant = Filament::getTenant();
        $this->form->fill([
            'tenant_name' => $tenant->name,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('tenant_name')
                    ->label(__('Workspace Name'))
                    ->helperText(__('Edit the name of your workspace'))
                    ->required(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $this->tenantService->updateTenantName(Filament::getTenant(), $data['tenant_name']);

        Notification::make()
            ->title(__('Settings Saved'))
            ->success()
            ->send();
    }
}
