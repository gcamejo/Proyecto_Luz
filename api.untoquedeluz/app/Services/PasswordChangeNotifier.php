<?php

namespace App\Services;

class PasswordChangeNotifier
{
    public function send(string $recipient, string $userName, string $changedAt, string $idempotencyKey)
    {
        $text = "Hola {$userName},\n\n"
            ."Se cambió la contraseña de tu cuenta de Un toque de Luz el {$changedAt}.\n\n"
            .'Si no realizaste este cambio, contactá al equipo de Un toque de Luz cuanto antes.\n\n'
            .'Por seguridad, este correo no contiene tu contraseña.';

        return app(ResendApiMailer::class)->send(
            $recipient,
            'Aviso de cambio de contraseña',
            $text,
            $idempotencyKey,
            'password_change'
        );
    }
}
