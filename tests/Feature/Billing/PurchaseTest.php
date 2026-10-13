<?php

use App\Domains\Billing\Enums\PaymentChannel;
use App\Domains\Billing\Enums\PaymentPurpose;
use App\Domains\Billing\Enums\PaymentStatus;
use App\Domains\Billing\Models\Checkout;
use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\PaymentMethod;
use App\Domains\Client\Enums\ClientPermission;
use App\Domains\Client\Enums\ClientPlan;
use App\Domains\Client\Models\Client;
use App\Domains\Client\Models\ClientUser;
use App\Domains\Event\Models\Event;
use App\Domains\Template\Models\Template;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\PayHere;

const BILLING_DETAILS = ['phone' => '0771234567', 'address' => '12 Galle Road', 'city' => 'Colombo', 'country' => 'Sri Lanka'];

beforeEach(function () {
    PayHere::configure();
});

function billingClient(string $plan = 'starter'): Client
{
    $client = Client::factory()->{$plan}()->create(['billing_details' => BILLING_DETAILS]);
    test()->actingAs($client->owner, 'client');

    return $client;
}

it('starts a Business subscription on PayHere with the server\'s price', function () {
    $client = billingClient();

    $this->post('/app/billing/subscription', ['interval' => 'yearly', 'amount' => 1])
        ->assertRedirect('/app/billing/checkouts/'.Checkout::sole()->id.'/pay');

    $payment = Checkout::sole();
    expect($payment)
        ->purpose->toBe(PaymentPurpose::Subscription)
        ->channel->toBe(PaymentChannel::Preapproval) // the card is saved for renewals
        ->amount->toBe(14990000)
        ->initiated_by->toBe($client->owner->id)
        ->and(Payment::count())->toBe(0); // nothing is a payment until PayHere processes it

    $this->get("/app/billing/checkouts/{$payment->id}/pay")
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/billing/redirect')
            ->where('gateway.url', 'https://sandbox.payhere.lk/pay/preapprove')
            ->where('gateway.fields.merchant_id', PayHere::MERCHANT_ID)
            ->where('gateway.fields.order_id', $payment->order_id)
            ->where('gateway.fields.amount', '149900.00')
            ->where('gateway.fields.currency', 'LKR')
            ->where('gateway.fields.phone', '0771234567')
            ->where('gateway.fields.notify_url', route('webhooks.payhere.notify'))
            ->where('gateway.fields.return_url', route('client.billing.orders.show', $payment->order_id))
            ->where('gateway.fields.hash', strtoupper(md5(PayHere::MERCHANT_ID.$payment->order_id.'149900.00LKR'.strtoupper(md5(PayHere::SECRET)))))
            ->missing('gateway.fields.merchant_secret'));
});

it('asks for billing details before sending the payer to PayHere', function () {
    $client = Client::factory()->starter()->create();
    $this->actingAs($client->owner, 'client');

    $this->post('/app/billing/event-credits', ['quantity' => 1])
        ->assertSessionHas('error', 'Add your billing details (phone, address and city) before paying.');
    expect(Checkout::count())->toBe(0);

    $this->put('/app/billing/details', BILLING_DETAILS)->assertSessionHas('success');
    $this->post('/app/billing/event-credits', ['quantity' => 1])->assertRedirect();

    expect(Checkout::sole()->channel)->toBe(PaymentChannel::Checkout);
});

it('charges a saved card right away', function () {
    $client = billingClient();
    $card = PaymentMethod::factory()->for($client)->create();
    PayHere::fakeApi(['/payment/charge' => PayHere::charge()]);

    $this->post('/app/billing/event-credits', ['quantity' => 3, 'payment_method_id' => $card->id])
        ->assertSessionHas('success', 'Payment received. Thank you!');

    expect(Payment::sole())->status->toBe(PaymentStatus::Succeeded)->amount->toBe(3 * 499000)
        ->and($client->fresh())->plan->toBe(ClientPlan::Celebration)->event_credits->toBe(3);
    Http::assertSent(fn ($request) => $request->url() === PayHere::API.'/payment/charge' && $request['amount'] === 14970.0);
});

it('records a declined saved card without applying anything', function () {
    $client = billingClient('celebration');
    $card = PaymentMethod::factory()->for($client)->create();
    PayHere::fakeApi(['/payment/charge' => PayHere::charge(-2, 'Insufficient funds')]);

    $this->post('/app/billing/event-credits', ['quantity' => 1, 'payment_method_id' => $card->id])
        ->assertSessionHas('error', 'The payment didn\'t go through: Insufficient funds');

    expect(Payment::sole()->status)->toBe(PaymentStatus::Failed)
        ->and(Checkout::count())->toBe(0)
        ->and($client->fresh()->event_credits)->toBe(1);
});

it('refuses another account\'s card and an expired one', function () {
    billingClient();
    $foreign = PaymentMethod::factory()->create();

    $this->post('/app/billing/event-credits', ['quantity' => 1, 'payment_method_id' => $foreign->id])
        ->assertSessionHasErrors(['payment_method_id' => 'Choose one of your saved cards.']);

    $expired = PaymentMethod::factory()->for(auth('client')->user()->client)->expired()->create();
    $this->post('/app/billing/event-credits', ['quantity' => 1, 'payment_method_id' => $expired->id])
        ->assertSessionHas('error', 'This card can\'t be charged any more. Choose another card or add a new one.');

    expect(Payment::count())->toBe(0)->and(Checkout::count())->toBe(0);
});

