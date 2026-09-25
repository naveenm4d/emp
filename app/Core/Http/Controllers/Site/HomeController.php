<?php

namespace App\Core\Http\Controllers\Site;

use App\Core\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/** The marketing homepage. */
class HomeController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('site/home', [
            'dashboardUrl' => route('client.dashboard'),
        ]);
    }
}
