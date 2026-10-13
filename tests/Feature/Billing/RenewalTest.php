<?php

use App\Domains\Billing\Enums\BillingInterval;
use App\Domains\Billing\Enums\PaymentPurpose;
use App\Domains\Billing\Enums\PaymentStatus;
use App\Domains\Billing\Enums\SubscriptionStatus;
use App\Domains\Billing\Models\Checkout;
use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\PaymentMethod;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Billing\Notifications\PaymentReceipt;
use App\Domains\Billing\Notifications\RenewalFailed;
use App\Domains\Billing\Notifications\RenewalUpcoming;
use App\Domains\Billing\Notifications\SubscriptionEnded;
use App\Domains\Client\Models\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\Support\PayHere;

beforeEach(function () {
    PayHere::configure();
    $this->freezeSecond();
});

/** A Business client whose monthly period ends in five hours. */
function dueSubscription(array $attributes = []): Subscription
{
    $client = Client::factory()->business(now()->addHours(5))->create();
    $card = PaymentMethod::factory()->for($client)->create();

    return Subscription::factory()->for($client)->dueForRenewal()->create([
        'payment_method_id' => $card->id,
        'current_period_end' => $client->plan_expires_at,
        ...$attributes,
    ]);
}

it('renews a subscription that is due, once', function () {
    Notification::fake();
    $subscription = dueSubscription(['next_interval' => BillingInterval::Yearly]);
    $end = $subscription->current_period_end;
    PayHere::fakeApi(['/payment/charge' => PayHere::charge()]);

    $this->artisan('billing:renew-subscriptions')->assertSuccessful();
    $this->artisan('billing:renew-subscriptions')->assertSuccessful();

    $payment = Payment::sole();
    expect($payment)
        ->purpose->toBe(PaymentPurpose::Renewal)
        ->status->toBe(PaymentStatus::Succeeded)
        ->amount->toBe(14990000) // the yearly price: the switch applies now
        ->initiated_by->toBeNull()
        ->and($subscription->fresh())
        ->interval->toBe(BillingInterval::Yearly)
        ->next_interval->toBeNull()
        ->current_period_start->toEqual($end)
        ->current_period_end->toEqual($end->addYearNoOverflow())
        ->and($subscription->client->fresh()->plan_expires_at)->toEqual($end->addYearNoOverflow());

    Http::assertSentCount(2); // OAuth + one charge
    Notification::assertSentTo($subscription->client->owner, PaymentReceipt::class);
});

it('ends the subscription with the period when the renewal is declined', function () {
    Notification::fake();
    $subscription = dueSubscription();
    $end = $subscription->current_period_end;
    PayHere::fakeApi(['/payment/charge' => PayHere::charge(-2, 'Card expired')]);

    $this->artisan('billing:renew-subscriptions')->assertSuccessful();
    $this->artisan('billing:renew-subscriptions')->assertSuccessful();

    expect(Payment::sole())->status->toBe(PaymentStatus::Failed)->status_message->toBe('Card expired')
        ->and($subscription->fresh())->status->toBe(SubscriptionStatus::Expired)->auto_renew->toBeFalse()->ended_reason->toBe('renewal_failed')
        ->and($subscription->client->fresh()->plan_expires_at)->toEqual($end);

    Http::assertSentCount(2); // no retry
    Notification::assertSentTo($subscription->client->owner, RenewalFailed::class);

    $this->travelTo($end->addMinute());
    expect($subscription->client->fresh()->planActive())->toBeFalse();
});

it('fails the renewal when there is no card to charge', function () {
    $subscription = dueSubscription();
    $subscription->paymentMethod->update(['expiry_year' => 2020]);

    Notification::fake();

    $this->artisan('billing:renew-subscriptions')->assertSuccessful();

    // PayHere never saw a charge: nothing is recorded as a payment.
    expect(Payment::count())->toBe(0)
        ->and(Checkout::count())->toBe(0)
        ->and($subscription->fresh())->status->toBe(SubscriptionStatus::Expired)->ended_reason->toBe('renewal_failed');
    Notification::assertSentTo($subscription->client->owner, RenewalFailed::class);
});

it('tries again when PayHere could not be reached', function () {
    $subscription = dueSubscription();
    PayHere::fakeApi([
        '/payment/charge' => Http::sequence()->pushStatus(503)->push(PayHere::chargeBody()),
        '/payment/search*' => PayHere::retrieval(null),
    ]);

    $this->artisan('billing:renew-subscriptions')->assertSuccessful();
    expect(Payment::count())->toBe(0)->and(Checkout::count())->toBe(1);

    $this->artisan('billing:renew-subscriptions')->assertSuccessful();
    expect(Payment::sole()->status)->toBe(PaymentStatus::Succeeded)
        ->and($subscription->fresh()->current_period_start)->toEqual($subscription->current_period_end);
});

it('leaves canceled subscriptions alone until they end', function () {
    Notification::fake();
    $subscription = dueSubscription(['status' => SubscriptionStatus::Canceled, 'auto_renew' => false]);

    $this->artisan('billing:renew-subscriptions')->assertSuccessful();
    expect(Payment::count())->toBe(0)
        ->and($subscription->fresh()->status)->toBe(SubscriptionStatus::Canceled);

    $this->travelTo($subscription->current_period_end->addMinute());
    $this->artisan('billing:renew-subscriptions')->assertSuccessful();

    expect($subscription->fresh())->status->toBe(SubscriptionStatus::Expired)->ended_reason->toBe('canceled');
    Notification::assertSentTo($subscription->client->owner, SubscriptionEnded::class);
});

it('reminds yearly subscribers a few days before the renewal, once', function () {
    Notification::fake();
    $subscription = dueSubscription(['interval' => BillingInterval::Yearly, 'current_period_end' => now()->addDays(2)]);

    $this->artisan('billing:renew-subscriptions')->assertSuccessful();
    $this->artisan('billing:renew-subscriptions')->assertSuccessful();

    Notification::assertSentToTimes($subscription->client->owner, RenewalUpcoming::class, 1);
    expect(Payment::count())->toBe(0);
});

it('forgets checkouts nobody finished after a day', function () {
    $abandoned = Checkout::factory()->create(['created_at' => now()->subDays(2)]);
    $recent = Checkout::factory()->create();

    $this->artisan('billing:renew-subscriptions')->assertSuccessful();

    expect($abandoned->fresh())->toBeNull()
        ->and($recent->fresh())->not->toBeNull()
        ->and(Payment::count())->toBe(0);
});
