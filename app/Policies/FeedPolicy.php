<?php

namespace App\Policies;

use App\Models\Feed;
use App\Models\User;

class FeedPolicy
{
    public function view(User $user, Feed $feed): bool
    {
        return $user->id === $feed->user_id;
    }

    public function update(User $user, Feed $feed): bool
    {
        return $user->id === $feed->user_id;
    }
}
