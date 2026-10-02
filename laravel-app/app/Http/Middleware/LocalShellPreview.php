<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LocalShellPreview
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(app()->environment('local') && config('nestcrafters.shell_previews'), 404);

        return $next($request);
    }
}
