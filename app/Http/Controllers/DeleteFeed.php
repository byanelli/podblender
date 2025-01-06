<?php

namespace App\Http\Controllers;

use App\Auth\Access\Gate;
use App\Models\Feed;
use Illuminate\Auth\Access\AuthorizationException;

class DeleteFeed
{
    /**
     * @throws AuthorizationException
     */
    public function __invoke(Gate $gate, Feed $feed): void
    {
        $gate->authorizeDelete($feed);

        $feed->delete();
    }
}
