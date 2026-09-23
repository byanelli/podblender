<?php

namespace App\Apis\Resend;

readonly class ReceivedEmail
{
    public function __construct(
        public ?string $subject,
        public ?string $text,
        public ?string $html,
    ) {}
}
