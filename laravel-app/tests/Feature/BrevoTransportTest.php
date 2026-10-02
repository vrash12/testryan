<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Tests\TestCase;

class BrevoTransportTest extends TestCase
{
    public function test_brevo_api_sends_with_configured_sender_and_key(): void
    {
        $this->app->instance('env', 'staging');
        config(['mail.default' => 'brevo', 'services.brevo.key' => 'synthetic-api-key', 'mail.from.address' => 'sender@example.test']);
        $requests = 0;
        $this->app->instance('brevo.http', new MockHttpClient(function ($method, $url, $options) use (&$requests) {
            $requests++;
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.brevo.com/v3/smtp/email', $url);
            $this->assertContains('api-key: synthetic-api-key', $options['headers']);
            $payload = json_decode($options['body'], true);
            $this->assertSame('sender@example.test', $payload['sender']['email']);
            $this->assertSame('inbox@example.test', $payload['to'][0]['email']);
            $this->assertSame('Synthetic API check', $payload['subject']);

            return new MockResponse('{"messageId":"synthetic-message-id"}', ['http_code' => 201]);
        }));
        $sent = Mail::raw('Synthetic content', fn ($message) => $message->to('inbox@example.test')->subject('Synthetic API check'));
        $this->assertSame('synthetic-message-id', $sent->getMessageId());
        $this->assertSame(1, $requests);
    }

    public function test_provider_rejection_is_not_reported_as_success(): void
    {
        $this->app->instance('env', 'staging');
        config(['mail.default' => 'brevo', 'services.brevo.key' => 'synthetic-api-key']);
        $this->app->instance('brevo.http', new MockHttpClient(new MockResponse('{"message":"quota exceeded"}', ['http_code' => 429])));
        $this->expectException(TransportExceptionInterface::class);
        Mail::raw('Synthetic', fn ($message) => $message->to('inbox@example.test')->subject('Synthetic'));
    }

    public function test_local_and_testing_refuse_brevo_even_with_a_key(): void
    {
        foreach (['local', 'testing'] as $environment) {
            $this->app->instance('env', $environment);
            config(['services.brevo.key' => 'synthetic-api-key']);
            try {
                Mail::mailer('brevo');
                $this->fail('External mail must be disabled.');
            } catch (\RuntimeException $e) {
                $this->assertSame('Brevo is disabled in local/testing.', $e->getMessage());
            }
        }
    }

    public function test_missing_key_fails_without_revealing_credentials(): void
    {
        $this->app->instance('env', 'staging');
        config(['services.brevo.key' => '']);
        $this->expectExceptionMessage('BREVO_API_KEY is not configured.');
        Mail::mailer('brevo');
    }

    public function test_readiness_check_never_sends_without_explicit_recipient(): void
    {
        Mail::fake();
        $this->artisan('nestcrafters:brevo-check')->assertFailed();
        $this->app->instance('env', 'staging');
        config(['mail.default' => 'brevo', 'services.brevo.key' => 'synthetic-api-key', 'mail.from.address' => 'sender@example.com', 'app.url' => 'https://portal.example.com']);
        $this->artisan('nestcrafters:brevo-check')->assertSuccessful();
        Mail::assertNothingSent();
        Mail::assertNothingOutgoing();
    }
}
