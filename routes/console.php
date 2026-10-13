<?php

use Illuminate\Support\Facades\Schedule;

// Safety net for notifications stuck in pending (e.g. after a worker outage).
Schedule::command('notifications:requeue-pending --minutes=10')->everyFiveMinutes()->withoutOverlapping();

// Automatic RSVP reminders (events opt in; each reminder goes out once per link).
Schedule::command('rsvps:send-reminders')->hourly()->withoutOverlapping();

// Business renewals (charged up to a day ahead, once per period), ended subscriptions,
// yearly renewal reminders, and payments left unfinished.
Schedule::command('billing:renew-subscriptions')->hourly()->withoutOverlapping();