it('keeps purchases to what the plan allows', function (string $plan, string $url, array $data, string $error) {
    $client = billingClient($plan);
    $url = str_replace('{event}', Event::factory()->for($client)->create()->id, $url);

    $this->post($url, $data)->assertSessionHas('error', $error);

    expect(Checkout::count())->toBe(0);
})->with([
    'credits on Business' => ['business', '/app/billing/event-credits', ['quantity' => 1], 'Your Business plan already includes unlimited events.'],
    'extra guests on Starter' => ['starter', '/app/billing/events/{event}/extra-guests', ['blocks' => 1], 'Extra guests can\'t be added on the Starter plan. Upgrade to Celebration or Business.'],
    'Business without an end date' => ['business', '/app/billing/subscription', ['interval' => 'monthly'], 'Your Business plan has no end date, so there\'s nothing to pay.'],
    'Enterprise' => ['enterprise', '/app/billing/subscription', ['interval' => 'monthly'], 'Enterprise accounts are billed by our team. Get in touch to change your plan.'],
]);

it('adds bought guests to the ones the event already has', function () {
    $client = billingClient('celebration');
    $event = Event::factory()->for($client)->create(['extra_guests' => 100]);
    $card = PaymentMethod::factory()->for($client)->create();
    PayHere::fakeApi(['/payment/charge' => PayHere::charge()]);

    $this->post("/app/billing/events/{$event->id}/extra-guests", ['blocks' => 2, 'payment_method_id' => $card->id])
        ->assertSessionHas('success');

    expect($event->fresh())->extra_guests->toBe(300)->guestLimit()->toBe(800)
        ->and(Payment::sole()->amount)->toBe(200000);
});

it('lets only people who manage billing pay', function () {
    $client = Client::factory()->starter()->create(['billing_details' => BILLING_DETAILS]);
    $member = ClientUser::factory()->for($client)->withPermissions([ClientPermission::EventsCreate])->create();
    $this->actingAs($member, 'client');

    $this->post('/app/billing/event-credits', ['quantity' => 1])->assertForbidden();
    $this->post('/app/billing/cards')->assertForbidden();
    $this->get('/app/membership')->assertInertia(fn (Assert $page) => $page->where('billing', null));

    $member->update(['permissions' => [ClientPermission::BillingManage->value]]);
    $this->post('/app/billing/event-credits', ['quantity' => 1])->assertRedirect();
});

it('hides other accounts\' payments and checkouts', function () {
    billingClient();
    $payment = Payment::factory()->create();
    $checkout = Checkout::factory()->create();

    $this->get("/app/billing/payments/{$payment->id}")->assertForbidden();
    $this->get("/app/billing/checkouts/{$checkout->id}/pay")->assertForbidden();
    $this->get("/app/billing/orders/{$payment->order_id}")
        ->assertInertia(fn (Assert $page) => $page->component('client/billing/checkout')->where('checkout', null));
});

it('never changes anything when the payer comes back from PayHere', function () {
    $client = billingClient();
    $checkout = Checkout::factory()->for($client)->create();

    $this->get("/app/billing/orders/{$checkout->order_id}?canceled=1")
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/billing/checkout')
            ->where('checkout.data.order_id', $checkout->order_id)
            ->where('canceled', true));

    expect($checkout->fresh())->not->toBeNull()->and(Payment::count())->toBe(0);

    $payment = Payment::factory()->for($client)->create();
    $this->get("/app/billing/orders/{$payment->order_id}")->assertRedirect("/app/billing/payments/{$payment->id}");
});

it('sells a paid design once, then it can be used for events', function () {
    $client = billingClient();
    $template = Template::factory()->published()->create(['price' => 69000, 'display_price' => 'Rs. 690', 'name' => 'Golden Ring']);

    $this->post('/app/events', ['title' => 'Wedding', 'template_id' => $template->id])
        ->assertSessionHas('error', 'Golden Ring is a paid design (Rs. 690). Buy it under Invitation designs to use it.');

    $card = PaymentMethod::factory()->for($client)->create();
    PayHere::fakeApi(['/payment/charge' => PayHere::charge()]);
    $this->post("/app/billing/templates/{$template->id}", ['payment_method_id' => $card->id])->assertSessionHas('success');

    expect(Payment::sole()->amount)->toBe(69000);
    $this->post('/app/events', ['title' => 'Wedding', 'template_id' => $template->id])->assertSessionHas('success');
    $this->post("/app/billing/templates/{$template->id}", ['payment_method_id' => $card->id])
        ->assertSessionHas('error', 'You can already use Golden Ring.');
});

it('keeps paid designs usable for accounts whose events already use them', function () {
    $client = billingClient('business');
    $template = Template::factory()->published()->create(['price' => 69000]);
    Event::factory()->for($client)->create(['template_version_id' => $template->latest_version_id]);

    $this->post('/app/events', ['title' => 'Second', 'template_id' => $template->id])->assertSessionHas('success');
    $this->get('/app/templates')->assertInertia(fn (Assert $page) => $page->where('ownedTemplateIds', [$template->id]));
});
