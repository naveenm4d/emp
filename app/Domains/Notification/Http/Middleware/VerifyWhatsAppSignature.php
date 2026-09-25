<?php

namespace App\Domains\Notification\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies Meta's X-Hub-Signature-256 header (HMAC-SHA256 of the raw body
 * with the Meta app secret).
 */
class VerifyWhatsAppSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('services.whatsapp.app_secret');

        if (blank($secret)) {
            // Allowed only outside production, to ease local testing.
            return app()->isProduction()
                ? response()->json(['error' => 'Webhook secret not configured.'], 503)
                : $next($request);
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expected, (string) $request->header('X-Hub-Signature-256'))) {
            return response()->json(['error' => 'Invalid signature.'], 401);
        }

        return $next($request);
    }
}
