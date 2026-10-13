<?php

use App\Domains\Billing\Enums\PaymentPurpose;
use App\Domains\Billing\Enums\SubscriptionStatus;
use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\PaymentMethod;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Client\Enums\ClientPlan;
use App\Domains\Client\Models\Client;
use App\Domains\Staff\Enums\StaffRole;
use App\Domains\Staff\Models\StaffMember;
use Tests\Support\PayHere;

beforeEach(function () {
    PayHere::configure();
    $this->freezeSecond();
});

/** A client on a running Business subscription with a saved card, signed in. */
function runningBusiness(): Subscription
{
    $client = Client::factory()->business(now()->addDays(10))->create(['event_credits' => 2]);
    $card = PaymentMethod::factory()->for($client)->create();
    $subscription = Subscription::factory()->for($client)->create([
        'payment_method_id' => $card->id,
        'current_period_end' => $client->plan_expires_at,
    ]);
    test()->actingAs($client->owner, 'client');

    return $subscription;
}

it('does not renew an active plan early', function () {
    $client = Client::factory()->business(now()->addDays(10))->create(['billing_details' => ['phone' => '0771234567', 'address' => 'x', 'city' => 'Colombo']]);
    $this->actingAs($client->owner, 'client');

    $this->post('/app/billing/subscription', ['interval' => 'monthly'])
        ->assertSessionHas('error', "Your Business plan is active until {$client->plan_expires_at->format('j M Y')}.");

    expect(Payment::count())->toBe(0);
});

it('downgrades from Business when it ends, keeping it until then', function () {
    $subscription = runningBusiness();
    $client = $subscription->client;

    $this->post('/app/billing/plan-change', ['plan' => 'celebration'])
        ->assertSessionHas('success', "You'll move to Celebration when Business ends on {$client->plan_expires_at->format('j M Y')}.");

    expect($client->fresh())->plan->toBe(ClientPlan::Business)->scheduled_plan->toBe(ClientPlan::Celebration)->planActive()->toBeTrue()
        ->and($subscription->fresh())->status->toBe(SubscriptionStatus::Canceled)->auto_renew->toBeFalse();

    $this->artisan('billing:renew-subscriptions')->assertSuccessful();
    expect($client->fresh()->plan)->toBe(ClientPlan::Business)
        ->and(Payment::count())->toBe(0);

    $this->travelTo($client->plan_expires_at->addMinute());
    $this->artisan('billing:renew-subscriptions')->assertSuccessful();

    expect($client->fresh())
        ->plan->toBe(ClientPlan::Celebration)
        ->plan_expires_at->toBeNull()
        ->event_credits->toBe(2)
        ->scheduled_plan->toBeNull()
        ->and($subscription->fresh()->status)->toBe(SubscriptionStatus::Expired);
});

it('keeps Business after all and turns auto-renew back on', function (string $how) {
    $subscription = runningBusiness();
    $this->post('/app/billing/plan-change', ['plan' => 'starter']);

    match ($how) {
        'keep' => $this->delete('/app/billing/plan-change')->assertSessionHas('success', 'You\'re keeping Business.'),
        'resume' => $this->patch('/app/billing/subscription', ['action' => 'resume'])->assertSessionHas('success'),
    };

    expect($subscription->client->fresh()->scheduled_plan)->toBeNull()
        ->and($subscription->fresh())->status->toBe(SubscriptionStatus::Active)->auto_renew->toBeTrue();
})->with(['keep', 'resume']);

it('only downgrades a running Business plan, to a cheaper plan', function (string $plan, string $target, array|string $error) {
    $client = Client::factory()->{$plan}()->create();
    $this->actingAs($client->owner, 'client');

    $response = $this->post('/app/billing/plan-change', ['plan' => $target]);

    is_array($error) ? $response->assertSessionHasErrors($error) : $response->assertSessionHas('error', $error);
    expect($client->fresh()->scheduled_plan)->toBeNull();
})->with([
    'from Celebration' => ['celebration', 'starter', 'Only a running Business plan can be downgraded.'],
    'Business without an end date' => ['business', 'starter', 'Only a running Business plan can be downgraded.'],
    'to Enterprise' => ['business', 'enterprise', ['plan']],
]);

