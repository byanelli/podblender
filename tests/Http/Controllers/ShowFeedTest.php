<?php

namespace Tests\Http\Controllers;

use App\Models\AudioClip;
use App\Models\AudioSource;
use App\Models\Feed;
use App\Models\InboundEmail;
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
        $feed = Feed::factory()->create(['user_id' => $user->id]);
        $clip = AudioClip::factory()->create(['audio_source_id' => AudioSource::factory()->create()->id]);

        $feed->audioClips()->attach($clip);

        $clip->load('audioSource');

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
                    ->where('feed.audio_clips.0.processing_state', $clip->processing_state)
                    ->where('feed.audio_clips.0.audio_source.name', $clip->audioSource->name)
                    ->where('feed.audio_clips.0.audio_source.platform_type.name', $clip->audioSource->platform_type->name);
            });
    }

    #[Test]
    public function it_shows_the_inbound_address_and_the_newest_emails_but_not_the_raw_token()
    {
        config(['services.resend.inbound_domain' => 'mail.example.com']);

        $user = User::factory()->create();
        $feed = Feed::factory()->create(['user_id' => $user->id, 'inbound_email_token' => 'abc123']);
        $emails = InboundEmail::factory()->count(11)->create(['feed_id' => $feed->id]);

        $this->actingAs($user);

        $this->get("/feeds/{$feed->id}")
            ->assertInertia(function (Assert $page) use ($emails) {
                $page->where('feed.inbound_email_address', 'abc123@mail.example.com')
                    ->missing('feed.inbound_email_token')
                    ->has('feed.inbound_emails', 10)
                    ->where('feed.inbound_emails.0.id', $emails->last()->id)
                    ->missing('feed.inbound_emails.0.resend_email_id');
            });
    }

    #[Test]
    public function it_does_not_show_another_users_feed()
    {
        $this->expectException(AuthorizationException::class);

        $user = User::factory()->create();
        $feed = Feed::factory()->create(['user_id' => $user->id + 1]);

        $this->actingAs($user);

        $this->get("/feeds/{$feed->id}");
    }
}
