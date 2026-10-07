<?php

namespace App\Core\Http\Controllers\Concerns;

use App\Domains\Client\Models\Client;
use App\Domains\Client\Models\ClientUser;
use Illuminate\Http\Request;

/**
 * Client dashboard controllers: who is signed in (a ClientUser) and the
 * account they act for (the Client that owns events, plan and credits).
 */
trait ResolvesClient
{
    protected function clientUser(Request $request): ClientUser
    {
        /** @var ClientUser */
        return $request->user('client');
    }

    protected function client(Request $request): Client
    {
        return $this->clientUser($request)->client;
    }
}
