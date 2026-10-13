<?php

use App\Domains\Billing\Enums\BillingInterval;
use App\Domains\Billing\Enums\PaymentChannel;
use App\Domains\Billing\Enums\PaymentPurpose;
use App\Domains\Billing\Enums\PaymentStatus;
use App\Domains\Billing\Enums\SubscriptionStatus;
use App\Domains\Billing\Models\Checkout;
use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\PaymentMethod;
use App\Domains\Billing\Models\PaymentNotification;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Billing\Notifications\PaymentReceipt;
use App\Domains\Client\Enums\ClientPlan;
use App\Domains\Client\Models\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\Support\PayHere;

beforeEach(function () {
    PayHere::configure();
    $this->client = Client::factory()->starter()->create();
});

function notify(array $payload)
{
    return test()->post('/webhooks/payhere', $payload);
}

it('records a paid checkout once, however often PayHere notifies', function () {
    Notification::fake();
    $checkout = Checkout::factory()->for($this->client)->create(['details' => ['quantity' => 2], 'amount' => 998000]);
    PayHere::fakeApi(['/payment/search*' => PayHere::retrieval($checkout)]);

    notify(PayHere::notification($checkout))->assertOk();
    notify(PayHere::notification($checkout))->assertOk();

    expect(Payment::sole())
        ->order_id->toBe($checkout->order_id)
        ->status->toBe(PaymentStatus::Succeeded)
        ->amount->toBe(998000)
        ->payhere_payment_id->toBe('320025071278')
        ->fulfilled_at->not->toBeNull()
        ->and(Checkout::count())->toBe(0)
        ->and($this->client->fresh())
        ->plan->toBe(ClientPlan::Celebration)
        ->event_credits->toBe(2);
    Notification::assertSentToTimes($this->client->owner, PaymentReceipt::class, 1);
});

it('records nothing for notifications PayHere did not sign', function (array $tamper) {
    $checkout = Checkout::factory()->for($this->client)->create();

    notify([...PayHere::notification($checkout), ...$tamper])->assertOk();

    expect(Payment::count())->toBe(0)
        ->and($checkout->fresh())->not->toBeNull()
        ->and(PaymentNotification::sole()->signature_valid)->toBeFalse();
})->with([
    'wrong signature' => [['md5sig' => strtoupper(md5('forged'))]],
    'status changed after signing' => [['status_code' => '2', 'md5sig' => 'X']],
    'another merchant' => [['merchant_id' => '999999']],
]);

it('declines a signed notification for a different amount', function () {
    $checkout = Checkout::factory()->for($this->client)->create(['amount' => 499000]);

    notify(PayHere::notification($checkout, overrides: ['payhere_amount' => '10.00']))->assertOk();

    expect(Payment::sole()->status)->toBe(PaymentStatus::Failed)
        ->and($this->client->fresh()->event_credits)->toBe(0)
        ->and(PaymentNotification::sole()->error)->toBe('Amount or currency differs from the order.');
});

it('records nothing PayHere has no record of', function () {
    $checkout = Checkout::factory()->for($this->client)->create();
    PayHere::fakeApi(['/payment/search*' => PayHere::retrieval(null)]);

    notify(PayHere::notification($checkout))->assertOk();

    expect(Payment::count())->toBe(0)
        ->and($checkout->fresh())->not->toBeNull();
});

it('records a declined checkout, then the payment if the payer pays after all', function () {
    $checkout = Checkout::factory()->for($this->client)->create();
    PayHere::fakeApi(['/payment/search*' => PayHere::retrieval($checkout)]);

    notify(PayHere::notification($checkout, -2))->assertOk();
    expect(Payment::sole()->status)->toBe(PaymentStatus::Failed);

    notify(PayHere::notification($checkout))->assertOk();
    expect(Payment::sole()->status)->toBe(PaymentStatus::Succeeded)
        ->and($this->client->fresh()->event_credits)->toBe(1);
});

