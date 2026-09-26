<?php

namespace App\Domains\Staff\Http\Controllers\Admin;

use App\Core\Http\Controllers\InertiaController;
use App\Domains\Staff\Contracts\StaffActivityServiceInterface;
use App\Domains\Staff\Contracts\StaffMemberServiceInterface;
use App\Domains\Staff\Http\Requests\Admin\LoginRequest;
use App\Domains\Staff\Models\StaffMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends InertiaController
{
    public function __construct(
        private readonly StaffMemberServiceInterface $staff,
        private readonly StaffActivityServiceInterface $activities,
    ) {}

    public function showLogin(): Response
    {
        return Inertia::render('admin/auth/login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        /** @var StaffMember $member */
        $member = $request->user('staff');
        $this->staff->recordLogin($member);
        $this->activities->record($member, 'admin.login', 'Signed in');

        return redirect()->intended(route('admin.dashboard', absolute: false));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('staff')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('admin.login');
    }
}
