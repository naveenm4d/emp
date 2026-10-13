<?php

namespace App\Core\Http\Controllers\Web;

use App\Core\Http\Controllers\Controller;
use App\Domains\Billing\Contracts\PriceServiceInterface;
use Inertia\Inertia;
use Inertia\Response;

/** The marketing homepage. */
class HomeController extends Controller
{
    public function __invoke(PriceServiceInterface $prices): Response
    {
        return Inertia::render('web/home', [
            'dashboardUrl' => route('client.dashboard'),
            // The plans' current prices (staff set them); minor units.
            'prices' => $prices->catalogue(),
        ]);
    }
}
