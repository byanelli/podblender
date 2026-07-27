<?php

namespace App\Jobs;

use App\Models\AudioSource;
use App\Models\Feed;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateAllSubscriptions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(Dispatcher $dispatcher): void
    {
        AudioSource::query()
            // Only sources somebody still wants updates from. A source whose
            // subscribers have all had their one fill is finished: checking it
            // every couple of hours would spend platform quota forever on feeds
            // that asked to be left alone.
            ->whereHas(AudioSource::REL_SUBSCRIBERS, Feed::scopeNeedingUpdates(...))
            ->each(function (AudioSource $subscription) use ($dispatcher) {
                $dispatcher->dispatch(new UpdateSubscription($subscription));
            });
    }
}
