<?php

namespace App\Support;

use InvalidArgumentException;

class Money
{
    public static function cents($value)
    {
        if ($value === null || $value === '') {
            return 0;
        }
        if (is_float($value)) {
            $value = number_format($value, 2, '.', '');
        }

        $value = trim((string) $value);
        if (!preg_match('/^(-)?(\d+)(?:\.(\d+))?$/', $value, $parts)) {
            throw new InvalidArgumentException('Invalid monetary amount.');
        }

        $fraction = $parts[3] ?? '';
        $cents = ((int) $parts[2]) * 100 + (int) str_pad(substr($fraction, 0, 2), 2, '0');
        if (strlen($fraction) > 2 && (int) $fraction[2] >= 5) {
            $cents++;
        }

        return $parts[1] === '-' ? -$cents : $cents;
    }

    public static function decimal($cents)
    {
        $cents = (int) $cents;
        $absolute = abs($cents);

        return sprintf('%s%d.%02d', $cents < 0 ? '-' : '', intdiv($absolute, 100), $absolute % 100);
    }

    public static function sum($values)
    {
        $total = 0;
        foreach ($values as $value) {
            $total += self::cents($value);
        }

        return $total;
    }
}
