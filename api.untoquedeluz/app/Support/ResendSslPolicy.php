<?php

namespace App\Support;

class ResendSslPolicy
{
    public static function shouldVerify(string $environment, bool $configured): bool
    {
        return $configured || $environment !== 'local';
    }
}