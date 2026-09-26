<?php

use App\Domains\Client\Models\Client;
use App\Domains\Staff\Enums\StaffPermission;
use App\Domains\Staff\Enums\StaffRole;
use App\Domains\Staff\Models\StaffActivity;
use App\Domains\Staff\Models\StaffMember;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->client = Client::factory()->create(['name' => 'Ada']);
    $this->staff = StaffMember::factory()->role(StaffRole::Admin)->create();
    $this->actingAs($this->staff, 'staff');
});

it('logs a staff change once, without secrets', function () {
    $this->patch("/admin/clients/{$this->client->id}", [
        'name' => 'Ada Lovelace',
        'email' => $this->client->email,
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertSessionHas('success');

    $activity = StaffActivity::sole();
    expect($activity)
        ->staff_member_id->toBe($this->staff->id)
        ->action->toBe('admin.clients.update')
        ->description->toBe('Updated client account: Ada Lovelace')
        ->client_id->toBe($this->client->id)
        ->subject_id->toBe($this->client->id)
        ->and($activity->changes)->toHaveKey('name', 'Ada Lovelace')
        ->and($activity->changes)->not->toHaveKey('password')
        ->and($activity->changes)->not->toHaveKey('password_confirmation');
});

it('does not log reads, failed validation or refused actions', function () {
    $this->get("/admin/clients/{$this->client->id}")->assertOk();
    $this->patch("/admin/clients/{$this->client->id}", ['name' => ''])->assertSessionHasErrors('name');
    $this->patch("/admin/clients/{$this->client->id}/plan", ['plan' => 'business', 'event_credits' => 0])->assertSessionHasErrors('note');

    expect(StaffActivity::count())->toBe(0);
});

it('logs a plan change only once', function () {
    $this->patch("/admin/clients/{$this->client->id}/plan", ['plan' => 'enterprise', 'event_credits' => 0, 'note' => 'Signed contract'])
        ->assertSessionHas('success');

    expect(StaffActivity::count())->toBe(1);
});

it('logs signing in and out', function () {
    auth('staff')->logout();
    $staff = StaffMember::factory()->role(StaffRole::Admin)->create(['email' => 'sam@emp.test']);

    $this->post('/admin/login', ['email' => 'sam@emp.test', 'password' => 'password'])->assertRedirect();
    $this->post('/admin/logout');

    expect(StaffActivity::where('staff_member_id', $staff->id)->orderBy('created_at')->pluck('action')->all())
        ->toEqualCanonicalizing(['admin.login', 'admin.logout']);
});

it('shows the log to staff who can read it, with filters', function () {
    $this->patch("/admin/clients/{$this->client->id}/plan", ['plan' => 'business', 'event_credits' => 0, 'note' => 'Paid']);
    $other = Client::factory()->create();
    $this->patch("/admin/clients/{$other->id}/plan", ['plan' => 'business', 'event_credits' => 0, 'note' => 'Paid too']);

    $this->get('/admin/activity')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('admin/activity/index')
        ->has('activities.data', 2));

    $this->get("/admin/activity?client={$this->client->id}")->assertInertia(fn (Assert $page) => $page
        ->has('activities.data', 1)
        ->where('activities.data.0.note', 'Paid'));
});

it('hides the log from viewers', function () {
    $this->actingAs(StaffMember::factory()->role(StaffRole::Viewer)->create(), 'staff');

    $this->get('/admin/activity')->assertForbidden();
});

it('grants the new permissions to admins through their role defaults', function () {
    expect(StaffMember::factory()->role(StaffRole::Admin)->create()->hasPermission(StaffPermission::ActivityRead))->toBeTrue()
        ->and(StaffMember::factory()->role(StaffRole::Viewer)->create()->hasPermission(StaffPermission::ClientsPlan))->toBeFalse();
});
