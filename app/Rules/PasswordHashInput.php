<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PasswordHashInput implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (config('hashing.driver', 'bcrypt') === 'bcrypt' && is_string($value)) {
            if (strlen($value) > 72) {
                $fail('The password must not exceed 72 bytes with the current password hashing configuration.');
            } elseif (str_contains($value, "\0")) {
                $fail('The password must not contain null characters.');
            }
        }
    }
}
