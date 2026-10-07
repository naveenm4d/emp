<?php

namespace App\Domains\Client\Http\Controllers\Dashboard;

use App\Core\Http\Controllers\InertiaController;
use App\Domains\Client\Contracts\ClientServiceInterface;
use App\Domains\Client\Contracts\ClientTeamServiceInterface;
use App\Domains\Client\Http\Requests\Dashboard\AcceptInvitationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Joining a client account from an invitation email: choose a password and sign in. */
class InvitationController extends InertiaController
{
    public function show(Request $request, string $token): Response
    {
        return Inertia::render('client/auth/accept-invitation', [
            'email' => $request->string('email')->toString(),
            'token' => $token,
        ]);
    }

    public function accept(
        AcceptInvitationRequest $request,
        ClientTeamServiceInterface $team,
        ClientServiceInterface $clients,
    ): RedirectResponse {
        $user = $team->acceptInvitation(
            (string) $request->validated('email'),
            (string) $request->validated('token'),
            (string) $request->validated('password'),
        );

        if ($user === null) {
            throw ValidationException::withMessages([
                'email' => 'This invitation link is invalid or has expired. Ask for a new one.',
            ]);
        }

        Auth::guard('client')->login($user);
        $request->session()->regenerate();
        $clients->recordLogin($user);

        return to_route('client.dashboard')->with('success', "Welcome to {$user->client->name}.");
    }
}
