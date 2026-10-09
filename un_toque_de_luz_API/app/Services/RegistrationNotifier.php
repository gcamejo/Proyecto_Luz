<?php

namespace App\Services;

use App\Models\Yoguini;
use App\Notifications\RegistrationCreatedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Throwable;

class RegistrationNotifier
{
    public function notifyAdmins(Yoguini $student, string $registeredAt)
    {
        $name = trim($student->nombre.' '.$student->apellido);
        $notification = new RegistrationCreatedNotification($name, $student->email, $registeredAt, $student->getKey());

        try {
            $admins = Yoguini::where('perfil', 'Admin')->get();
        } catch (Throwable $exception) {
            Log::warning('Admin registration recipients lookup failed.', [
                'exception_type' => get_class($exception),
            ]);
            $admins = collect();
        }

        $alreadyNotified = false;
        $admins->each(function ($admin) use ($notification, $student, &$alreadyNotified) {
            try {
                $result = DB::transaction(function () use ($admin, $notification, $student) {
                    $lockedAdmin = Yoguini::whereKey($admin->getKey())->lockForUpdate()->first();
                    if (!$lockedAdmin) {
                        return 'missing';
                    }

                    $alreadyNotified = $lockedAdmin->notifications()
                        ->where('type', RegistrationCreatedNotification::class)
                        ->where('data->registro_id', $student->getKey())
                        ->exists();

                    if ($alreadyNotified) {
                        return 'duplicate';
                    }

                    Notification::send($lockedAdmin, $notification);
                    return 'sent';
                });

                if ($result === 'duplicate') {
                    $alreadyNotified = true;
                }
            } catch (Throwable $exception) {
                Log::warning('Admin registration notification failed.', [
                    'exception_type' => get_class($exception),
                ]);
            }
        });

        $recipient = trim((string) config('mail.admin_notification_email'));
        if ($recipient === '') {
            return;
        }

        if ($alreadyNotified) {
            return;
        }

        $body = "Nombre: {$name}\nEmail: {$student->email}\nFecha: {$registeredAt}";

        try {
            Mail::raw($body, function ($message) use ($recipient) {
                $message->to($recipient)->subject('Nuevo registro de alumno');
            });
        } catch (Throwable $exception) {
            Log::warning('Admin registration email failed.', [
                'exception_type' => get_class($exception),
            ]);
        }
    }
}
