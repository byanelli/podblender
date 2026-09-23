<?php

namespace Tests\Http\Controllers;

use App\Models\AudioSource;
use App\Models\Feed;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RegenerateInboundEmailAddressTest extends TestCase
{
    #[Test]
    public function it_replaces_the_feeds_address()
    {
        $user = User::factory()->create();
        $feed = Feed::factory()->create(['user_id' => $user->id, 'inbound_email_token' => 'old']);

        $this->actingAs($user)
            ->postJson("/feeds/{$feed->id}/inbound-email-address")
            ->assertOk();

        $this->assertMatchesRegularExpression('/^[a-z0-9]{20}$/', $feed->refresh()->inbound_email_token);
    }

    #[Test]
    public function it_gives_an_address_to_a_custom_feed_that_has_none()
    {
        $user = User::factory()->create();
        $feed = Feed::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->postJson("/feeds/{$feed->id}/inbound-email-address")->assertOk();

        $this->assertNotNull($feed->refresh()->inbound_email_token);
    }

    #[Test]
    public function it_refuses_a_subscription_feed()
    {
        $this->withExceptionHandling();

        $user = User::factory()->create();
        $feed = Feed::factory()->create([
            'user_id'         => $user->id,
            'subscription_id' => AudioSource::factory()->create()->id,
        ]);

        $this->actingAs($user)
            ->postJson("/feeds/{$feed->id}/inbound-email-address")
            ->assertUnprocessable();

        $this->assertNull($feed->refresh()->inbound_email_token);
    }

    #[Test]
    public function it_refuses_another_users_feed()
    {
        $this->expectException(AuthorizationException::class);

        $user = User::factory()->create();
        $feed = Feed::factory()->create(['user_id' => $user->id + 1]);

        $this->actingAs($user)->postJson("/feeds/{$feed->id}/inbound-email-address");
    }
}
