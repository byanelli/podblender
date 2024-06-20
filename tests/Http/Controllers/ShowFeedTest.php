<?php

namespace Tests\Http\Controllers;

use App\Models\AudioClip;
use App\Models\AudioSource;
use App\Models\Feed;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShowFeedTest extends TestCase
{
    #[Test]
    public function it_shows_the_feed()
    {

        $user = User::factory()->create();
        $feed = Feed::factory()->create([Feed::COL_USER_ID => $user->id]);
        $clip = AudioClip::factory()->create([AudioClip::COL_AUDIO_SOURCE_ID => AudioSource::factory()->create()->id]);

        $feed->audioClips()->attach($clip);

        $clip->load(AudioClip::REL_AUDIO_SOURCE);

        $this->actingAs($user);

        $this->get("/feeds/{$feed->id}")
            ->assertInertia(function (Assert $page) use ($clip, $feed) {
                $page->component('Feed')
                    ->has('feed')
                    ->where('feed.id', $feed->id)
                    ->where('feed.name', $feed->name)
                    ->has('feed.audio_clips', 1)
                    ->where('feed.audio_clips.0.id', $clip->id)
                    ->where('feed.audio_clips.0.title', $clip->title)
                    ->where('feed.audio_clips.0.processing', $clip->processing)
                    ->where('feed.audio_clips.0.audio_source.name', $clip->audioSource->name)
                    ->where('feed.audio_clips.0.audio_source.platform_type.name', $clip->audioSource->platform_type->name);
            });
    }

    #[Test]
    public function it_does_not_show_another_users_feed()
    {
        $this->expectException(AuthorizationException::class);

        $user = User::factory()->create();
        $feed = Feed::factory()->create([Feed::COL_USER_ID => $user->id + 1]);

        $this->actingAs($user);

        $this->get("/feeds/{$feed->id}");
    }
}
