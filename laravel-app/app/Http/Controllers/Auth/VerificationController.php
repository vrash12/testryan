<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Identity\SecurityAudit;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class VerificationController extends Controller
{
    public function notice(Request $request): View
    {
        if ($request->user()->hasVerifiedEmail() && ($request->user()->role() === 'admin' || $request->user()->canAccessWork())) {
            return view('auth.account-settings', ['user' => $request->user()]);
        }

        return view('auth.account-status', ['user' => $request->user()]);
    }

    public function settings(Request $request): View|RedirectResponse
    {
        if (! $request->user()->hasVerifiedEmail() || ($request->user()->role() !== 'admin' && ! $request->user()->canAccessWork())) {
            return redirect()->route('account.status');
        }

        return view('auth.account-settings', ['user' => $request->user()]);
    }

    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        if (! $request->user()->hasVerifiedEmail()) {
            DB::transaction(function () use ($request): void {
                $request->fulfill();
                app(SecurityAudit::class)->record($request->user(), 'email address verified.');
            });
        }

        return redirect()->route('dashboard')->with('status', 'Your email address is verified.');
    }

    public function resend(Request $request): RedirectResponse
    {
        $request->user()->sendEmailVerificationNotification();

        return redirect()->route('account.status')->with('status', 'A verification message has been requested. Check your email; delivery can be retried if needed.');
    }
}
