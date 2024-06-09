<?php

namespace Tests\Http\Controllers;

use App\Models\AudioClip;
use App\Models\AudioSource;
use App\Models\Feed;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShowFeedTest extends TestCase
{
    #[Test]
    public function it_shows_the_feed() {

        $user = User::factory()->create();
        $feed = Feed::factory()->create([Feed::COL_USER_ID => $user->id]);
        $clip = AudioClip::factory()->create([AudioClip::COL_AUDIO_SOURCE_ID => AudioSource::factory()->create()->id]);

        $feed->audioClips()->attach($clip);

        $this->actingAs($user);

        $response = $this->get("/feeds/{$feed->id}");

        $this->assertStringContainsString(e($clip->title)."</p>", $response->getContent());
    }

    #[Test]
    public function it_does_not_show_another_users_feed() {
        $this->expectException(AuthorizationException::class);

        $user = User::factory()->create();
        $feed = Feed::factory()->create([Feed::COL_USER_ID => $user->id+1]);

        $this->actingAs($user);

        $this->get("/feeds/{$feed->id}");
    }
}
