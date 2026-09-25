<?php

namespace App\Domains\Client\Http\Controllers\Dashboard;

use App\Core\Http\Controllers\InertiaController;
use App\Domains\Client\Contracts\ClientServiceInterface;
use App\Domains\Client\Http\Requests\Dashboard\LoginRequest;
use App\Domains\Client\Http\Requests\Dashboard\RegisterRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends InertiaController
{
    public function __construct(
        private readonly ClientServiceInterface $clients,
    ) {}

    public function showLogin(Request $request): Response
    {
        return Inertia::render('client/auth/login', [
            'status' => $request->session()->get('status'),
        ]);
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        return redirect()->intended(route('client.dashboard', absolute: false));
    }

    public function showRegister(): Response
    {
        return Inertia::render('client/auth/register');
    }

    public function register(RegisterRequest $request): RedirectResponse
    {
        $client = $this->clients->register($request->toData());

        Auth::guard('client')->login($client);
        $request->session()->regenerate();

        return to_route('client.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('client')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('client.login');
    }
}
