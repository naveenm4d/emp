<?php

use App\Domains\Client\Enums\ClientPlan;
use App\Domains\Client\Models\Client;
use App\Domains\Event\Models\Event;
use App\Domains\Guest\Models\Guest;
use App\Domains\Staff\Enums\StaffPermission;
use App\Domains\Staff\Enums\StaffRole;
use App\Domains\Staff\Models\StaffActivity;
use App\Domains\Staff\Models\StaffMember;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->client = Client::factory()->starter()->create(['name' => 'Ada']);
    $this->staff = StaffMember::factory()->role(StaffRole::Admin)->create(['name' => 'Sam']);
    $this->actingAs($this->staff, 'staff');
});

it('changes a client\'s plan and logs who did it and why', function () {
    $this->patch("/admin/clients/{$this->client->id}/plan", [
        'plan' => 'celebration',
        'event_credits' => 2,
        'plan_expires_at' => '2030-01-01',
        'note' => 'Paid Rs. 9,980 by bank transfer, ref 1234',
    ])->assertSessionHas('success', 'Ada is now on the Celebration plan.');

    expect($this->client->fresh())
        ->plan->toBe(ClientPlan::Celebration)
        ->event_credits->toBe(2)
        ->plan_expires_at->toBeNull(); // only subscriptions end

    $activity = StaffActivity::sole();
    expect($activity)
        ->staff_member_id->toBe($this->staff->id)
        ->action->toBe('admin.clients.plan')
        ->client_id->toBe($this->client->id)
        ->note->toBe('Paid Rs. 9,980 by bank transfer, ref 1234')
        ->description->toBe('Plan Starter → Celebration, event credits 0 → 2')
        ->and($activity->changes['before']['plan'])->toBe('starter')
        ->and($activity->changes['after']['plan'])->toBe('celebration');

    $this->get("/admin/clients/{$this->client->id}")->assertInertia(fn (Assert $page) => $page
        ->where('plan.plan', 'celebration')
        ->where('planHistory.0.note', 'Paid Rs. 9,980 by bank transfer, ref 1234')
        ->where('planHistory.0.staff.name', 'Sam'));
});

it('sets when a subscription ends', function () {
    $this->patch("/admin/clients/{$this->client->id}/plan", [
        'plan' => 'business', 'event_credits' => 0, 'plan_expires_at' => '2030-01-31', 'note' => 'Yearly',
    ])->assertSessionHas('success');

    expect($this->client->fresh()->plan_expires_at->toDateString())->toBe('2030-01-31');
});

it('needs a user limit for Enterprise and logs it', function () {
    $this->patch("/admin/clients/{$this->client->id}/plan", ['plan' => 'enterprise', 'event_credits' => 0, 'note' => 'Contract'])
        ->assertSessionHasErrors(['user_limit' => 'Enterprise accounts need a user limit.']);

    $this->patch("/admin/clients/{$this->client->id}/plan", ['plan' => 'enterprise', 'event_credits' => 0, 'user_limit' => 12, 'note' => 'Contract'])
        ->assertSessionHas('success');

    expect($this->client->fresh()->userLimit())->toBe(12)
        ->and(StaffActivity::sole()->description)->toBe('Plan Starter → Enterprise, users 12');
});

it('needs a note and a known plan', function () {
    $this->patch("/admin/clients/{$this->client->id}/plan", ['plan' => 'gold', 'event_credits' => 0])
        ->assertSessionHasErrors([
            'plan' => 'The selected plan is invalid.',
            'note' => 'Add a note: why the plan changed (e.g. the payment reference).',
        ]);

    expect(StaffActivity::count())->toBe(0);
});

it('needs the clients.plan permission', function (StaffMember $staff) {
    $this->actingAs($staff, 'staff');

    $this->patch("/admin/clients/{$this->client->id}/plan", ['plan' => 'business', 'event_credits' => 0, 'note' => 'x'])
        ->assertForbidden();
})->with([
    'viewer' => fn () => StaffMember::factory()->role(StaffRole::Viewer)->create(),
    'admin without it' => fn () => StaffMember::factory()->role(StaffRole::Admin)->withPermissions([StaffPermission::ClientsRead, StaffPermission::ClientsUpdate])->create(),
]);

it('adds extra guests to an event and logs it', function () {
    $this->client->update(['plan' => 'celebration']);
    $event = Event::factory()->for($this->client)->create(['title' => 'Wedding']);

    $this->patch("/admin/clients/{$this->client->id}/events/{$event->id}/extra-guests", ['blocks' => 2, 'note' => 'Paid Rs. 2,000'])
        ->assertSessionHas('success', 'Wedding now allows up to 700 guests.');

    expect($event->fresh()->extra_guests)->toBe(200)
        ->and(StaffActivity::sole())
        ->action->toBe('admin.clients.events.extra-guests')
        ->note->toBe('Paid Rs. 2,000');

    Guest::factory()->for($event)->count(700)->create();
    $this->actingAs($this->client->owner, 'client')
        ->post("/app/events/{$event->id}/guests", ['name' => 'One more', 'phone' => '+15550109999'])
        ->assertSessionHas('error', 'This event allows up to 700 guests on the Celebration plan. Add more guests: Rs. 1,000 per 100.');
});

it('refuses extra guests on plans without them', function (string $plan) {
    $this->client->update(['plan' => $plan]);
    $event = Event::factory()->for($this->client)->create();

    $this->patch("/admin/clients/{$this->client->id}/events/{$event->id}/extra-guests", ['blocks' => 1, 'note' => 'x'])
        ->assertSessionHas('error');

    expect($event->fresh()->extra_guests)->toBe(0);
})->with(['starter', 'enterprise']);

it('needs a note for extra guests', function () {
    $this->client->update(['plan' => 'business']);
    $event = Event::factory()->for($this->client)->create();

    $this->patch("/admin/clients/{$this->client->id}/events/{$event->id}/extra-guests", ['blocks' => 1])
        ->assertSessionHasErrors(['note' => 'Add a note: why the guests were added (e.g. the payment reference).']);
});
