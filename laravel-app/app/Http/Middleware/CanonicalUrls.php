<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class CanonicalUrls
{
    public function handle(Request $request, Closure $next): Response
    {
        $root = rtrim(config('app.url'), '/');
        if (empty(parse_url($root, PHP_URL_PATH))) {
            $root .= $request->getBaseUrl();
        }
        URL::forceRootUrl($root);
        URL::forceScheme(parse_url($root, PHP_URL_SCHEME));

        return $next($request);
    }
}