it('renews an ended Enterprise plan at the price staff set', function () {
    $client = Client::factory()->enterprise(now()->subDay())->create(['renewal_price' => 50000000, 'renewal_months' => 12, 'user_limit' => 20]);
    $card = PaymentMethod::factory()->for($client)->create();
    $this->actingAs($client->owner, 'client');
    PayHere::fakeApi(['/payment/charge' => PayHere::charge()]);

    $this->post('/app/billing/enterprise-renewal', ['payment_method_id' => $card->id])->assertSessionHas('success');

    expect(Payment::sole())->purpose->toBe(PaymentPurpose::EnterpriseRenewal)->amount->toBe(50000000)
        ->and($client->fresh())
        ->plan->toBe(ClientPlan::Enterprise)
        ->plan_expires_at->toDateTimeString()->toBe(now()->addMonthsNoOverflow(12)->endOfDay()->toDateTimeString())
        ->user_limit->toBe(20)
        ->renewal_price->toBe(50000000);
});

it('does not renew Enterprise while it runs, or without agreed terms', function (array $attributes, string $error) {
    $client = Client::factory()->enterprise()->create($attributes);
    $card = PaymentMethod::factory()->for($client)->create();
    $this->actingAs($client->owner, 'client');

    $this->post('/app/billing/enterprise-renewal', ['payment_method_id' => $card->id])->assertSessionHas('error', $error);

    expect(Payment::count())->toBe(0);
})->with([
    'still active' => [['plan_expires_at' => '2030-06-30 23:59:59', 'renewal_price' => 100000, 'renewal_months' => 12], 'Your Enterprise plan is active until 30 Jun 2030.'],
    'no terms' => [['plan_expires_at' => now()->subDay()], 'Your Enterprise renewal is arranged with our team. Get in touch to renew.'],
]);

it('keeps Enterprise from switching plans online', function (string $url, array $data, string $error) {
    $client = Client::factory()->enterprise(now()->subDay())->create(['billing_details' => ['phone' => '0771234567', 'address' => 'x', 'city' => 'Colombo']]);
    $this->actingAs($client->owner, 'client');

    $this->post($url, $data)->assertSessionHas('error', $error);
})->with([
    'subscribe' => ['/app/billing/subscription', ['interval' => 'monthly'], 'Enterprise accounts are billed by our team. Get in touch to change your plan.'],
    'event credits' => ['/app/billing/event-credits', ['quantity' => 1], 'Your Enterprise plan already includes unlimited events.'],
    'downgrade' => ['/app/billing/plan-change', ['plan' => 'starter'], 'Only a running Business plan can be downgraded.'],
]);

it('lets staff set the Enterprise renewal terms', function () {
    $client = Client::factory()->enterprise()->create();
    $this->actingAs(StaffMember::factory()->role(StaffRole::Admin)->create(), 'staff');

    $this->patch("/admin/clients/{$client->id}/plan", [
        'plan' => 'enterprise', 'plan_expires_at' => '2030-01-01', 'event_credits' => 0, 'user_limit' => 25,
        'renewal_price' => '450000', 'renewal_months' => 12, 'note' => 'Signed yearly contract',
    ])->assertSessionHas('success');

    expect($client->fresh())->renewal_price->toBe(45000000)->renewal_months->toBe(12);

    $this->patch("/admin/clients/{$client->id}/plan", [
        'plan' => 'enterprise', 'event_credits' => 0, 'user_limit' => 25, 'renewal_price' => '450000', 'note' => 'x',
    ])->assertSessionHasErrors(['renewal_months' => 'Set how many months a renewal covers.']);
});
