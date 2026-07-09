<?php

namespace App\Http\Requests;

use BYanelli\Roma\Request\Attributes\Rule;

readonly class CreateSubscriptionRequest
{
    public function __construct(
        #[Rule(['url:http,https', 'max:255'])]
        public string $url,

        #[Rule('max:255')]
        public string $name,
    ) {}
}
