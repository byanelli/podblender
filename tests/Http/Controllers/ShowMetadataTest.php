<?php

namespace Tests\Http\Controllers;

use App\Models\User;
use App\Platforms\Contracts\ClipMetadata;
use App\Platforms\Contracts\SourceMetadata;
use DateTimeInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\FakesPlatform;
use Tests\TestCase;

class ShowMetadataTest extends TestCase
{
    use FakesPlatform;

    #[Test]
    public function it_shows_metadata()
    {
        $url = 'https://youtube.com/watch?v='.($id = 'lijwliejfwlef');

        $this->fakePlatform(
            clipMetadata: new ClipMetadata(
                title: $title = 'Some title',
                description: $description = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
                canonicalUrl: $url,
                publishedAt: $publishedAt = now()->subDay()->roundSeconds(),
                source: new SourceMetadata(
                    name: $sourceName = 'Some channel',
                    canonicalUrl: $sourceUrl = 'https://youtube.com/channel/lwefjiritlrth',
                ),
            ),
        );

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('api/fetch-metadata', ['url' => $url]);

        $response->assertJsonFragment([
            'metadata' => [
                'title' => $title,
                'description' => $description,
                'canonicalUrl' => $url,
                // roma serializes DateTimeInterface with the ATOM format by default (see IsArrayable::normalizeValue),
                // e.g. 2026-07-16T12:34:56+00:00.
                'publishedAt' => $publishedAt->format(DateTimeInterface::ATOM),
                'source' => [
                    'name' => $sourceName,
                    'canonicalUrl' => $sourceUrl,
                ],
            ],
            'platformType' => [
                'name' => 'YouTube',
                'value' => 1,
            ],
        ]);
    }
}
