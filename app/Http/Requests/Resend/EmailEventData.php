<?php

namespace App\Http\Requests\Resend;

use BYanelli\Roma\Request\Attributes\Key;

/**
 * The data of a Resend email event. Only email_id is required, so that event types other than email.received are
 * accepted and ignored instead of rejected, which would make Resend retry them.
 */
readonly class EmailEventData
{
    /**
     * @param  list<string>  $to
     * @param  list<string>  $cc
     * @param  list<string>  $receivedFor
     */
    public function __construct(
        #[Key('email_id')]
        public string $emailId,

        public ?string $from = null,

        public ?string $subject = null,

        public array $to = [],

        public array $cc = [],

        #[Key('received_for')]
        public array $receivedFor = [],
    ) {}
}
