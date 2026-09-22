<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class S2PPassword implements ValidationRule
{
    public function validate(
        string $attribute,
        mixed $value,
        Closure $fail
    ): void {
        if (! is_string($value)) {
            $fail('Kata laluan mestilah teks.');

            return;
        }

        if (mb_strlen($value, 'UTF-8') < 12) {
            $fail('Kata laluan mesti mengandungi sekurang-kurangnya 12 aksara.');

            return;
        }

        if (strlen($value) > 72) {
            $fail('Kata laluan terlalu panjang. Had maksimum ialah 72 bait.');

            return;
        }

        if (
            ! preg_match('/[A-Z]/', $value) ||
            ! preg_match('/[a-z]/', $value) ||
            ! preg_match('/[0-9]/', $value) ||
            ! preg_match('/[\p{P}\p{S}]/u', $value)
        ) {
            $fail(
                'Kata laluan mesti mempunyai huruf besar A-Z, huruf kecil a-z, nombor dan simbol.'
            );
        }
    }
}
