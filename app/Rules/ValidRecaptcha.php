<?php

namespace App\Rules;

use App\Support\Security\Recaptcha;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Request;

class ValidRecaptcha implements ValidationRule
{
    /**
     * @param  Closure(string, ?string=): void  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! Recaptcha::verify(is_string($value) ? $value : null, Request::ip())) {
            $fail('backoffice.auth.captcha_failed')->translate();
        }
    }
}
