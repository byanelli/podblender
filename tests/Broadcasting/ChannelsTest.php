<?php

namespace Tests\Broadcasting;

use App\Models\Feed;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChannelsTest extends TestCase
{
    private function authorize(User $user, Feed $feed)
    {
        return $this->actingAs($user)->postJson('/broadcasting/auth', [
            'socket_id'    => '1234.5678',
            'channel_name' => "private-feeds.{$feed->id}",
        ]);
    }

    #[Test]
    public function the_owner_is_authorized_on_their_private_feed_channel()
    {
        $owner = User::factory()->create();
        $feed = Feed::factory()->create(['user_id' => $owner->id]);

        $this->authorize($owner, $feed)->assertOk();
    }

    #[Test]
    public function a_non_owner_is_rejected_from_the_private_feed_channel()
    {
        $this->withExceptionHandling();

        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $feed = Feed::factory()->create(['user_id' => $owner->id]);

        $this->authorize($stranger, $feed)->assertForbidden();
    }
}
