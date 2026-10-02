<?php

namespace App\Http\Controllers\Contractor;

use App\Actions\Contractor\ContractorManagement;
use App\Http\Controllers\Controller;
use App\Http\Requests\ContractorProfileRequest;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(User $user): View
    {
        Gate::authorize('update', $user);
        abort_unless((int) auth()->id() === (int) $user->user_id, 403);

        return view('contractor.profile', ['contractor' => $user->load(['accountState', 'services', 'profileUpload']), 'services' => Service::query()->orderBy('service_name')->get()]);
    }

    public function update(ContractorProfileRequest $request, User $user, ContractorManagement $management): RedirectResponse
    {
        abort_unless((int) $request->user()->user_id === (int) $user->user_id, 403);
        $updated = $management->update($request->user(), $user, $request->validated(), $request->file('profile_photo'));
        // The contractor is allowed to see the review/status page after the edit that revoked work access.
        $request->session()->put('auth_session_version', $updated->accountState?->session_version ?? 0);

        return redirect()->route('account.status')->with('status', 'Your profile was saved and is awaiting admin review.');
    }

    public function removePhoto(Request $request, User $user, ContractorManagement $management): RedirectResponse
    {
        abort_unless((int) $request->user()->user_id === (int) $user->user_id, 403);
        Gate::authorize('update', $user);
        $updated = $management->removeProfilePhoto($request->user(), $user);
        $request->session()->put('auth_session_version', $updated->accountState?->session_version ?? 0);

        return redirect()->route('account.status')->with('status', 'Your profile photo was removed and is awaiting admin review.');
    }
}
