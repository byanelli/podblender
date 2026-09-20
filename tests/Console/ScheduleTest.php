<?php

namespace Tests\Console;

use App\Jobs\UpdateAllSubscriptions;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    #[Test]
    public function it_refreshes_every_subscription_every_two_hours(): void
    {
        // Bootstrapping the console kernel loads routes/console.php, where the schedule is defined.
        $this->app->make(Kernel::class)->bootstrap();

        $event = collect($this->app->make(Schedule::class)->events())
            ->first(fn ($event) => $event->description === UpdateAllSubscriptions::class);

        // Nothing else dispatches UpdateAllSubscriptions. Without it, a subscription is filled once and never updated.
        $this->assertNotNull($event, 'UpdateAllSubscriptions is not scheduled.');

        $this->assertEquals('0 */2 * * *', $event->expression);
    }
}
