<?php

namespace App\Services\Concerns;

use App\Models\Yoguini;
use Illuminate\Validation\ValidationException;

trait LocksStudent
{
    // La fila del alumno funciona como mutex de todas las operaciones de pagos.
    protected function lockStudent($userId)
    {
        return Yoguini::whereKey($userId)->lockForUpdate()->firstOrFail();
    }

    protected function fail($key, $message)
    {
        throw ValidationException::withMessages([$key => [$message]]);
    }
}
