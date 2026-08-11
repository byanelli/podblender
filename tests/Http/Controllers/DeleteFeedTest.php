<?php

namespace Tests\Http\Controllers;

use App\Models\Feed;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DeleteFeedTest extends TestCase
{
    #[Test]
    public function it_deletes_the_owners_feed()
    {
        $user = User::factory()->create();
        $feed = Feed::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->delete("/feeds/{$feed->id}");

        $this->assertModelMissing($feed);
    }

    #[Test]
    public function it_does_not_let_another_user_delete_a_feed()
    {
        $this->expectException(AuthorizationException::class);

        $user = User::factory()->create();
        $feed = Feed::factory()->create(['user_id' => $user->id + 1]);

        $this->actingAs($user)->delete("/feeds/{$feed->id}");
    }

    #[Test]
    public function it_does_not_let_a_guest_delete_a_feed()
    {
        $this->expectException(AuthenticationException::class);

        $feed = Feed::factory()->create();

        $this->delete("/feeds/{$feed->id}");
    }
}
