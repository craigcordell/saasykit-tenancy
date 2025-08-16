<?php

namespace App\Filament\Dashboard\Resources\Subscriptions\ActionHandlers;

use App\Filament\Dashboard\Resources\Subscriptions\SubscriptionResource;
use App\Models\Subscription;
use App\Services\PaymentProviders\PaymentService;
use App\Services\SubscriptionService;
use Filament\Notifications\Notification;

class DiscardSubscriptionCancellationActionHandler
{
    public function __construct(
        private SubscriptionService $subscriptionService,
        private PaymentService $paymentService
    ) {}

    public function handle(Subscription $record)
    {
        $user = auth()->user();

        $userSubscription = $this->subscriptionService->findActiveByUserAndSubscriptionUuid($user->id, $record->uuid);

        if (! $userSubscription) {
            Notification::make()
                ->title(__('Error canceling subscription'))
                ->danger()
                ->send();

            return redirect()->to(SubscriptionResource::getUrl());
        }

        $paymentProvider = $userSubscription->paymentProvider()->first();

        $paymentProviderStrategy = $this->paymentService->getPaymentProviderBySlug(
            $paymentProvider->slug
        );

        $this->subscriptionService->discardSubscriptionCancellation($userSubscription, $paymentProviderStrategy);

        Notification::make()
            ->title(__('Subscription cancellation discarded'))
            ->success()
            ->send();

        return redirect()->to(SubscriptionResource::getUrl());
    }
}
