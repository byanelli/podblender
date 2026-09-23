<?php

namespace Tests\Models;

use App\Enums\ClipProcessingState;
use App\Models\AudioClip;
use App\Models\AudioSource;
use App\Models\Feed;
use App\Models\InboundEmail;
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

        // Attached oldest pivot date first, so returning rows in insert order would fail the ordering assertion.
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

        // The processing clip is excluded even though its pivot date is the most recent.
        $this->assertCount(2, $finished);
        $this->assertEquals([$newer->id, $older->id], $finished->pluck('id')->all());
    }

    #[Test]
    public function it_has_no_inbound_address_without_a_token_or_a_configured_domain()
    {
        config(['services.resend.inbound_domain' => 'mail.example.com']);
        $this->assertNull(Feed::factory()->make(['inbound_email_token' => null])->inbound_email_address);

        config(['services.resend.inbound_domain' => null]);
        $this->assertNull(Feed::factory()->make(['inbound_email_token' => 'abc123'])->inbound_email_address);
    }

    #[Test]
    public function deleting_a_feed_deletes_its_inbound_emails()
    {
        $feed = Feed::factory()->create();
        InboundEmail::factory()->create(['feed_id' => $feed->id]);

        $feed->delete();

        $this->assertDatabaseCount('inbound_emails', 0);
    }
}
