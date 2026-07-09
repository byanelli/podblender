<?php

namespace App\Http\Requests;

use BYanelli\Roma\Request\Attributes\Rule;

readonly class CreateCustomFeedRequest
{
    public function __construct(
        #[Rule('max:255')]
        public string $name,
    ) {}
}
