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
         * Earliest publication date to backfill from. Null means the default
         * window; the epoch means everything the source has published.
         */
        #[Rule(['nullable', 'date'])]
        public ?CarbonImmutable $backfillSince = null,

        /**
         * Whether to keep adding episodes published after the subscription is
         * created. If false, the feed is filled once and not updated again.
         */
        #[Rule(['boolean'])]
        public bool $tracksNewEpisodes = true,
    ) {}
}
