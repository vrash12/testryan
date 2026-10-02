<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCurrentAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user->exists) {
            $user->refresh(); // Recheck role, approval, lifecycle and email proof on every request.
        }
        abort_unless($user->role() !== null, 403);
        $version = $user->accountState?->session_version ?? 0;
        if (! $user->isActiveAccount() || $request->session()->get('auth_session_version') !== $version) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', 'Please sign in again. Your session has ended.');
        }

        return $next($request);
    }
}
