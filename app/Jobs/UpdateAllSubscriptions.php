<?php

namespace App\Jobs;

use App\Models\AudioSource;
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
            ->whereHas(AudioSource::REL_SUBSCRIBERS)
            ->each(function (AudioSource $subscription) use ($dispatcher) {
                $dispatcher->dispatch(new UpdateSubscription($subscription));
            });
    }
}
