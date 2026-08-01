<?php

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Actions;

use App\Actions\FindOrCreateAudioClip;
use App\Enums\ClipProcessingState;
use App\Enums\PlatformType;
use App\Jobs\DownloadAndStoreAudioClip;
use App\Models\AudioClip;
use App\Models\AudioSource;
use App\Platforms\Contracts\ClipMetadata;
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

        // We don't want to actually run the DownloadAndStoreAudioClip job, but we do want to assert it was dispatched.
        Bus::fake();

        /** @var FindOrCreateAudioClip $createAudioClip */
        $createAudioClip = $this->app->make(FindOrCreateAudioClip::class);

        $clip = $createAudioClip->__invoke(PlatformType::YouTube, $metadata)
            ->load(AudioClip::REL_AUDIO_SOURCE);

        $this->assertEquals($clipUrl, $clip->platform_url);
        $this->assertEquals($title, $clip->title);
        $this->assertEquals($description, $clip->description);
        $this->assertEquals($publishedAt, $clip->published_at);
        $this->assertEquals(0, $clip->duration);
        $this->assertEquals($sourceUrl, $clip->audioSource->platform_url);
        $this->assertEquals($sourceName, $clip->audioSource->name);
        $this->assertEquals(ClipProcessingState::Processing, $clip->processing_state);

        // A newly created clip has no audio yet, so its download must be queued exactly once.
        Bus::assertDispatchedTimes(DownloadAndStoreAudioClip::class, 1);
    }

    #[Test]
    public function it_returns_an_existing_clip_without_queuing_another_download()
    {
        $existing = AudioClip::factory()->create([
            AudioClip::COL_PLATFORM_URL => $url = 'https://youtube.com/watch?v=already',
            AudioClip::COL_AUDIO_SOURCE_ID => AudioSource::factory()->create()->id,
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
        );

        Bus::fake();

        /** @var FindOrCreateAudioClip $createAudioClip */
        $createAudioClip = $this->app->make(FindOrCreateAudioClip::class);

        $clip = $createAudioClip->__invoke(PlatformType::YouTube, $metadata);

        // The existing clip is returned as-is; no new clip and no fresh download.
        $this->assertTrue($clip->is($existing));
        $this->assertDatabaseCount('audio_clips', 1);
        Bus::assertNotDispatched(DownloadAndStoreAudioClip::class);
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

        // Str::limit trims to (limit - 3) characters and appends an ellipsis, keeping the stored value within bounds.
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

        // Simulate a concurrent job winning the race: after our existence check has passed but before our own insert, a
        // row with the same (unique) platform_url appears. Our insert then loses on the unique constraint.
        AudioClip::creating(function (AudioClip $clip) use ($clipUrl) {
            static $raced = false;
            if ($raced) {
                return;
            }
            $raced = true;

            DB::table('audio_clips')->insert([
                AudioClip::COL_PLATFORM_URL => $clipUrl,
                AudioClip::COL_AUDIO_SOURCE_ID => $clip->audio_source_id,
                AudioClip::COL_TITLE => 'the winner',
                AudioClip::COL_DESCRIPTION => 'the winner',
                AudioClip::COL_PUBLISHED_AT => now(),
                AudioClip::COL_DURATION => 0,
                AudioClip::COL_STORAGE_PATH => Uuid::uuid4()->toString(),
                AudioClip::COL_GUID => Uuid::uuid4()->toString(),
                AudioClip::COL_PROCESSING_STATE => ClipProcessingState::Processed->value,
                AudioClip::COL_SIZE => 0,
                AudioClip::COL_CREATED_AT => now(),
                'updated_at' => now(),
            ]);
        });

        /** @var FindOrCreateAudioClip $createAudioClip */
        $createAudioClip = $this->app->make(FindOrCreateAudioClip::class);

        $clip = $createAudioClip->__invoke(PlatformType::YouTube, $metadata);

        // Instead of letting the unique-constraint violation blow up the whole subscription update, the action
        // returns the clip the winner created — and no duplicate is left behind.
        $this->assertEquals('the winner', $clip->title);
        $this->assertDatabaseCount('audio_clips', 1);
    }
}
