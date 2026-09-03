<?php

namespace Tests\Rules;

use App\Rules\AllowedRegistrationEmail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AllowedRegistrationEmailTest extends TestCase
{
    #[Test]
    public function it_passes_everything_when_the_allowlist_is_empty()
    {
        config()->set('auth.allowed_registration_emails', []);

        $this->assertNull($this->failureFor('stranger@example.com'));
    }

    #[Test]
    public function it_passes_an_address_on_the_allowlist()
    {
        config()->set('auth.allowed_registration_emails', ['allowed@example.com', 'other@example.com']);

        $this->assertNull($this->failureFor('allowed@example.com'));
    }

    #[Test]
    public function it_ignores_casing_and_whitespace_on_both_sides()
    {
        config()->set('auth.allowed_registration_emails', ['  Allowed@Example.COM ']);

        $this->assertNull($this->failureFor(' allowed@example.com'));
        $this->assertNull($this->failureFor('ALLOWED@EXAMPLE.COM'));
    }

    #[Test]
    public function it_fails_an_address_that_is_not_on_the_allowlist()
    {
        config()->set('auth.allowed_registration_emails', ['allowed@example.com']);

        $this->assertSame(
            'Registration is limited to approved email addresses.',
            $this->failureFor('stranger@example.com')
        );
    }

    #[Test]
    public function it_fails_a_value_that_is_not_a_string()
    {
        config()->set('auth.allowed_registration_emails', ['allowed@example.com']);

        $this->assertNotNull($this->failureFor(['allowed@example.com']));
    }

    #[Test]
    public function the_config_file_parses_the_environment_variable_into_a_clean_list()
    {
        $this->assertSame([], $this->parseConfiguredList(''));
        $this->assertSame([], $this->parseConfiguredList('  ,  ,'));
        $this->assertSame(
            ['first@example.com', 'second@example.com'],
            $this->parseConfiguredList(' First@Example.com ,second@example.com, ')
        );
    }

    /**
     * Re-evaluates config/auth.php with ALLOWED_REGISTRATION_EMAILS set to
     * $value, so the parsing that lives in the config file itself is covered.
     *
     * @return array<int, string>
     */
    private function parseConfiguredList(string $value): array
    {
        $original = $_ENV['ALLOWED_REGISTRATION_EMAILS'] ?? null;
        $_ENV['ALLOWED_REGISTRATION_EMAILS'] = $value;

        try {
            /** @var array{allowed_registration_emails: array<int, string>} $parsed */
            $parsed = require config_path('auth.php');

            return $parsed['allowed_registration_emails'];
        } finally {
            if ($original === null) {
                unset($_ENV['ALLOWED_REGISTRATION_EMAILS']);
            } else {
                $_ENV['ALLOWED_REGISTRATION_EMAILS'] = $original;
            }
        }
    }

    /**
     * Runs the rule and returns the failure message, or null when it passed.
     */
    private function failureFor(mixed $value): ?string
    {
        $message = null;

        (new AllowedRegistrationEmail)->validate(
            'email',
            $value,
            function (string $failure) use (&$message) {
                $message = $failure;
            }
        );

        return $message;
    }
}
