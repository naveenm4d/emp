<?php

namespace App\Domains\Event\Http\Controllers\Admin;

use App\Domains\Event\Http\Controllers\Dashboard\MapPreviewController as DashboardMapPreviewController;

/** Staff editing an event get the same map preview (guarded by `can:events.read` on the route). */
class MapPreviewController extends DashboardMapPreviewController {}
