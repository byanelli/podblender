<?php

namespace App\Http\Controllers;

use App\Auth\Access\Gate;
use App\Models\Feed;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Filesystem\Filesystem;

class DeleteFeed
{
    /**
     * @throws AuthorizationException
     */
    public function __invoke(Gate $gate, Filesystem $storage, Feed $feed): void
    {
        $gate->authorizeDelete($feed);

        $coverPath = $feed->cover_path;

        $feed->delete();

        // A cover belongs to one feed and nothing else points at it, so it goes
        // when the feed does. Deleted after the row rather than before, so a
        // delete that fails part way through can't leave a feed on the
        // dashboard whose picture is already gone.
        if ($coverPath !== null) {
            $storage->delete($coverPath);
        }
    }
}
