<?php

use App\Models\Feed;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('feeds.{feed}', function (User $user, Feed $feed) {
    return $feed->user_id === $user->id;
});
