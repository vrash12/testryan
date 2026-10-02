<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        abort_unless($request->user()?->role() === $role, 403);

        if ($role === 'contractor') {
            abort_unless($request->user()?->is_approved === 1, 403);
        }

        return $next($request);
    }
}
