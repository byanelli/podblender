<?php

namespace Tests\Http\Controllers;

use App\Enums\PlatformType;
use App\Platforms\Contracts\SourceMetadata;
use Tests\TestCase;
use Tests\Concerns\FakesDispatcher;
use Tests\Concerns\FakesPlatform;

class CreateSubscriptionTest extends TestCase
{
    use FakesDispatcher, FakesPlatform;

    public function testCreateSubscription()
    {
        $url = 'https://example.com/audio-source';
        $feedName = 'Test Feed';
        $user = User::factory()->create();
        $this->actingAs($user);

        $requestPayload = [
            'url' => $url,
            'feed_name' => $feedName,
        ];

        $metadata = new SourceMetadata(
            name: 'Test Audio Source',
            canonicalUrl: $url
        );

        $this->fakePlatform(
            sourceMetadata: new SourceMetadata(
                name: $sourceName = 'Test channel',
                canonicalUrl: $sourceUrl = 'https://youtube.com/@zzz'
            )
        );

        $this->fakeNoOpDispatcher();

        // Act
        $response = $this->postJson('/feed/subscriptions', $requestPayload);

        // Assert
        $response->assertOk();
        $this->assertDatabaseHas('audio_sources', [
            'platform_url' => $url,
            'name' => 'Test Audio Source',
        ]);
        $this->assertDatabaseHas('feeds', [
            'name' => $feedName,
            'user_id' => $user->id,
        ]);
    }
}
