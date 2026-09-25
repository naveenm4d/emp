<?php

namespace App\Domains\Client\Http\Controllers\Internal;

use App\Core\Http\Controllers\InertiaController;
use App\Domains\Client\Contracts\ClientQueryServiceInterface;
use App\Domains\Client\DTOs\ClientFilters;
use App\Domains\Client\Http\Resources\ClientResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends InertiaController
{
    public function __construct(
        private readonly ClientQueryServiceInterface $clients,
    ) {}

    public function index(Request $request): Response
    {
        $filters = ClientFilters::fromArray($request->query());

        return Inertia::render('internal/clients/index', [
            'clients' => ClientResource::collection($this->clients->search($filters)),
            'filters' => $filters->toArray(),
        ]);
    }
}
