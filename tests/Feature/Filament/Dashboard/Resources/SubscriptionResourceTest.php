<?php

namespace Tests\Feature\Filament\Dashboard\Resources;

use App\Filament\Dashboard\Resources\Subscriptions\SubscriptionResource;
use App\Models\Subscription;
use App\Models\User;
use Tests\Feature\FeatureTest;

class SubscriptionResourceTest extends FeatureTest
{
    public function test_list(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $response = $this->get(SubscriptionResource::getUrl('index', [], true, 'dashboard'))->assertSuccessful();

        $response->assertStatus(200);
    }

    public function test_change_plan(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        // create subscription for this user
        $subscription = Subscription::factory()->for($user)->create([
            'user_id' => $user->id,
        ]);

        $response = $this->get(SubscriptionResource::getUrl('change-plan', [
            'record' => $subscription->uuid,
        ], true, 'dashboard'))->assertSuccessful();

        $response->assertStatus(200);
    }

    public function test_cancel()
    {
        $user = $this->createUser();
        $this->actingAs($user);

        // create subscription for this user
        $subscription = Subscription::factory()->for($user)->create([
            'user_id' => $user->id,
        ]);

        $response = $this->get(SubscriptionResource::getUrl('cancel', [
            'record' => $subscription->uuid,
        ], true, 'dashboard'))->assertSuccessful();

        $response->assertStatus(200);
    }

    public function test_confirm_cancellation()
    {
        $user = $this->createUser();
        $this->actingAs($user);

        // create subscription for this user
        $subscription = Subscription::factory()->for($user)->create([
            'user_id' => $user->id,
        ]);

        $response = $this->get(SubscriptionResource::getUrl('confirm-cancellation', [
            'record' => $subscription->uuid,
        ], true, 'dashboard'))->assertSuccessful();

        $response->assertStatus(200);
    }
}
