<?php

namespace App\Apis\Resend\Contracts;

use App\Apis\Resend\ReceivedEmail;

interface Client
{
    /**
     * Fetch the content of an email that Resend received. Resend's webhook contains only the metadata.
     */
    public function receivedEmail(string $emailId): ReceivedEmail;
}
