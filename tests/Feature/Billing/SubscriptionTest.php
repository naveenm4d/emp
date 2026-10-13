<?php

use App\Domains\Billing\Enums\BillingInterval;
use App\Domains\Billing\Enums\SubscriptionStatus;
use App\Domains\Billing\Models\PaymentMethod;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Client\Models\Client;
use App\Domains\Staff\Enums\StaffRole;
use App\Domains\Staff\Models\StaffMember;
use Tests\Support\PayHere;

beforeEach(function () {
    PayHere::configure();
    $this->client = Client::factory()->business(now()->addDays(15))->create();
    $this->card = PaymentMethod::factory()->for($this->client)->create();
    $this->subscription = Subscription::factory()->for($this->client)->create([
        'payment_method_id' => $this->card->id,
        'current_period_end' => $this->client->plan_expires_at,
    ]);
    $this->actingAs($this->client->owner, 'client');
});

it('turns auto-renew off and keeps the plan until the period ends, then back on', function () {
    $this->patch('/app/billing/subscription', ['action' => 'cancel'])
        ->assertSessionHas('success', "Auto-renew is off. Business stays until {$this->subscription->current_period_end->format('j M Y')}.");

    expect($this->subscription->fresh())->status->toBe(SubscriptionStatus::Canceled)->auto_renew->toBeFalse()
        ->and($this->client->fresh()->planActive())->toBeTrue();

    $this->patch('/app/billing/subscription', ['action' => 'resume'])->assertSessionHas('success');

    expect($this->subscription->fresh())->status->toBe(SubscriptionStatus::Active)->auto_renew->toBeTrue();
});

it('needs a card that can be charged to turn auto-renew back on', function () {
    $this->subscription->update(['status' => SubscriptionStatus::Canceled, 'auto_renew' => false]);
    $this->card->update(['expiry_year' => 2020]);

    $this->patch('/app/billing/subscription', ['action' => 'resume'])
        ->assertSessionHas('error', 'Add a card first: renewals are charged to it.');

    expect($this->subscription->fresh()->auto_renew)->toBeFalse();
});

it('switches to yearly from the next renewal', function () {
    $this->patch('/app/billing/subscription', ['action' => 'interval', 'interval' => 'yearly'])->assertSessionHas('success');

    expect($this->subscription->fresh())
        ->interval->toBe(BillingInterval::Monthly)
        ->next_interval->toBe(BillingInterval::Yearly);
});

it('moves renewals to another card, or turns auto-renew off, when the card is removed', function () {
    $other = PaymentMethod::factory()->for($this->client)->create(['is_default' => false]);

    $this->delete("/app/billing/cards/{$this->card->id}")->assertSessionHas('success');

    expect($this->subscription->fresh()->payment_method_id)->toBe($other->id)
        ->and($other->fresh()->is_default)->toBeTrue()
        ->and(PaymentMethod::withTrashed()->find($this->card->id))->trashed()->toBeTrue()->customer_token->toBeNull();

    $this->delete("/app/billing/cards/{$other->id}")
        ->assertSessionHas('error', "Auto-renew is now off: there's no card to charge. Add one before {$this->subscription->current_period_end->format('j M Y')} to keep Business.");

    expect($this->subscription->fresh())->status->toBe(SubscriptionStatus::Canceled)->auto_renew->toBeFalse();
});

it('makes another card the default and renews with it', function () {
    $other = PaymentMethod::factory()->for($this->client)->create(['is_default' => false]);

    $this->patch("/app/billing/cards/{$other->id}")->assertSessionHas('success');

    expect($other->fresh()->is_default)->toBeTrue()
        ->and($this->card->fresh()->is_default)->toBeFalse()
        ->and($this->subscription->fresh()->payment_method_id)->toBe($other->id);
});

it('does not let anyone touch another account\'s cards', function () {
    $foreign = PaymentMethod::factory()->create();

    $this->patch("/app/billing/cards/{$foreign->id}")->assertForbidden();
    $this->delete("/app/billing/cards/{$foreign->id}")->assertForbidden();

    expect($foreign->fresh()->customer_token)->not->toBeNull();
});

it('follows staff plan changes so nobody is charged for a plan they left', function () {
    $staff = StaffMember::factory()->role(StaffRole::Admin)->create();
    $this->actingAs($staff, 'staff');

    $this->patch("/admin/clients/{$this->client->id}/plan", [
        'plan' => 'business', 'plan_expires_at' => '2030-01-01', 'event_credits' => 0, 'note' => 'Goodwill extension',
    ])->assertSessionHas('success');

    expect($this->subscription->fresh())
        ->status->toBe(SubscriptionStatus::Active)
        ->current_period_end->toDateString()->toBe('2030-01-01');

    $this->patch("/admin/clients/{$this->client->id}/plan", [
        'plan' => 'celebration', 'event_credits' => 2, 'note' => 'Moved to per-event billing',
    ])->assertSessionHas('success');

    expect($this->subscription->fresh())
        ->status->toBe(SubscriptionStatus::Expired)
        ->auto_renew->toBeFalse()
        ->ended_reason->toBe('plan_changed');
});

it('refuses a second subscription while one is running', function () {
    $this->client->update(['billing_details' => ['phone' => '0771234567', 'address' => 'x', 'city' => 'Colombo']]);

    $this->post('/app/billing/subscription', ['interval' => 'monthly'])
        ->assertSessionHas('error', 'You\'re already subscribed to Business.');
});
