<?php

namespace App\Services;

use App\Mail\PasswordChangedMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class PasswordChangeNotifier
{
    public function send(string $recipient, string $userName, string $changedAt)
    {
        try {
            Mail::to($recipient)->send(new PasswordChangedMail($userName, $changedAt));
        } catch (Throwable $exception) {
            Log::warning('Password change email failed.', [
                'exception_type' => get_class($exception),
            ]);
        }
    }
}
