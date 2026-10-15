<?php

namespace App\Rules;

use App\Support\Money;
use Illuminate\Contracts\Validation\Rule;

class MoneyAmount implements Rule
{
    private $allowZero;

    public function __construct($allowZero = false)
    {
        $this->allowZero = $allowZero;
    }

    public function passes($attribute, $value)
    {
        if (!is_string($value) || !preg_match('/^\d{1,10}(\.\d{1,2})?$/', $value)) {
            return false;
        }

        return $this->allowZero || Money::cents($value) > 0;
    }

    public function message()
    {
        return $this->allowZero
            ? 'The :attribute must be an amount with at most 2 decimals.'
            : 'The :attribute must be a positive amount with at most 2 decimals.';
    }
}
