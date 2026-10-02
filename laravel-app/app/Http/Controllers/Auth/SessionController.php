<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Identity\SecurityAudit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        if (! Auth::attempt($request->only('email', 'password'), false)) {
            throw ValidationException::withMessages(['email' => 'These credentials could not be confirmed.']);
        }
        $request->session()->regenerate();
        $request->session()->put('auth_session_version', $request->user()->accountState?->session_version ?? 0);
        app(SecurityAudit::class)->record($request->user(), 'sign-in succeeded.');
        $intended = $request->session()->pull('url.intended');
        $root = parse_url(route('home'));
        if (is_string($intended)) {
            $parts = parse_url($intended);
            $prefix = rtrim($root['path'] ?? '', '/');
            if ($parts !== false && ($parts['host'] ?? null) === ($root['host'] ?? null)
                && ($parts['scheme'] ?? null) === ($root['scheme'] ?? null) && ($parts['port'] ?? null) === ($root['port'] ?? null)
                && str_starts_with($parts['path'] ?? '', $prefix.'/')) {
                return redirect()->to($intended);
            }
        }

        return redirect()->route('dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
