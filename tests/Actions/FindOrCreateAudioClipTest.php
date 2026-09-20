<?php

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Actions;

use App\Actions\FindOrCreateAudioClip;
use App\Enums\ClipProcessingState;
use App\Enums\PlatformType;
use App\Jobs\DownloadAndStoreAudioClip;
use App\Jobs\DownloadAndStoreThumbnail;
use App\Models\AudioClip;
use App\Models\AudioSource;
use App\Platforms\Contracts\ClipMetadata;
use App\Platforms\Contracts\RemoteImageThumbnail;
use App\Platforms\Contracts\SourceMetadata;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class FindOrCreateAudioClipTest extends TestCase
{
    #[Test]
    public function it_creates_an_audio_clip()
    {
        $metadata = new ClipMetadata(
            title: $title = 'foo',
            description: $description = 'zzz',
            canonicalUrl: $clipUrl = 'https://youtube.com/watch?v=lijwliejfwlef',
            publishedAt: $publishedAt = now()->subDay()->roundSeconds(),
            source: new SourceMetadata(
                name: $sourceName = 'bar',
                canonicalUrl: $sourceUrl = 'https://youtube.com/channel/9340e9tjh490e5',
                authorName: $sourceName,
            ),
        );

        Bus::fake();

        /** @var FindOrCreateAudioClip $createAudioClip */
        $createAudioClip = $this->app->make(FindOrCreateAudioClip::class);

        $clip = $createAudioClip->__invoke(PlatformType::YouTube, $metadata)
            ->load('audioSource');

        $this->assertEquals($clipUrl, $clip->platform_url);
        $this->assertEquals($title, $clip->title);
        $this->assertEquals($description, $clip->description);
        $this->assertEquals($publishedAt, $clip->published_at);
        $this->assertEquals(0, $clip->duration);
        $this->assertEquals($sourceUrl, $clip->audioSource->platform_url);
        $this->assertEquals($sourceName, $clip->audioSource->name);
        $this->assertEquals(ClipProcessingState::Processing, $clip->processing_state);

        Bus::assertDispatchedTimes(DownloadAndStoreAudioClip::class, 1);

        // The metadata has no thumbnail, and a thumbnail job with no source
        // would fail on every attempt.
        Bus::assertNotDispatched(DownloadAndStoreThumbnail::class);
    }

    #[Test]
    public function it_queues_a_thumbnail_download_when_the_metadata_has_one()
    {
        $metadata = new ClipMetadata(
            title: 'foo',
            description: 'zzz',
            canonicalUrl: 'https://youtube.com/watch?v=withart',
            publishedAt: now()->subDay()->roundSeconds(),
            source: new SourceMetadata(
                name: 'bar',
                canonicalUrl: 'https://youtube.com/channel/9340e9tjh490e5',
                authorName: 'bar',
            ),
            thumbnail: new RemoteImageThumbnail($thumbnailUrl = 'https://i.ytimg.com/vi/withart/maxresdefault.jpg'),
        );

        Bus::fake();

        /** @var FindOrCreateAudioClip $createAudioClip */
        $createAudioClip = $this->app->make(FindOrCreateAudioClip::class);

        $clip = $createAudioClip->__invoke(PlatformType::YouTube, $metadata);

        Bus::assertDispatched(
            DownloadAndStoreThumbnail::class,
            fn (DownloadAndStoreThumbnail $job) => $job->clip->is($clip)
                && $job->source instanceof RemoteImageThumbnail
                && $job->source->url === $thumbnailUrl
        );
    }

    #[Test]
    public function it_returns_an_existing_clip_without_queuing_another_download()
    {
        $existing = AudioClip::factory()->create([
            'platform_url'    => $url = 'https://youtube.com/watch?v=already',
            'audio_source_id' => AudioSource::factory()->create()->id,
        ]);

        $metadata = new ClipMetadata(
            title: 'foo',
            description: 'zzz',
            canonicalUrl: $url,
            publishedAt: now()->subDay()->roundSeconds(),
            source: new SourceMetadata(
                name: 'bar',
                canonicalUrl: 'https://youtube.com/channel/9340e9tjh490e5',
                authorName: 'bar',
            ),
            // Included so the test can check that no thumbnail job is queued
            // for an existing clip.
            thumbnail: new RemoteImageThumbnail('https://i.ytimg.com/vi/already/maxresdefault.jpg'),
        );

        Bus::fake();

        /** @var FindOrCreateAudioClip $createAudioClip */
        $createAudioClip = $this->app->make(FindOrCreateAudioClip::class);

        $clip = $createAudioClip->__invoke(PlatformType::YouTube, $metadata);

        $this->assertTrue($clip->is($existing));
        $this->assertDatabaseCount('audio_clips', 1);
        Bus::assertNotDispatched(DownloadAndStoreAudioClip::class);
        Bus::assertNotDispatched(DownloadAndStoreThumbnail::class);
    }

    #[Test]
    public function it_truncates_an_overlong_title_and_description_to_fit_the_columns()
    {
        $metadata = new ClipMetadata(
            title: str_repeat('a', 600),
            description: str_repeat('b', 1200),
            canonicalUrl: 'https://youtube.com/watch?v=verylong',
            publishedAt: now()->subDay()->roundSeconds(),
            source: new SourceMetadata(
                name: 'bar',
                canonicalUrl: 'https://youtube.com/channel/9340e9tjh490e5',
                authorName: 'bar',
            ),
        );

        Bus::fake();

        /** @var FindOrCreateAudioClip $createAudioClip */
        $createAudioClip = $this->app->make(FindOrCreateAudioClip::class);

        $clip = $createAudioClip->__invoke(PlatformType::YouTube, $metadata);

        // The action limits to (column length - 3) characters, and Str::limit appends a three-character ellipsis.
        $this->assertEquals(str_repeat('a', 497).'...', $clip->title);
        $this->assertEquals(500, strlen($clip->title));
        $this->assertEquals(str_repeat('b', 997).'...', $clip->description);
        $this->assertEquals(1000, strlen($clip->description));
    }

    #[Test]
    public function it_returns_the_existing_clip_when_a_concurrent_create_wins_the_race()
    {
        $clipUrl = 'https://youtube.com/watch?v=raced';

        $metadata = new ClipMetadata(
            title: 'foo',
            description: 'zzz',
            canonicalUrl: $clipUrl,
            publishedAt: now()->subDay()->roundSeconds(),
            source: new SourceMetadata(
                name: 'bar',
                canonicalUrl: 'https://youtube.com/channel/9340e9tjh490e5',
                authorName: 'bar',
            ),
        );

        Bus::fake();

        // Simulate a concurrent job: a row with the same unique platform_url is inserted after the action's existence
        // check and before its insert, which then fails on the unique constraint.
        AudioClip::creating(function (AudioClip $clip) use ($clipUrl) {
            static $raced = false;
            if ($raced) {
                return;
            }
            $raced = true;

            DB::table('audio_clips')->insert([
                'platform_url'     => $clipUrl,
                'audio_source_id'  => $clip->audio_source_id,
                'title'            => 'the winner',
                'description'      => 'the winner',
                'published_at'     => now(),
                'duration'         => 0,
                'storage_path'     => Uuid::uuid4()->toString(),
                'guid'             => Uuid::uuid4()->toString(),
                'processing_state' => ClipProcessingState::Processed->value,
                'size'             => 0,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        });

        /** @var FindOrCreateAudioClip $createAudioClip */
        $createAudioClip = $this->app->make(FindOrCreateAudioClip::class);

        $clip = $createAudioClip->__invoke(PlatformType::YouTube, $metadata);

        // An uncaught violation would fail the whole subscription update.
        $this->assertEquals('the winner', $clip->title);
        $this->assertDatabaseCount('audio_clips', 1);
    }
}
