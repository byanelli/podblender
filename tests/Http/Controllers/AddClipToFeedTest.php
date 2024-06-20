<?php

namespace Tests\Http\Controllers;

use App\Models\AudioClip;
use App\Models\AudioSource;
use App\Models\Feed;
use App\Models\User;
use App\Platforms\Metadata;
use Illuminate\Auth\Access\AuthorizationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\FakesDispatcher;
use Tests\Concerns\FakesPlatform;
use Tests\TestCase;

class AddClipToFeedTest extends TestCase
{
    use FakesPlatform, FakesDispatcher;

    #[Test]
    public function it_adds_a_new_clip_to_the_feed() {
        $url = 'https://youtube.com/watch?v='.($id = 'lijwliejfwlef');

        $this->fakePlatform(
            metadata: new Metadata(
                id: $id,
                title: $title = 'Some title',
                description: $description = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
                sourceId: $sourceId = 'lwiejlwiejf',
                sourceName: $sourceName = 'Some channel',
            ),
        );

        // We don't want to run the DownloadAndStore job
        $this->fakeNoOpDispatcher();

        $user = User::factory()->create();
        $feed = Feed::factory()->create([Feed::COL_USER_ID => $user->id]);

        $this->assertTrue($feed->audioClips()->doesntExist());

        $this->actingAs($user)->postJson("api/feeds/$feed->id/add", ['url' => $url]);

        /** @var AudioClip $clip */
        $clip = $feed->audioClips()->first();

        $this->assertNotNull($clip);
        $this->assertEquals($url, $clip->platform_url);
        $this->assertEquals($title, $clip->title);
        $this->assertEquals($description, $clip->description);
        $this->assertEquals($sourceId, $clip->audioSource->platform_id);
        $this->assertEquals($sourceName, $clip->audioSource->name);
        $this->assertEquals(0, $clip->duration);
    }

    #[Test]
    public function it_attaches_an_existing_clip_to_the_feed() {
        $user = User::factory()->create();
        $feed = Feed::factory()->create([Feed::COL_USER_ID => $user->id]);
        $clip = AudioClip::factory()->create([
            AudioClip::COL_AUDIO_SOURCE_ID => AudioSource::factory()->create()->id
        ]);

        $this->assertTrue($feed->audioClips()->doesntExist());

        $this->actingAs($user)->postJson("api/feeds/$feed->id/add", ['url' => $clip->platform_url]);

        $this->assertEquals(1, AudioClip::count());
        $this->assertTrue($feed->audioClips()->first()->is($clip));
    }

    #[Test]
    public function it_does_not_add_a_clip_to_another_users_feed() {
        $this->expectException(AuthorizationException::class);

        $user = User::factory()->create();
        $feed = Feed::factory()->create([Feed::COL_USER_ID => $user->id+1]);
        $clip = AudioClip::factory()->create([
            AudioClip::COL_AUDIO_SOURCE_ID => AudioSource::factory()->create()->id
        ]);

        $this->actingAs($user)->postJson("api/feeds/$feed->id/add", ['url' => $clip->platform_url]);
    }
}
