<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Cnpj implements ValidationRule
{
    public static function normalize(string $value): ?string
    {
        $value = strtoupper($value);
        if (! preg_match('/^(?:[0-9A-Z]{12}[0-9]{2}|[0-9A-Z]{2}\.[0-9A-Z]{3}\.[0-9A-Z]{3}\/[0-9A-Z]{4}-[0-9]{2})$/D', $value)) {
            return null;
        }

        $digits = str_replace(['.', '/', '-'], '', $value);
        if (preg_match('/^([0-9])\1{13}$/D', $digits)) {
            return null;
        }

        foreach ([12, 13] as $length) {
            $sum = 0;
            $weight = $length - 7;
            for ($i = 0; $i < $length; $i++) {
                $sum += (ord($digits[$i]) - 48) * $weight;
                $weight = $weight === 2 ? 9 : $weight - 1;
            }
            $remainder = $sum % 11;
            if ((int) $digits[$length] !== ($remainder < 2 ? 0 : 11 - $remainder)) {
                return null;
            }
        }

        return $digits;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || self::normalize($value) === null) {
            $fail('Informe um CNPJ válido, com 14 caracteres.');
        }
    }
}
