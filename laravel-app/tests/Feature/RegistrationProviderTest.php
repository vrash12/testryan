<?php

namespace Tests\Feature;

use App\Actions\Identity\RegistrationBotCheck;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RegistrationProviderTest extends TestCase
{
    public function test_turnstile_checks_success_hostname_action_and_provider_failure_without_live_requests(): void
    {
        config(['identity.bot_driver' => 'turnstile', 'identity.turnstile_secret_key' => 'synthetic-test-secret']);
        Http::preventStrayRequests();
        $bot = app(RegistrationBotCheck::class);
        $results = [['success' => true, 'hostname' => 'localhost', 'action' => 'registration'], ['success' => true, 'hostname' => 'attacker.invalid', 'action' => 'registration'], ['success' => true, 'hostname' => 'localhost', 'action' => 'login'], ['success' => false]];
        $responses = Http::fakeSequence('challenges.cloudflare.com/*');
        foreach ($results as $result) {
            $responses->push($result);
        }
        foreach ($results as $index => $result) {
            $request = Request::create('http://localhost/register', 'POST', ['bot_answer' => '1', 'cf-turnstile-response' => 'synthetic-token']);
            $request->setLaravelSession(app('session')->driver());
            $challenge = $bot->issue($request);
            $request->merge(['bot_token' => $challenge['token']]);
            $sessionChallenge = $request->session()->get('registration_challenge');
            $sessionChallenge['issued_at'] -= 5;
            $request->session()->put('registration_challenge', $sessionChallenge);
            $this->assertSame($index === 0, $bot->passes($request));
            $this->assertFalse($bot->passes($request)); // Token consumed; local replay never contacts the provider.
        }
        Http::assertSentCount(count($results));
    }
}
