<?php

namespace Tests\Http\Controllers;

use App\Models\User;
use App\Platforms\Metadata;
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
                sourceId: $sourceId = 'lwiejlwiejf',
                sourceName: $sourceName = 'Some channel',
            ),
        );

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post("api/fetch-metadata", ['url' => $url]);

        $response->assertJsonFragment([
            'metadata' => compact('id', 'title', 'description', 'sourceId', 'sourceName'),
            'platformType' => [
                'name' => 'YouTube',
                'value' => 1,
            ]
        ]);
    }
}
