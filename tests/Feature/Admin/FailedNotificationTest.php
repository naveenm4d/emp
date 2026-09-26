<?php

use App\Domains\Notification\Enums\NotificationStatus;
use App\Domains\Notification\Jobs\SendNotificationJob;
use App\Domains\Notification\Models\Notification;
use App\Domains\Staff\Enums\StaffPermission;
use App\Domains\Staff\Models\StaffMember;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

it('lists failed notifications', function () {
    Notification::factory()->failed()->count(2)->create();
    Notification::factory()->create();
    $this->actingAs(StaffMember::factory()->withPermissions([StaffPermission::NotificationsRead])->create(), 'staff');

    $this->get('/admin/notifications/failed')
        ->assertInertia(fn (Assert $page) => $page->component('admin/notifications/failed')->has('notifications.data', 2));
});

it('re-queues a failed notification', function () {
    Queue::fake();
    $notification = Notification::factory()->failed()->create();
    $this->actingAs(StaffMember::factory()->withPermissions([StaffPermission::NotificationsRetry])->create(), 'staff');

    $this->post("/admin/notifications/{$notification->id}/retry")->assertSessionHas('success');

    expect($notification->fresh())->status->toBe(NotificationStatus::Pending)->error->toBeNull();
    Queue::assertPushed(SendNotificationJob::class, fn ($job) => $job->notificationId === $notification->id);
});

it('refuses to retry a notification that has not failed', function () {
    $notification = Notification::factory()->create(['status' => NotificationStatus::Sent]);
    $this->actingAs(StaffMember::factory()->superAdmin()->create(), 'staff');

    $this->post("/admin/notifications/{$notification->id}/retry")
        ->assertSessionHas('error', 'Only failed notifications can be retried.');
});

it('requires notifications.retry', function () {
    $notification = Notification::factory()->failed()->create();
    $this->actingAs(StaffMember::factory()->withPermissions([StaffPermission::NotificationsRead])->create(), 'staff');

    $this->post("/admin/notifications/{$notification->id}/retry")->assertForbidden();
});
