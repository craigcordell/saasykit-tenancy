<?php

namespace Tests\Feature\Filament\Dashboard\Resources;

use App\Filament\Dashboard\Resources\Transactions\TransactionResource;
use Tests\Feature\FeatureTest;

class TransactionResourceTest extends FeatureTest
{
    public function test_list(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $response = $this->get(TransactionResource::getUrl('index', [], true, 'dashboard'))->assertSuccessful();

        $response->assertStatus(200);
    }
}
