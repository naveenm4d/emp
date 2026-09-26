<?php

namespace App\Domains\Staff\Http\Middleware;

use App\Domains\Staff\Models\StaffMember;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Re-checks is_active on every request so deactivated staff lose access
 * immediately instead of when their session expires.
 */
class EnsureStaffIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $staff = $request->user('staff');

        if ($staff instanceof StaffMember && ! $staff->is_active) {
            Auth::guard('staff')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return to_route('admin.login')->with('error', 'Your account has been deactivated.');
        }

        return $next($request);
    }
}
