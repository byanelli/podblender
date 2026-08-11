<?php

namespace Tests\Models;

use App\Enums\ClipProcessingState;
use App\Models\AudioClip;
use App\Models\AudioSource;
use App\Models\Feed;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FeedTest extends TestCase
{
    #[Test]
    public function finished_processing_clips_excludes_unprocessed_clips_and_orders_by_pivot_date_descending()
    {
        $source = AudioSource::factory()->create();
        $feed = Feed::factory()->create();

        $older = AudioClip::factory()->create([
            'audio_source_id'  => $source->id,
            'processing_state' => ClipProcessingState::Processed,
        ]);
        $newer = AudioClip::factory()->create([
            'audio_source_id'  => $source->id,
            'processing_state' => ClipProcessingState::Processed,
        ]);
        $processing = AudioClip::factory()->create([
            'audio_source_id'  => $source->id,
            'processing_state' => ClipProcessingState::Processing,
        ]);

        // Attach the older clip with the more recent pivot date reversed from insert order, to prove the ordering
        // comes from the pivot's published_at and not the order the rows were inserted.
        $feed->audioClips()->attach($older, [
            'published_at' => CarbonImmutable::parse('2026-01-01 00:00:00'),
        ]);
        $feed->audioClips()->attach($newer, [
            'published_at' => CarbonImmutable::parse('2026-06-01 00:00:00'),
        ]);
        $feed->audioClips()->attach($processing, [
            'published_at' => CarbonImmutable::parse('2026-12-01 00:00:00'),
        ]);

        $finished = $feed->audioClipsFinishedProcessing()->get();

        // The still-processing clip is filtered out even though its pivot date is the most recent.
        $this->assertCount(2, $finished);
        $this->assertEquals([$newer->id, $older->id], $finished->pluck('id')->all());
    }
}
