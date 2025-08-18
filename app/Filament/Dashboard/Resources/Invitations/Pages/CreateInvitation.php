<?php

namespace App\Filament\Dashboard\Resources\Invitations\Pages;

use App\Filament\Dashboard\Resources\Invitations\InvitationResource;
use App\Services\TenantService;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateInvitation extends CreateRecord
{
    protected static string $resource = InvitationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['token'] = Str::random(60);
        $data['tenant_id'] = Filament::getTenant()->id;
        $data['uuid'] = Str::uuid();
        $data['expires_at'] = now()->addDays(7);
        $data['user_id'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var TenantService $tenantService */
        $tenantService = app(TenantService::class);
        $tenantService->handleAfterInvitationCreated($this->getRecord());
    }
}
