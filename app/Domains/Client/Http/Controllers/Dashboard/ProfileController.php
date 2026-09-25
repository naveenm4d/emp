<?php

namespace App\Domains\Client\Http\Controllers\Dashboard;

use App\Core\Http\Controllers\InertiaController;
use App\Domains\Client\Contracts\ClientServiceInterface;
use App\Domains\Client\Http\Requests\Dashboard\UpdatePasswordRequest;
use App\Domains\Client\Http\Requests\Dashboard\UpdateProfileRequest;
use App\Domains\Client\Http\Resources\ClientResource;
use App\Domains\Client\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends InertiaController
{
    public function __construct(
        private readonly ClientServiceInterface $clients,
    ) {}

    public function edit(Request $request): Response
    {
        return Inertia::render('client/profile/edit', [
            'client' => ClientResource::make($this->client($request)),
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $this->clients->updateProfile($this->client($request), $request->toData());

        return $this->backWithSuccess('Profile updated.');
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $this->clients->updatePassword($this->client($request), $request->validated('password'));

        return $this->backWithSuccess('Password updated.');
    }

    private function client(Request $request): Client
    {
        /** @var Client */
        return $request->user('client');
    }
}
