<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ResendApiMailer
{
    private const ENDPOINT = 'https://api.resend.com/emails';
    private const TIMEOUT_SECONDS = 5;

    public function send(string $recipient, string $subject, string $text, string $idempotencyKey, string $purpose)
    {
        $apiKey = trim((string) config('services.resend.api_key'));
        $from = trim((string) config('services.resend.from'));

        if ($apiKey === '' || $from === '' || trim($recipient) === '') {
            Log::warning('Resend email skipped because configuration is incomplete.', [
                'purpose' => $purpose,
            ]);
            return false;
        }

        try {
            $response = Http::acceptJson()
                ->withToken($apiKey)
                ->withHeaders(['Idempotency-Key' => substr($idempotencyKey, 0, 256)])
                ->timeout(self::TIMEOUT_SECONDS)
                ->retry(2, 250, function ($exception) {
                    return $exception instanceof ConnectionException;
                })
                ->post(self::ENDPOINT, [
                    'from' => $from,
                    'to' => [$recipient],
                    'subject' => $subject,
                    'text' => $text,
                ]);

            if ($response->successful()) {
                return true;
            }

            $errorType = $response->json('name');
            if (!is_string($errorType) || !preg_match('/^[A-Za-z0-9_.-]{1,64}$/', $errorType)) {
                $errorType = 'http_error';
            }

            Log::warning('Resend HTTP email request failed.', [
                'purpose' => $purpose,
                'status' => $response->status(),
                'error_type' => $errorType,
            ]);

            return false;
        } catch (Throwable $exception) {
            Log::warning('Resend HTTP email transport failed.', [
                'purpose' => $purpose,
                'exception_type' => get_class($exception),
            ]);

            return false;
        }
    }
}
