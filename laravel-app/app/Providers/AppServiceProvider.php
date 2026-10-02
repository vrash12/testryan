<?php

namespace App\Providers;

use App\Auth\LegacyUserProvider;
use App\View\Composers\JobContentComposer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoApiTransport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton('brevo.http', fn () => HttpClient::create(['timeout' => 15, 'max_duration' => 20]));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Mail::extend('brevo', function () {
            if (app()->environment(['local', 'testing'])) {
                throw new \RuntimeException('Brevo is disabled in local/testing.');
            }
            $key = (string) config('services.brevo.key');
            if (trim($key) === '') {
                throw new \RuntimeException('BREVO_API_KEY is not configured.');
            }

            return new BrevoApiTransport($key, app('brevo.http'));
        });
        View::composer(['jobs.show', 'jobs.print'], JobContentComposer::class);
        Auth::provider('legacy', fn ($app, $config) => new LegacyUserProvider($app['hash'], $config['model']));
        $identifier = fn (Request $request) => hash('sha256', strtolower(trim(is_string($request->input('email')) ? $request->input('email') : '')));
        RateLimiter::for('identity-login', fn (Request $request) => [
            Limit::perMinute(5)->by('login-pair:'.$identifier($request).':'.$request->ip()),
            Limit::perMinute(10)->by('login-email:'.$identifier($request)),
            Limit::perMinute(20)->by('login-ip:'.$request->ip()),
        ]);
        RateLimiter::for('identity-form', fn (Request $request) => Limit::perMinute(20)->by('register-form:'.$request->ip()));
        RateLimiter::for('identity-registration', fn (Request $request) => [
            Limit::perHour(5)->by('register-ip:'.$request->ip()), Limit::perHour(2)->by('register-email:'.$identifier($request)),
            Limit::perHour(30)->by('register-global'),
        ]);
        RateLimiter::for('identity-recovery', fn (Request $request) => [Limit::perHour(5)->by('recover-ip:'.$request->ip()), Limit::perHour(3)->by('recover-email:'.$identifier($request))]);
        RateLimiter::for('identity-reset', fn (Request $request) => Limit::perMinute(5)->by('reset:'.$request->ip()));
        RateLimiter::for('identity-verification-send', fn (Request $request) => [Limit::perMinute(1)->by('verify-send:'.$request->user()->user_id), Limit::perHour(6)->by('verify-hour:'.$request->user()->user_id)]);
        RateLimiter::for('identity-verify', fn (Request $request) => Limit::perMinute(6)->by('verify:'.$request->user()->user_id));
        RateLimiter::for('identity-admin-password', fn (Request $request) => Limit::perMinute(5)->by('admin-password:'.$request->user()->user_id));
        RateLimiter::for('domain-write', fn (Request $request) => Limit::perMinute(30)->by('domain-write:'.$request->user()->user_id));
    }
}
