<?php

namespace App\Http\Requests\Payments\Concerns;

trait NormalizesMoneyInput
{
    // Un número JSON llega como int/float: se pasa a texto antes de validar los 2 decimales.
    protected function normalizeMoney(array $fields)
    {
        $normalized = [];
        foreach ($fields as $field) {
            $value = $this->input($field);
            if (is_int($value)) {
                $normalized[$field] = (string) $value;
            } elseif (is_float($value)) {
                $normalized[$field] = rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
            }
        }
        if ($normalized) {
            $this->merge($normalized);
        }
    }
}
