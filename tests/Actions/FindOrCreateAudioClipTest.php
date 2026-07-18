<?php

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Actions;

use App\Actions\FindOrCreateAudioClip;
use App\Enums\ClipProcessingState;
use App\Enums\PlatformType;
use App\Models\AudioClip;
use App\Platforms\Contracts\ClipMetadata;
use App\Platforms\Contracts\SourceMetadata;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\Concerns\FakesDispatcher;
use Tests\TestCase;

class FindOrCreateAudioClipTest extends TestCase
{
    use FakesDispatcher;

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
                canonicalUrl: $sourceUrl = 'https://youtube.com/channel/9340e9tjh490e5'
            ),
        );

        // We don't want to dispatch the DownloadAndStoreAudioClip job.
        $this->fakeNoOpDispatcher();

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
                canonicalUrl: 'https://youtube.com/channel/9340e9tjh490e5'
            ),
        );

        $this->fakeNoOpDispatcher();

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
