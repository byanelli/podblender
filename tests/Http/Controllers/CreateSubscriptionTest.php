<?php

namespace Tests\Http\Controllers;

use App\Models\AudioSource;
use App\Models\User;
use App\Platforms\Contracts\SourceMetadata;
use Tests\Concerns\FakesDispatcher;
use Tests\Concerns\FakesPlatform;
use Tests\TestCase;

class CreateSubscriptionTest extends TestCase
{
    use FakesDispatcher, FakesPlatform;

    public function test_create_subscription()
    {
        $sourceUrl = 'https://youtube.com/@zzz';
        $feedName = 'Test Feed';
        $user = User::factory()->create();
        $this->actingAs($user);

        $requestPayload = [
            'url' => $sourceUrl,
            'name' => $feedName,
        ];

        $this->fakePlatform(
            sourceMetadata: new SourceMetadata(
                name: $sourceName = 'Test channel',
                canonicalUrl: $sourceUrl,
            )
        );

        $this->fakeNoOpDispatcher();

        $this->postJson('/feeds/subscription', $requestPayload)
            ->assertOk();

        $this->assertDatabaseCount('audio_sources', 1);

        $this->assertDatabaseHas('audio_sources', [
            'platform_url' => $sourceUrl,
            'name' => $sourceName,
        ]);

        $this->assertDatabaseCount('feeds', 1);

        $this->assertDatabaseHas('feeds', [
            'name' => $feedName,
            'user_id' => $user->id,
            'subscription_id' => AudioSource::first()->id,
        ]);
    }
}
