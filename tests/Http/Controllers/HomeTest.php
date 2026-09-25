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
        $feed = Feed::factory()->create(['user_id' => $user->id]);

        $source = AudioSource::factory()->create();
        $feed->audioClips()->attach(
            AudioClip::factory()->count(2)->create(['audio_source_id' => $source->id])
        );

        // Another user's feed, which must not appear on this dashboard.
        Feed::factory()->create(['user_id' => $user->id + 1]);

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
    public function it_gives_each_feed_its_inbound_email_address_for_the_copy_button()
    {
        config(['services.resend.inbound_domain' => 'mail.example.com']);

        $user = User::factory()->create();
        Feed::factory()->create(['user_id' => $user->id, 'inbound_email_token' => 'abc123']);

        $this->actingAs($user)
            ->get('/')
            ->assertInertia(fn (Assert $page) => $page
                ->where('user.feeds.0.inbound_email_address', 'abc123@mail.example.com')
                ->missing('user.feeds.0.inbound_email_token'));
    }

    #[Test]
    public function it_sends_a_user_with_an_unverified_email_address_to_the_verification_prompt()
    {
        $this->actingAs(User::factory()->unverified()->create())
            ->get('/')
            ->assertRedirect(route('verification.notice'));
    }

    #[Test]
    public function it_is_not_reachable_by_a_guest()
    {
        $this->expectException(AuthenticationException::class);

        $this->get('/');
    }
}
