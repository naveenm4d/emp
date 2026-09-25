<?php

namespace App\Domains\Client\Http\Controllers\Dashboard;

use App\Core\Http\Controllers\InertiaController;
use App\Domains\Client\Contracts\ClientServiceInterface;
use App\Domains\Client\Models\Client;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetController extends InertiaController
{
    public function __construct(
        private readonly ClientServiceInterface $clients,
    ) {}

    public function showForgot(Request $request): Response
    {
        return Inertia::render('client/auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]);
    }

    public function sendLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::broker('clients')->sendResetLink($request->only('email'));

        // Always report success so the form cannot be used to probe accounts.
        return back()->with('status', __('A reset link will be sent if the account exists.'));
    }

    public function showReset(Request $request, string $token): Response
    {
        return Inertia::render('client/auth/reset-password', [
            'email' => $request->string('email')->toString(),
            'token' => $token,
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::broker('clients')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (Client $client, string $password) {
                $this->clients->updatePassword($client, $password);
                $client->forceFill(['remember_token' => Str::random(60)])->save();

                event(new PasswordReset($client));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }

        return to_route('client.login')->with('status', __($status));
    }
}
