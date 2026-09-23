<?php

namespace App\Apis\Resend;

use App\Apis\Resend\Contracts\Client as ClientContract;
use Resend;

readonly class Client implements ClientContract
{
    public function __construct(private ClientConfig $config) {}

    public function receivedEmail(string $emailId): ReceivedEmail
    {
        $email = Resend::client($this->config->apiKey)->emails->receiving->get($emailId);

        return new ReceivedEmail(
            subject: $email->subject,
            text: $email->text,
            html: $email->html,
        );
    }
}
