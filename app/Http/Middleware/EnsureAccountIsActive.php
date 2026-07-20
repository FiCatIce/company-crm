<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cuts the session of an account deactivated mid-flight (hierarchy H7b). The login
 * hooks in FortifyServiceProvider only guard the DOOR; without this a user who was
 * already signed in would keep full access until their session happened to expire —
 * which is exactly the window a deactivation is meant to close.
 *
 * Runs on the web group so it covers every authenticated page. API tokens are a
 * separate surface: the CTI ingest token is a machine credential, not a login.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('status', 'Akun Anda dinonaktifkan. Hubungi administrator.');
        }

        return $next($request);
    }
}
