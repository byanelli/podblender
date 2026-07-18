<?php

namespace Tests\Http\Controllers;

use App\Models\AudioClip;
use App\Models\AudioSource;
use App\Models\Feed;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HomeTest extends TestCase
{
    #[Test]
    public function it_shows_the_dashboard_with_the_users_feeds_and_clip_counts()
    {
        $user = User::factory()->create();
        $feed = Feed::factory()->create([Feed::COL_USER_ID => $user->id]);

        $source = AudioSource::factory()->create();
        $feed->audioClips()->attach(
            AudioClip::factory()->count(2)->create([AudioClip::COL_AUDIO_SOURCE_ID => $source->id])
        );

        // Another user's feed must not leak into this dashboard.
        Feed::factory()->create([Feed::COL_USER_ID => $user->id + 1]);

        $this->actingAs($user)
            ->get('/')
            ->assertInertia(function (Assert $page) use ($user, $feed) {
                $page->component('Dashboard')
                    ->where('user.id', $user->id)
                    ->has('user.feeds', 1)
                    ->where('user.feeds.0.id', $feed->id)
                    ->where('user.feeds.0.audio_clips_count', 2);
            });
    }

    #[Test]
    public function it_is_not_reachable_by_a_guest()
    {
        $this->expectException(AuthenticationException::class);

        $this->get('/');
    }
}
