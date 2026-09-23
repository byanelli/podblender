<?php

namespace App\Events;

use App\Models\Feed;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * An email to the feed's inbound address arrived or finished processing. The feed page reloads its list.
 */
class InboundEmailUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(private readonly Feed $feed) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("feeds.{$this->feed->id}"),
        ];
    }
}
