<?php

namespace App\Http\Requests\Resend;

use BYanelli\Roma\Request\Attributes\Accessors\Content;
use BYanelli\Roma\Request\Attributes\Header;
use BYanelli\Roma\Request\ContextualBinding\Request;

/**
 * A Resend webhook event. Resend signs its webhooks with Svix's scheme.
 */
#[Request]
readonly class WebhookRequest
{
    public function __construct(
        // The signature is computed over the body as sent.
        #[Content]
        public string $payload,

        #[Header('svix-id')]
        public string $svixId,

        #[Header('svix-timestamp')]
        public string $svixTimestamp,

        #[Header('svix-signature')]
        public string $svixSignature,

        public string $type,

        public EmailEventData $data,
    ) {}

    /**
     * @return array{svix-id: string, svix-timestamp: string, svix-signature: string}
     */
    public function signatureHeaders(): array
    {
        return [
            'svix-id'        => $this->svixId,
            'svix-timestamp' => $this->svixTimestamp,
            'svix-signature' => $this->svixSignature,
        ];
    }
}
