<?php

namespace Tests\Actions;

use App\Actions\GenerateFeedCover;
use App\Models\Feed;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\FakesCoverGenerator;
use Tests\TestCase;

class GenerateFeedCoverTest extends TestCase
{
    use FakesCoverGenerator;

    #[Test]
    public function it_stores_a_cover_and_points_the_feed_at_it()
    {
        $storage = Storage::fake();
        $this->fakeCoverGenerator();

        $feed = $this->feed('Long Reads for the Commute');

        $this->generate($feed);

        $this->assertNotNull($feed->cover_path);
        $storage->assertExists($feed->cover_path);
        $this->assertStringStartsWith('covers/long-reads-for-the-commute-', $feed->cover_path);
        $this->assertStringEndsWith('.jpg', $feed->cover_path);
        $this->assertDatabaseHas('feeds', ['id' => $feed->id, 'cover_path' => $feed->cover_path]);
    }

    #[Test]
    public function it_deletes_the_temporary_file_it_was_given()
    {
        Storage::fake();
        $this->fakeCoverGenerator();

        // The fake generator writes to the system temp directory, as the real
        // one does, so a file left behind appears in this listing.
        $before = glob(sys_get_temp_dir().'/*.jpg') ?: [];

        $this->generate($this->feed('Lectures'));

        $this->assertSame($before, glob(sys_get_temp_dir().'/*.jpg') ?: []);
    }

    #[Test]
    public function regenerating_gives_a_new_path_and_removes_the_old_file()
    {
        // Podcast apps cache artwork by URL, so a redrawn cover needs a new
        // path.
        $storage = Storage::fake();
        $this->fakeCoverGenerator();

        $feed = $this->feed('Lectures');

        $this->generate($feed);
        $first = $feed->cover_path;

        $this->generate($feed);
        $second = $feed->cover_path;

        $this->assertNotSame($first, $second);
        $storage->assertMissing($first);
        $storage->assertExists($second);
    }

    #[Test]
    public function a_generator_failure_leaves_the_cover_null_and_logs_a_warning()
    {
        Storage::fake();
        $this->fakeCoverGeneratorThatFails('the cover font is missing');

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $message) => str_contains($message, 'the cover font is missing'));

        $feed = $this->feed('Lectures');

        $this->generate($feed);

        $this->assertNull($feed->fresh()?->cover_path);
    }

    #[Test]
    public function a_failed_redraw_leaves_the_cover_the_feed_already_had()
    {
        $storage = Storage::fake();
        $this->fakeCoverGenerator();

        $feed = $this->feed('Lectures');
        $this->generate($feed);
        $existing = $feed->cover_path;

        $this->fakeCoverGeneratorThatFails();
        Log::shouldReceive('warning')->once();

        $this->generate($feed);

        $this->assertSame($existing, $feed->fresh()?->cover_path);
        $storage->assertExists($existing);
    }

    #[Test]
    public function a_failed_save_removes_the_new_cover_and_keeps_the_old_one()
    {
        $storage = Storage::fake();
        $this->fakeCoverGenerator();

        $feed = $this->feed('Lectures');
        $this->generate($feed);
        $existing = $feed->cover_path;

        Feed::saving(fn () => throw new \RuntimeException('the database is unavailable'));
        Log::shouldReceive('warning')->once();

        $this->generate($feed);

        $this->assertSame([$existing], $storage->allFiles());
        $this->assertSame($existing, $feed->cover_path);
        $this->assertSame($existing, $feed->fresh()?->cover_path);
    }

    private function feed(string $name): Feed
    {
        return Feed::factory()->create([
            'name'    => $name,
            'user_id' => User::factory()->create()->id,
        ]);
    }

    private function generate(Feed $feed): void
    {
        $this->app->make(GenerateFeedCover::class)($feed);
    }
}
