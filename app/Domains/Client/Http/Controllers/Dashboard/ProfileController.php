<?php

namespace App\Domains\Client\Http\Controllers\Dashboard;

use App\Core\Http\Controllers\Concerns\ResolvesClient;
use App\Core\Http\Controllers\InertiaController;
use App\Domains\Client\Contracts\ClientServiceInterface;
use App\Domains\Client\Http\Requests\Dashboard\UpdatePasswordRequest;
use App\Domains\Client\Http\Requests\Dashboard\UpdateProfileRequest;
use App\Domains\Client\Http\Resources\ClientUserResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** The signed-in user's own account: name, email and password. */
class ProfileController extends InertiaController
{
    use ResolvesClient;

    public function __construct(
        private readonly ClientServiceInterface $clients,
    ) {}

    public function edit(Request $request): Response
    {
        return Inertia::render('client/profile/edit', [
            'user' => ClientUserResource::make($this->clientUser($request)),
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $this->clients->updateProfile($this->clientUser($request), $request->toData());

        return $this->backWithSuccess('Profile updated.');
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $this->clients->updatePassword($this->clientUser($request), $request->validated('password'));

        return $this->backWithSuccess('Password updated.');
    }
}
