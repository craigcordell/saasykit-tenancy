<?php

namespace App\View\Components\Filament\Plans;

use Closure;
use Illuminate\Contracts\View\View;

class All extends \App\View\Components\Plans\All
{
    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.filament.plans.all', $this->calculateViewData());
    }

    protected function calculateViewData()
    {
        $subscription = null;
        if ($this->currentSubscriptionUuid !== null) {
            $subscription = $this->subscriptionService->findActiveByUserAndSubscriptionUuid(auth()->user()->id, $this->currentSubscriptionUuid);
        }

        $planType = null;
        if ($subscription !== null) {
            $planType = $subscription->plan->type;
        }

        $plans = $this->planService->getAllPlansWithPrices(
            $this->products,
            $planType,
        );

        $viewData['subscription'] = $subscription;

        return $this->enrichViewData($viewData, $plans);
    }
}