it('records nothing while PayHere is still processing, or when the payer cancels', function () {
    $checkout = Checkout::factory()->for($this->client)->create();

    notify(PayHere::notification($checkout, 0))->assertOk();
    expect(Payment::count())->toBe(0)
        ->and($checkout->fresh()->payhere_payment_id)->toBe('320025071278');

    notify(PayHere::notification($checkout, -1))->assertOk();
    expect(Payment::count())->toBe(0)
        ->and(Checkout::count())->toBe(0);
});

it('never moves a paid payment back', function () {
    $payment = Payment::factory()->for($this->client)->create();

    notify(PayHere::notification($payment, -2))->assertOk();
    notify(PayHere::notification($payment, 0))->assertOk();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Succeeded);
});

it('saves the card from a preapproval and charges the subscription to it', function () {
    $this->freezeSecond();
    $checkout = Checkout::factory()->for($this->client)->create([
        'purpose' => PaymentPurpose::Subscription,
        'channel' => PaymentChannel::Preapproval,
        'description' => 'EMP Business plan (Monthly)',
        'details' => ['interval' => BillingInterval::Monthly->value],
        'amount' => 1499000,
        'initiated_by' => $this->client->owner->id,
    ]);
    PayHere::fakeApi(['/payment/charge' => PayHere::charge()]);

    notify(PayHere::notification($checkout, overrides: ['customer_token' => 'tok-secret-123']))->assertOk();
    notify(PayHere::notification($checkout, overrides: ['customer_token' => 'tok-secret-123']))->assertOk();

    $card = PaymentMethod::sole();
    expect($card)
        ->customer_token->toBe('tok-secret-123')
        ->last4->toBe('1292')
        ->expiry_month->toBe(12)
        ->expiry_year->toBe(2029)
        ->is_default->toBeTrue()
        ->and(DB::table('payment_methods')->value('customer_token'))->not->toContain('tok-secret-123')
        ->and(json_encode(PaymentNotification::query()->pluck('payload')))->not->toContain('tok-secret-123')
        ->and(Payment::sole())->status->toBe(PaymentStatus::Succeeded)->payment_method_id->toBe($card->id)
        ->and($this->client->fresh())->plan->toBe(ClientPlan::Business)->plan_expires_at->toEqual(now()->addMonthNoOverflow())
        ->and(Subscription::sole())->status->toBe(SubscriptionStatus::Active)->auto_renew->toBeTrue();

    Http::assertSentCount(2); // OAuth + one charge, despite the repeated notification
    Http::assertSent(fn ($request) => $request->url() === PayHere::API.'/payment/charge'
        && $request['customer_token'] === 'tok-secret-123'
        && $request['order_id'] === $checkout->order_id
        && $request['amount'] === 14990.0);
});

it('keeps the card but not the plan when the first charge is declined', function () {
    $checkout = Checkout::factory()->for($this->client)->create([
        'purpose' => PaymentPurpose::Subscription,
        'channel' => PaymentChannel::Preapproval,
        'details' => ['interval' => 'monthly'],
        'amount' => 1499000,
    ]);
    PayHere::fakeApi(['/payment/charge' => PayHere::charge(-2, 'Insufficient funds')]);

    notify(PayHere::notification($checkout, overrides: ['customer_token' => 'tok-1']))->assertOk();

    expect(Payment::sole())->status->toBe(PaymentStatus::Failed)->status_message->toBe('Insufficient funds')
        ->and(PaymentMethod::count())->toBe(1)
        ->and($this->client->fresh()->plan)->toBe(ClientPlan::Starter);
});

it('saves a card without buying anything', function () {
    $checkout = Checkout::factory()->for($this->client)->create([
        'purpose' => PaymentPurpose::CardSetup,
        'channel' => PaymentChannel::Preapproval,
        'amount' => 1000,
    ]);

    notify(PayHere::notification($checkout, overrides: ['customer_token' => 'tok-1']))->assertOk();

    expect(Payment::sole()->status)->toBe(PaymentStatus::Succeeded)
        ->and(PaymentMethod::sole()->client_id)->toBe($this->client->id)
        ->and($this->client->fresh()->plan)->toBe(ClientPlan::Starter);
});
