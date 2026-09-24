<?php

namespace Tests\Apis\Tts;

use App\Apis\Tts\SegmentCache;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class SegmentCacheTest extends TestCase
{
    private function mp3(string $content): string
    {
        file_put_contents($path = sys_get_temp_dir().'/'.Uuid::uuid4()->toString().'.mp3', $content);

        return $path;
    }

    #[Test]
    public function it_returns_a_local_copy_of_a_cached_segment_and_its_usage()
    {
        Storage::fake(SegmentCache::DISK);

        $cache = app(SegmentCache::class);
        $cache->put('segment', $this->mp3('audio'), [10, 100]);

        [$path, $usage] = $cache->get('segment');

        $this->assertEquals('audio', file_get_contents($path));
        $this->assertEquals([10, 100], $usage);

        // The caller deletes the copy, which mustn't delete the entry.
        unlink($path);
        $this->assertNotNull($cache->get('segment'));
    }

    #[Test]
    public function it_keeps_a_segment_with_unknown_usage()
    {
        Storage::fake(SegmentCache::DISK);

        $cache = app(SegmentCache::class);
        $cache->put('segment', $this->mp3('audio'), null);

        $this->assertNull($cache->get('segment')[1]);
    }

    #[Test]
    public function it_misses_a_segment_that_was_never_cached_or_was_forgotten()
    {
        Storage::fake(SegmentCache::DISK);

        $cache = app(SegmentCache::class);
        $cache->put('segment', $this->mp3('audio'), [10, 100]);
        $cache->forget('segment');

        $this->assertNull($cache->get('segment'));
        $this->assertNull($cache->get('other'));
        $this->assertEmpty(Storage::disk(SegmentCache::DISK)->files());
    }

    #[Test]
    public function it_misses_an_mp3_without_its_usage_file()
    {
        $disk = Storage::fake(SegmentCache::DISK);

        $cache = app(SegmentCache::class);
        $cache->put('segment', $this->mp3('audio'), [10, 100]);

        // What a prune that ran between the two deletes would leave.
        $disk->delete(collect($disk->files())->first(fn (string $file) => str_ends_with($file, '.json')));

        $this->assertNull($cache->get('segment'));
    }

    #[Test]
    public function it_prunes_only_files_older_than_the_ttl()
    {
        $disk = Storage::fake(SegmentCache::DISK);

        $cache = app(SegmentCache::class);
        $cache->put('old', $this->mp3('old audio'), [10, 100]);
        $disk->put('abandoned.mp3.partial', 'partial write');

        $stale = time() - SegmentCache::TTL_SECONDS - 60;
        collect($disk->files())->each(fn (string $file) => touch($disk->path($file), $stale));

        $cache->put('new', $this->mp3('new audio'), [10, 100]);

        $cache->prune();

        $this->assertNull($cache->get('old'));
        $this->assertNotNull($cache->get('new'));
        $this->assertCount(2, $disk->files());
    }
}
