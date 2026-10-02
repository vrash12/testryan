<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Identity\SetAccountPassword;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AdminPasswordRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AccountSecurityController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', User::class);

        return view('admin.accounts', ['accounts' => User::with('accountState')->select(['user_id', 'company_name', 'first_name', 'last_name', 'email', 'is_admin', 'is_approved'])->orderBy('user_id')->paginate(20)]);
    }

    public function edit(User $user): View
    {
        Gate::authorize('resetPassword', $user);

        return view('admin.account-password', ['account' => $user]);
    }

    public function update(AdminPasswordRequest $request, User $user, SetAccountPassword $setPassword): RedirectResponse
    {
        $setPassword->run($user, $request->validated('password'), $request->user());
        if ($user->is($request->user())) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', 'Your password was updated. Sign in again.');
        }

        return redirect()->route('admin.accounts.index')->with('status', 'Password updated. Existing sessions for this account have ended.');
    }
}
