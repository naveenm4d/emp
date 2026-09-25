<?php

use App\Domains\Client\Providers\ClientServiceProvider;
use App\Domains\Event\Providers\EventServiceProvider;
use App\Domains\Guest\Providers\GuestServiceProvider;
use App\Domains\Invitation\Providers\InvitationServiceProvider;
use App\Domains\Notification\Providers\NotificationServiceProvider;
use App\Domains\Staff\Providers\StaffServiceProvider;
use App\Domains\Template\Providers\TemplateServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,

    // Domains
    ClientServiceProvider::class,
    StaffServiceProvider::class,
    TemplateServiceProvider::class,
    EventServiceProvider::class,
    GuestServiceProvider::class,
    InvitationServiceProvider::class,
    NotificationServiceProvider::class,
];
