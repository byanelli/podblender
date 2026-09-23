<?php

namespace App\Http\Controllers;

use App\Auth\Access\Gate;
use App\Models\Feed;
use Illuminate\Auth\Access\AuthorizationException;

readonly class RegenerateInboundEmailAddress
{
    /**
     * @throws AuthorizationException
     */
    public function __invoke(Gate $gate, Feed $feed): void
    {
        $gate->authorizeUpdate($feed);

        // Clips arrive in a subscription feed only from its source.
        abort_if($feed->subscription_id !== null, 422, 'Only a custom feed has an email address.');

        $feed->regenerateInboundEmailToken();
    }
}
