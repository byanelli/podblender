<?php

namespace Tests\Events;

use App\Events\FinishedProcessingClip;
use App\Models\Feed;
use Illuminate\Broadcasting\PrivateChannel;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FinishedProcessingClipTest extends TestCase
{
    #[Test]
    public function it_broadcasts_on_the_feeds_private_channel()
    {
        $feed = Feed::factory()->create();

        $channels = (new FinishedProcessingClip($feed))->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertEquals("private-feeds.{$feed->id}", $channels[0]->name);
    }
}
