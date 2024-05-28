<?php

namespace Tests\Http\Controllers;

use App\Models\AudioClip;
use App\Models\AudioSource;
use App\Models\Feed;
use App\Models\User;
use App\Apis\YtDlp\Metadata;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\FakesFfmpeg;
use Tests\Concerns\FakesYoutubeDownloader;
use Tests\TestCase;

class AddClipToFeedTest extends TestCase
{
    use FakesYoutubeDownloader, FakesFfmpeg;

    #[Test]
    public function it_adds_a_new_clip_to_the_feed() {
        $this->assertTrue(true);

        $user = User::factory()->create();
        $feed = Feed::factory()->create([Feed::COL_USER_ID => $user->id]);

        $this->fakeYoutubeDownloader(metadata: new Metadata(
            id: $metaId = '123',
            title: $metaTitle = 'Some title',
            description: $metaDescription = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
            channel_id: $metaChannelId = 'lwiejlwiejf',
            channel: $metaChannel = 'Some channel',
        ));
        $this->fakeFfmpeg($duration = 100);

        $this->assertTrue($feed->audioClips()->doesntExist());

        $this->actingAs($user)->postJson("/feeds/$feed->id/add", ['id' => '123']);

        /** @var AudioClip $clip */
        $clip = $feed->audioClips()->first();

        $this->assertNotNull($clip);
        $this->assertEquals($metaId, $clip->platform_id);
        $this->assertEquals($metaTitle, $clip->title);
        $this->assertEquals($metaDescription, $clip->description);
        $this->assertEquals($metaChannelId, $clip->audioSource->platform_id);
        $this->assertEquals($metaChannel, $clip->audioSource->name);
        $this->assertEquals($duration, $clip->duration);
    }

    #[Test]
    public function it_attaches_an_existing_clip_to_the_feed() {
        $this->assertTrue(true);

        $user = User::factory()->create();
        $feed = Feed::factory()->create([Feed::COL_USER_ID => $user->id]);
        $clip = AudioClip::factory()->create([
            AudioClip::COL_AUDIO_SOURCE_ID => AudioSource::factory()->create()->id
        ]);

        $this->assertTrue($feed->audioClips()->doesntExist());

        $this->actingAs($user)->postJson("/feeds/$feed->id/add", ['id' => $clip->platform_id]);

        $this->assertEquals(1, AudioClip::count());
        $this->assertTrue($feed->audioClips()->first()->is($clip));
    }
}
