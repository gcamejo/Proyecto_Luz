<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class RegistrationCreatedNotification extends Notification
{
    private $nombre;
    private $email;
    private $fecha;
    private $registroId;

    public function __construct(string $nombre, string $email, string $fecha, $registroId = null)
    {
        $this->nombre = $nombre;
        $this->email = $email;
        $this->fecha = $fecha;
        $this->registroId = $registroId;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        $data = [
            'nombre' => $this->nombre,
            'email' => $this->email,
            'fecha' => $this->fecha,
        ];

        if ($this->registroId !== null) {
            $data['registro_id'] = $this->registroId;
        }

        return $data;
    }
}
