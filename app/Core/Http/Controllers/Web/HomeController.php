<?php

namespace App\Core\Http\Controllers\Web;

use App\Core\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/** The marketing homepage. */
class HomeController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('web/home', [
            'dashboardUrl' => route('client.dashboard'),
        ]);
    }
}
