<?php

use App\Domains\Billing\Enums\PaymentStatus;
use App\Domains\Billing\Gateways\MockPaymentGateway;
use App\Domains\Billing\Models\Checkout;
use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\PaymentMethod;
use App\Domains\Client\Enums\ClientPlan;
use App\Domains\Client\Models\Client;

beforeEach(function () {
    config(['services.payhere.merchant_id' => null, 'inertia.ssr.enabled' => false]);
    $this->client = Client::factory()->starter()->create(['billing_details' => ['phone' => '0771234567', 'address' => 'x', 'city' => 'Colombo']]);
    $this->actingAs($this->client->owner, 'client');
});

it('completes a subscription on the mock page without PayHere credentials', function () {
    $this->post('/app/billing/subscription', ['interval' => 'monthly']);
    $checkout = Checkout::sole();

    $this->get("/app/billing/checkouts/{$checkout->id}/pay")->assertRedirect("/app/billing/mock/{$checkout->id}");
    $this->post("/app/billing/mock/{$checkout->id}", ['outcome' => 'succeed'])->assertRedirect("/app/billing/orders/{$checkout->order_id}");

    expect(Payment::sole()->status)->toBe(PaymentStatus::Succeeded)
        ->and(PaymentMethod::sole()->last4)->toBe('4242')
        ->and($this->client->fresh()->plan)->toBe(ClientPlan::Business);
});

it('is not there with real credentials', function () {
    config(['services.payhere.merchant_id' => '1211149', 'services.payhere.merchant_secret' => 'secret']);
    $checkout = Checkout::factory()->for($this->client)->create();

    $this->get("/app/billing/mock/{$checkout->id}")->assertNotFound();
    $this->post("/app/billing/mock/{$checkout->id}", ['outcome' => 'succeed'])->assertNotFound();

    expect(Payment::count())->toBe(0);
});

it('does not trust mock-signed notifications on the webhook in production', function () {
    $checkout = Checkout::factory()->for($this->client)->create();
    $this->app['env'] = 'production';

    $this->post('/webhooks/payhere', MockPaymentGateway::notification($checkout, PaymentStatus::Succeeded))->assertOk();

    expect(Payment::count())->toBe(0);
});
