<?php

namespace App\Domains\Billing\Http\Controllers\Dashboard;

use App\Core\Http\Controllers\Concerns\ResolvesClient;
use App\Core\Http\Controllers\InertiaController;
use App\Domains\Billing\Contracts\SubscriptionServiceInterface;
use App\Domains\Billing\Http\Requests\Dashboard\ScheduleDowngradeRequest;
use App\Domains\Billing\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Downgrading from Business at the end of the period, or keeping it after all. */
class PlanChangeController extends InertiaController
{
    use ResolvesClient;

    public function __construct(
        private readonly SubscriptionServiceInterface $subscriptions,
    ) {}

    public function store(ScheduleDowngradeRequest $request): RedirectResponse
    {
        $client = $this->client($request);

        $this->subscriptions->scheduleDowngrade($client, $request->plan());

        return $this->backWithSuccess("You'll move to {$request->plan()->label()} when Business ends on {$client->refresh()->plan_expires_at?->format('j M Y')}.");
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->authorize('create', Payment::class);

        $warning = $this->subscriptions->keepPlan($this->client($request));

        return $warning !== null
            ? back()->with('error', $warning)
            : $this->backWithSuccess('You\'re keeping Business.');
    }
}
