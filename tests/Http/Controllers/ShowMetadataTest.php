<?php

namespace Tests\Http\Controllers;

use App\Models\Feed;
use App\Models\User;
use App\Platforms\Metadata;
use Illuminate\Auth\Access\AuthorizationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\FakesPlatform;
use Tests\TestCase;

class ShowMetadataTest extends TestCase
{
    use FakesPlatform;

    #[Test]
    public function it_shows_metadata() {
        $url = 'https://youtube.com/watch?v='.($id = 'lijwliejfwlef');

        $this->fakePlatform(
            metadata: new Metadata(
                id: $id,
                title: $title = 'Some title',
                description: $description = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
                sourceId: 'lwiejlwiejf',
                sourceName: $sourceName = 'Some channel',
            ),
        );

        $user = User::factory()->create();
        $feed = Feed::factory()->create([Feed::COL_USER_ID => $user->id]);

        $response = $this->actingAs($user)->post("feeds/{$feed->id}/show-metadata", ['url' => $url]);

        $this->assertStringContainsString(">{$url}</dd>", $response->getContent());
        $this->assertStringContainsString(">YouTube</dd>", $response->getContent());
        $this->assertStringContainsString(">{$title}</dd>", $response->getContent());
        $this->assertStringContainsString(">{$description}</dd>", $response->getContent());
        $this->assertStringContainsString(">{$sourceName}</dd>", $response->getContent());
    }

    #[Test]
    public function it_does_not_show_metadata_for_another_users_feed() {
        $this->expectException(AuthorizationException::class);

        $user = User::factory()->create();
        $feed = Feed::factory()->create([Feed::COL_USER_ID => $user->id+1]);

        $this->actingAs($user)->post("feeds/{$feed->id}/show-metadata", ['url' => 'https://youtube.com/watch?v=lijwliejfwlef']);
    }
}
