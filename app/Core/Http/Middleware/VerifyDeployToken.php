<?php

namespace App\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards /webhooks/deploy/*: the request must carry `emp.deploy_token` as a
 * bearer token. Without a configured token the routes don't exist (404).
 */
class VerifyDeployToken
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) config('emp.deploy_token');

        abort_if($token === '', Response::HTTP_NOT_FOUND);
        abort_unless(hash_equals($token, (string) $request->bearerToken()), Response::HTTP_FORBIDDEN);

        return $next($request);
    }
}
