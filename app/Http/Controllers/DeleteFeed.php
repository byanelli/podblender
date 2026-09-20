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

        // A cover belongs to one feed. It is deleted after the row, so a
        // failed delete can't leave a feed whose cover file is missing.
        if ($coverPath !== null) {
            $storage->delete($coverPath);
        }
    }
}
