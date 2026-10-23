<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class PrivacyDeletionRequestedNotification extends Notification
{
    public function __construct(private int $userId, private string $name, private string $email)
    {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'tipo' => 'solicitud_eliminacion',
            'registro_id' => $this->userId,
            'nombre' => $this->name,
            'email' => $this->email,
            'fecha' => now(config('app.timezone'))->toIso8601String(),
        ];
    }
}