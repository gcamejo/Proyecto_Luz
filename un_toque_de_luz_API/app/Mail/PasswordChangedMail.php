<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordChangedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $userName;
    public $changedAt;

    public function __construct(string $userName, string $changedAt)
    {
        $this->userName = $userName;
        $this->changedAt = $changedAt;
    }

    public function build()
    {
        return $this->subject('Aviso de cambio de contraseña')
            ->text('emails.password_changed');
    }
}
