<?php

namespace App\Policies;

use App\Models\Feed;
use App\Models\User;

class FeedPolicy
{
    private function feedBelongsToUser(User $user, Feed $feed): bool
    {
        return $user->id === $feed->user_id;
    }

    public function view(User $user, Feed $feed): bool
    {
        return $this->feedBelongsToUser($user, $feed);
    }

    public function update(User $user, Feed $feed): bool
    {
        return $this->feedBelongsToUser($user, $feed);
    }

    public function delete(User $user, Feed $feed): bool
    {
        return $this->feedBelongsToUser($user, $feed);
    }
}
