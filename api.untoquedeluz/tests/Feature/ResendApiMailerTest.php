<?php

namespace Tests\Feature;

use App\Services\ResendApiMailer;
use App\Support\ResendSslPolicy;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ResendApiMailerTest extends TestCase
{
    public function test_resend_ssl_verification_can_only_be_disabled_in_local_environment(): void
    {
        config(['app.env' => 'local']);
        $this->assertFalse(ResendSslPolicy::shouldVerify((string) config('app.env'), false));

        foreach (['testing', 'staging', 'production'] as $environment) {
            config(['app.env' => $environment]);
            $this->assertTrue(ResendSslPolicy::shouldVerify((string) config('app.env'), false));
        }

        $this->assertTrue(ResendSslPolicy::shouldVerify('local', true));
    }

    public function test_sends_email_to_resend_http_api_with_idempotency_key()
    {
        config([
            'services.resend.api_key' => 'test-resend-key',
            'services.resend.from' => 'alerts@example.test',
        ]);
        Http::fake([
            'https://api.resend.com/emails' => Http::response(['id' => 'email_test_123'], 200),
        ]);

        $sent = app(ResendApiMailer::class)->send(
            'admin@example.test',
            'Nuevo registro',
            'Nombre: Alumna\nEmail: alumna@example.test\nFecha: 2026-10-13',
            'registration-42',
            'registration'
        );

        $this->assertTrue($sent);
        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.resend.com/emails'
                && $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'Bearer test-resend-key')
                && $request->hasHeader('Idempotency-Key', 'registration-42')
                && $request->data()['to'] === ['admin@example.test']
                && $request->data()['subject'] === 'Nuevo registro'
                && strpos($request->data()['text'], 'alumna@example.test') !== false;
        });
    }

    public function test_does_not_send_when_api_key_or_sender_is_missing()
    {
        config([
            'services.resend.api_key' => '',
            'services.resend.from' => '',
        ]);
        Http::fake();

        $sent = app(ResendApiMailer::class)->send(
            'admin@example.test',
            'Nuevo registro',
            'Registro',
            'registration-43',
            'registration'
        );

        $this->assertFalse($sent);
        Http::assertNothingSent();
    }

    public function test_returns_false_for_resend_http_errors()
    {
        config([
            'services.resend.api_key' => 'test-resend-key',
            'services.resend.from' => 'alerts@example.test',
        ]);
        Http::fake([
            'https://api.resend.com/emails' => Http::response(['name' => 'validation_error'], 422),
        ]);

        $sent = app(ResendApiMailer::class)->send(
            'admin@example.test',
            'Nuevo registro',
            'Registro',
            'registration-44',
            'registration'
        );

        $this->assertFalse($sent);
        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.resend.com/emails';
        });
    }

    public function test_retries_connection_failures_only_a_bounded_number_of_times()
    {
        config([
            'services.resend.api_key' => 'test-resend-key',
            'services.resend.from' => 'alerts@example.test',
        ]);
        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            $attempts++;
            throw new ConnectionException('temporary connection failure');
        });

        $sent = app(ResendApiMailer::class)->send(
            'admin@example.test',
            'Nuevo registro',
            'Registro',
            'registration-45',
            'registration'
        );

        $this->assertFalse($sent);
        $this->assertSame(2, $attempts);
    }
}
