<?php

namespace Tests\Feature\Filament\Dashboard\Resources;

use App\Filament\Dashboard\Resources\Orders\OrderResource;
use Tests\Feature\FeatureTest;

class OrderResourceTest extends FeatureTest
{
    public function test_list(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $response = $this->get(OrderResource::getUrl('index', [], true, 'dashboard'))->assertSuccessful();

        $response->assertStatus(200);
    }
}
