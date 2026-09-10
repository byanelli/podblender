<?php

namespace Tests\Http\Controllers;

use App\Models\User;
use App\Platforms\Contracts\ClipMetadata;
use App\Platforms\Contracts\RemoteImageThumbnail;
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
                    authorName: $sourceName,
                ),
                estimatedDownloadTime: $estimatedDownloadTime = 1234,
            ),
        );

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('api/fetch-metadata', ['url' => $url]);

        $response->assertJsonFragment([
            'metadata'     => [
                'title'                 => $title,
                'description'           => $description,
                'canonicalUrl'          => $url,
                // roma serializes DateTimeInterface with the ATOM format by default (see IsArrayable::normalizeValue),
                // e.g. 2026-07-16T12:34:56+00:00.
                'publishedAt'           => $publishedAt->format(DateTimeInterface::ATOM),
                'source'                => [
                    'name'         => $sourceName,
                    'canonicalUrl' => $sourceUrl,
                    'type'         => ['name' => 'Channel', 'value' => 'channel'],
                    'authorName'   => $sourceName,
                    'clipCount'    => null,
                ],
                'estimatedDownloadTime' => $estimatedDownloadTime,
                'thumbnail'             => null,
            ],
            'platformType' => [
                'name'  => 'YouTube',
                'value' => 1,
            ],
        ]);
    }

    #[Test]
    public function it_shows_a_clips_thumbnail_source()
    {
        $url = 'https://youtube.com/watch?v='.($id = 'lijwliejfwlef');

        $this->fakePlatform(
            clipMetadata: new ClipMetadata(
                title: 'Some title',
                description: 'Lorem ipsum.',
                canonicalUrl: $url,
                publishedAt: now()->subDay()->roundSeconds(),
                source: new SourceMetadata(
                    name: 'Some channel',
                    canonicalUrl: 'https://youtube.com/channel/lwefjiritlrth',
                    authorName: 'Some channel',
                ),
                thumbnail: new RemoteImageThumbnail(
                    $thumbnailUrl = "https://i.ytimg.com/vi/{$id}/maxresdefault.jpg"
                ),
            ),
        );

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('api/fetch-metadata', ['url' => $url]);

        // The thumbnail is a nested object like the source is, so it survives
        // the trip to the client rather than serializing as an empty value.
        $response->assertJsonPath('metadata.thumbnail', ['url' => $thumbnailUrl]);
    }
}
