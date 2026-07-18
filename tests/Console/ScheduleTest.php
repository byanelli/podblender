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
        // Booting the console kernel is what loads routes/console.php, where the schedule is defined.
        $this->app->make(Kernel::class)->bootstrap();

        $event = collect($this->app->make(Schedule::class)->events())
            ->first(fn ($event) => $event->description === UpdateAllSubscriptions::class);

        // Without this entry nothing ever dispatches UpdateAllSubscriptions, so a subscription is filled once when it's
        // created and never updated again.
        $this->assertNotNull($event, 'UpdateAllSubscriptions is not scheduled.');

        // Every two hours, on the hour: cron 0 */2 * * *.
        $this->assertEquals('0 */2 * * *', $event->expression);
    }
}
