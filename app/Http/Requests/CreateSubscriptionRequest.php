<?php

namespace App\Http\Requests;

use BYanelli\Roma\Request\Attributes\Rule;
use BYanelli\Roma\Request\ContextualBinding\Request;
use Carbon\CarbonImmutable;

#[Request]
readonly class CreateSubscriptionRequest
{
    public function __construct(
        #[Rule(['url:http,https', 'max:255'])]
        public string $url,

        #[Rule('max:255')]
        public string $name,

        /**
         * How far back to reach for episodes. Absent means the default window;
         * a date at the epoch means everything the source has ever published.
         */
        #[Rule(['nullable', 'date'])]
        public ?CarbonImmutable $backfillSince = null,

        /**
         * Whether to keep collecting episodes published from here on. False
         * captures the source as it stands and then leaves it alone.
         */
        #[Rule(['boolean'])]
        public bool $tracksNewEpisodes = true,
    ) {}
}
