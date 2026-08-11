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
            // Only update sources with at least one subscription that wants
            // to be updated.
            ->whereHas('subscribers', Feed::scopeNeedingUpdates(...))
            ->each(function (AudioSource $subscription) use ($dispatcher) {
                $dispatcher->dispatch(new UpdateSubscription($subscription));
            });
    }
}
