<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(Request $request): Response
    {
        // Public liveness contains no infrastructure details or database query.
        $checks = ['Application' => true];

        if (app()->environment('local')) {
            try {
                $checks['Local database'] = (int) DB::selectOne('SELECT 1 AS ready')->ready === 1;
            } catch (Throwable $exception) {
                Log::error('Local health database check failed.', ['exception' => $exception]);
                $checks['Local database'] = false;
            }

            $checks['Private storage'] = is_writable(storage_path('app/private'));
            $checks['Built assets'] = is_file(public_path('build/manifest.json')) || is_file(public_path('hot'));
            $checks['Local mail capture'] = config('mail.default') === 'log';
        }

        $healthy = ! in_array(false, $checks, true);
        $status = $healthy ? 200 : 503;

        if ($request->expectsJson()) {
            return response(['status' => $healthy ? 'ok' : 'unavailable'], $status)
                ->header('Cache-Control', 'no-store');
        }

        return response()->view('health', compact('checks', 'healthy'), $status);
    }
}
