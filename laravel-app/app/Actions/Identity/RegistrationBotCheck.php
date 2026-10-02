<?php

namespace App\Actions\Identity;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class RegistrationBotCheck
{
    public function issue(Request $request): array
    {
        $a = random_int(1, 9);
        $b = random_int(1, 9);
        $challenge = ['token' => Str::random(40), 'answer' => (string) ($a + $b), 'issued_at' => now()->timestamp];
        $request->session()->put('registration_challenge', $challenge);

        return ['token' => $challenge['token'], 'question' => 'What is '.$a.' + '.$b.'?'];
    }

    public function passes(Request $request): bool
    {
        $challenge = $request->session()->pull('registration_challenge');
        if (! is_array($challenge) || ! hash_equals($challenge['token'], (string) $request->input('bot_token')) || $request->filled('website')) {
            return false;
        }
        $age = now()->timestamp - $challenge['issued_at'];
        if ($age < config('identity.minimum_form_seconds') || $age > 60 * config('identity.challenge_minutes')) {
            return false;
        }
        if (config('identity.bot_driver') === 'challenge') {
            // Local accessible fallback only. Public deployment requires a configured provider.
            return app()->environment(['local', 'testing']) && hash_equals($challenge['answer'], trim((string) $request->input('bot_answer')));
        }
        if (config('identity.bot_driver') !== 'turnstile' || ! config('identity.turnstile_secret_key')) {
            return false;
        }
        try {
            $result = Http::asForm()->timeout(8)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => config('identity.turnstile_secret_key'), 'response' => $request->input('cf-turnstile-response'), 'remoteip' => $request->ip(),
            ]);

            return $result->successful() && $result->json('success') === true && $result->json('action') === 'registration'
                && $result->json('hostname') === $request->getHost();
        } catch (Throwable) {
            return false;
        }
    }
}
