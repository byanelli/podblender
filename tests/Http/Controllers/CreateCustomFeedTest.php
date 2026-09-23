<?php

namespace Tests\Http\Controllers;

use App\Models\Feed;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\FakesCoverGenerator;
use Tests\TestCase;

class CreateCustomFeedTest extends TestCase
{
    use FakesCoverGenerator;

    protected function setUp(): void
    {
        parent::setUp();

        // Creating a feed draws a cover. No test here inspects the image, so
        // the generator is faked.
        Storage::fake();
        $this->fakeCoverGenerator();
    }

    public function test_create_custom_feed()
    {
        $feedName = 'Test Feed';
        $user = User::factory()->create();
        $this->actingAs($user);

        $requestPayload = [
            'name' => $feedName,
        ];

        $this->postJson('/feeds', $requestPayload)
            ->assertOk();

        $this->assertDatabaseCount('feeds', 1);

        $this->assertDatabaseHas('feeds', [
            'name'            => $feedName,
            'user_id'         => $user->id,
            'subscription_id' => null,
        ]);
    }

    #[Test]
    public function it_gives_the_new_feed_an_inbound_email_address()
    {
        config(['services.resend.inbound_domain' => 'mail.example.com']);

        $this->actingAs(User::factory()->create())
            ->postJson('/feeds', ['name' => 'Talks'])
            ->assertOk();

        $feed = Feed::query()->sole();

        $this->assertMatchesRegularExpression('/^[a-z0-9]{20}$/', $feed->inbound_email_token);
        $this->assertEquals("{$feed->inbound_email_token}@mail.example.com", $feed->inbound_email_address);
    }

    #[Test]
    public function it_gives_the_new_feed_a_cover()
    {
        $storage = Storage::fake();

        $this->actingAs(User::factory()->create())
            ->postJson('/feeds', ['name' => 'Long Reads for the Commute'])
            ->assertOk();

        $feed = Feed::query()->sole();

        $this->assertNotNull($feed->cover_path);
        $storage->assertExists($feed->cover_path);
    }

    #[Test]
    public function a_cover_that_cannot_be_drawn_does_not_stop_the_feed_being_created()
    {
        $this->fakeCoverGeneratorThatFails('the cover font is missing');

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $message) => str_contains($message, 'the cover font is missing'));

        $this->actingAs(User::factory()->create())
            ->postJson('/feeds', ['name' => 'Lectures'])
            ->assertOk();

        $this->assertNull(Feed::query()->sole()->cover_path);
    }
}
