<?php

use Illuminate\Support\Facades\Schedule;

// Safety net for notifications stuck in pending (e.g. after a worker outage).
Schedule::command('notifications:requeue-pending --minutes=10')->everyFiveMinutes()->withoutOverlapping();
