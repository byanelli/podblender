<?php

namespace Tests\Concerns;

use App\Apis\Resend\Contracts\Client;
use App\Apis\Resend\ReceivedEmail;
use Tests\TestCase;

/**
 * @mixin TestCase
 */
trait FakesResend
{
    protected function fakeResend(?ReceivedEmail $receivedEmail = null, ?\Throwable $error = null): void
    {
        $this->app->instance(Client::class, new readonly class($receivedEmail, $error) implements Client
        {
            public function __construct(private ?ReceivedEmail $receivedEmail, private ?\Throwable $error) {}

            public function receivedEmail(string $emailId): ReceivedEmail
            {
                if ($this->error !== null) {
                    throw $this->error;
                }

                return $this->receivedEmail ?? new ReceivedEmail(subject: null, text: null, html: null);
            }
        });
    }
}
