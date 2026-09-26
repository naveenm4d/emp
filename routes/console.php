<?php

use Illuminate\Support\Facades\Schedule;

// Safety net for notifications stuck in pending (e.g. after a worker outage).
Schedule::command('notifications:requeue-pending --minutes=10')->everyFiveMinutes()->withoutOverlapping();

// Automatic RSVP reminders (events opt in; each reminder goes out once per link).
Schedule::command('rsvps:send-reminders')->hourly()->withoutOverlapping();
