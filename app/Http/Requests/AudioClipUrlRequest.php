<?php

namespace App\Http\Requests;

use BYanelli\Roma\Request\Attributes\Rule;
use BYanelli\Roma\Request\ContextualBinding\Request;

#[Request]
readonly class AudioClipUrlRequest
{
    public function __construct(
        #[Rule(['url:http,https', 'max:255'])]
        public string $url,
    ) {}
}
