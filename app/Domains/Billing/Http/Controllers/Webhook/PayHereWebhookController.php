<?php

namespace App\Domains\Billing\Http\Controllers\Webhook;

use App\Core\Http\Controllers\Controller;
use App\Domains\Billing\Contracts\PaymentServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * PayHere's notify_url: the only place a checkout's result is taken from.
 * The service checks the signature and the amount; the answer is always 200
 * so PayHere doesn't retry calls we rejected on purpose.
 */
class PayHereWebhookController extends Controller
{
    public function notify(Request $request, PaymentServiceInterface $payments): Response
    {
        $payments->handleNotification($request->post());

        return response('OK');
    }
}
