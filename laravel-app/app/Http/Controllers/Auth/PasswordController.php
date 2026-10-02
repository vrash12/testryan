<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Identity\SetAccountPassword;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RecoveryRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function request(): View
    {
        return view('auth.forgot-password');
    }

    public function email(RecoveryRequest $request): RedirectResponse
    {
        DB::transaction(fn () => Password::sendResetLink($request->only('email')));

        return back()->with('status', 'If this address belongs to an eligible account, a password reset message has been requested.');
    }

    public function reset(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => is_string($request->query('email')) ? $request->query('email') : '']);
    }

    public function update(ResetPasswordRequest $request, SetAccountPassword $setPassword): RedirectResponse
    {
        $result = DB::transaction(function () use ($request, $setPassword) {
            DB::table(config('auth.passwords.users.table'))->where('email', $request->validated('email'))->lockForUpdate()->first();

            return Password::reset($request->only('email', 'password', 'password_confirmation', 'token'), fn ($user, $password) => $setPassword->run($user, $password));
        });
        if ($result !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => 'This reset link is invalid or expired. Request a new link.'])->withInput($request->only('email'));
        }
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Your password was updated. Sign in with your new password.');
    }
}
