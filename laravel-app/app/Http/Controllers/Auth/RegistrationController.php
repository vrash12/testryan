<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Identity\RegisterContractor;
use App\Actions\Identity\RegistrationBotCheck;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Service;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegistrationController extends Controller
{
    public function create(Request $request, RegistrationBotCheck $bot): View
    {
        abort_unless(config('identity.registration_enabled'), 404);

        return view('auth.register', ['services' => Service::orderBy('service_name')->get(['service_id', 'service_name']), 'challenge' => $bot->issue($request)]);
    }

    public function store(RegisterRequest $request, RegisterContractor $register): RedirectResponse
    {
        try {
            $user = $register->run($request->validated(), $request->ip());
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23000' && str_contains($exception->getSql(), 'Users')) {
                throw ValidationException::withMessages(['email' => 'This email is already registered. Sign in or request a password reset.']);
            }
            throw $exception;
        }
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('auth_session_version', 0);

        return redirect()->route('account.status')->with('status', 'Your account was created. Verify your email while an administrator reviews your registration.');
    }
}
