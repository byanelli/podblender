<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Restricts registration to the addresses in config('auth.allowed_registration_emails').
 *
 * An empty allowlist means this deployment is open to anyone, so the rule lets
 * everything through. Otherwise both the allowlist and the submitted address
 * are trimmed and lowercased before they're compared, so casing and stray
 * whitespace never decide whether someone gets in.
 */
class AllowedRegistrationEmail implements ValidationRule
{
    /**
     * @param  Closure(string, string|null=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $allowed = $this->allowedEmails();

        if ($allowed === []) {
            return;
        }

        if (! is_string($value) || ! in_array($this->normalise($value), $allowed, true)) {
            $fail('Registration is limited to approved email addresses.');
        }
    }

    /**
     * @return array<int, string>
     */
    private function allowedEmails(): array
    {
        $configured = config('auth.allowed_registration_emails', []);

        if (! is_array($configured)) {
            return [];
        }

        $emails = [];

        foreach ($configured as $email) {
            if (! is_string($email)) {
                continue;
            }

            if (($normalised = $this->normalise($email)) !== '') {
                $emails[] = $normalised;
            }
        }

        return $emails;
    }

    private function normalise(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
