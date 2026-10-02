<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class ProfilePhotoController extends Controller
{
    public function __invoke(User $user)
    {
        Gate::authorize('view', $user);
        if ($user->profile_photo === null) {
            abort(404);
        }
        $user->load(['profileUpload', 'privatePhoto']);
        $metadata = $user->profileUpload ?: $user->privatePhoto;
        $headers = [
            'Content-Type' => $metadata?->mime_type ?: $metadata?->detected_mime_type ?: 'image/jpeg',
            'Content-Disposition' => 'inline; filename="profile-photo"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ];

        if ($metadata?->path && Storage::disk($metadata->disk)->exists($metadata->path)) {
            return response()->file(Storage::disk($metadata->disk)->path($metadata->path), $headers);
        }

        return response($user->profile_photo, 200, $headers);
    }
}
