<?php

namespace Tests\Models;

use App\Models\AudioClip;
use App\Models\AudioSource;
use App\Models\Feed;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AudioSourceTest extends TestCase
{
    #[Test]
    public function its_audio_clips_are_the_clips_belonging_to_it()
    {
        $source = AudioSource::factory()->create();
        $otherSource = AudioSource::factory()->create();

        $ours = AudioClip::factory()->count(2)->create([AudioClip::COL_AUDIO_SOURCE_ID => $source->id]);
        AudioClip::factory()->create([AudioClip::COL_AUDIO_SOURCE_ID => $otherSource->id]);

        $this->assertEqualsCanonicalizing(
            $ours->pluck('id')->all(),
            $source->audioClips->pluck('id')->all()
        );
    }

    #[Test]
    public function its_subscribers_are_the_feeds_subscribed_to_it()
    {
        $source = AudioSource::factory()->create();
        $otherSource = AudioSource::factory()->create();

        $subscriberA = Feed::factory()->create([Feed::COL_SUBSCRIPTION_ID => $source->id]);
        $subscriberB = Feed::factory()->create([Feed::COL_SUBSCRIPTION_ID => $source->id]);

        // A feed subscribed to a different source, and a feed subscribed to nothing, must not appear.
        Feed::factory()->create([Feed::COL_SUBSCRIPTION_ID => $otherSource->id]);
        Feed::factory()->create();

        $this->assertEqualsCanonicalizing(
            [$subscriberA->id, $subscriberB->id],
            $source->subscribers->pluck('id')->all()
        );
    }
}
