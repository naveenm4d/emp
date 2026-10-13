<?php

use App\Domains\Billing\Enums\BillingProduct;
use App\Domains\Billing\Enums\PaymentStatus;
use App\Domains\Billing\Models\BillingPrice;
use App\Domains\Billing\Models\Payment;
use App\Domains\Staff\Enums\StaffRole;
use App\Domains\Staff\Models\StaffActivity;
use App\Domains\Staff\Models\StaffMember;
use Illuminate\Support\Facades\Http;
use Tests\Support\PayHere;

beforeEach(function () {
    PayHere::configure();
    $this->staff = StaffMember::factory()->role(StaffRole::Admin)->create();
    $this->actingAs($this->staff, 'staff');
});

it('changes a price with a note and logs it', function () {
    $this->patch('/admin/billing/prices/business_monthly', ['amount' => '17990', 'is_active' => true, 'note' => 'New year pricing'])
        ->assertSessionHas('success', 'Business, monthly now costs Rs. 17,990.');

    expect(BillingPrice::query()->where('product', BillingProduct::BusinessMonthly)->value('amount'))->toBe(1799000);

    $activity = StaffActivity::sole();
    expect($activity)
        ->action->toBe('admin.billing.prices.update')
        ->note->toBe('New year pricing')
        ->and($activity->changes)->toEqual(['product' => 'business_monthly', 'before' => ['amount' => 1499000, 'is_active' => true], 'after' => ['amount' => 1799000, 'is_active' => true]]);
});

it('requires a note and a sensible amount', function () {
    $this->patch('/admin/billing/prices/business_monthly', ['amount' => '5', 'is_active' => true])
        ->assertSessionHasErrors(['amount' => 'PayHere takes at least Rs. 10.', 'note' => 'Add a note: why the price changed.']);
});

it('refunds a payment through PayHere and logs it', function () {
    $payment = Payment::factory()->create(['payhere_payment_id' => '320025071278']);
    PayHere::fakeApi(['/payment/refund' => Http::response(['status' => 1, 'msg' => 'Successfully processed the refund', 'data' => 155010])]);

    $this->post("/admin/billing/payments/{$payment->id}/refund", ['note' => 'Charged twice'])->assertSessionHas('success');

    expect($payment->fresh())->status->toBe(PaymentStatus::Refunded)->refunded_at->not->toBeNull()
        ->and(StaffActivity::sole())->action->toBe('admin.billing.payments.refund')->note->toBe('Charged twice');
    Http::assertSent(fn ($request) => $request->url() === PayHere::API.'/payment/refund'
        && $request['payment_id'] === '320025071278'
        && $request->hasHeader('Authorization', 'Bearer test-token'));
});

it('only refunds paid payments', function () {
    $payment = Payment::factory()->declined()->create();

    $this->post("/admin/billing/payments/{$payment->id}/refund", ['note' => 'x'])
        ->assertSessionHas('error', 'Only a paid payment can be refunded.');
});

it('keeps billing to staff with the billing permissions', function () {
    $viewer = StaffMember::factory()->role(StaffRole::Viewer)->create();
    $this->actingAs($viewer, 'staff');
    $payment = Payment::factory()->create();

    $this->get('/admin/billing/payments')->assertForbidden();
    $this->get('/admin/billing/prices')->assertForbidden();
    $this->patch('/admin/billing/prices/business_monthly', ['amount' => '1', 'is_active' => true, 'note' => 'x'])->assertForbidden();
    $this->post("/admin/billing/payments/{$payment->id}/refund", ['note' => 'x'])->assertForbidden();
});
