<?php

namespace Tests\Apis\YouTubeData;

use App\Apis\YouTubeData\Client;
use DateTimeInterface;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClientTest extends TestCase
{
    #[Test]
    public function it_gets_video_metadata()
    {
        Http::fake(['*' => Http::response([
            'items' => [
                [
                    'id' => $clipId = 'leirjieljrg',
                    'snippet' => [
                        'title' => $title = 'some video',
                        'description' => $description = 'foo bar',
                        'channelId' => $sourceId = 'eiorjg90ej',
                        'channelTitle' => $sourceName = 'some channel',
                        'publishedAt' => ($publishedAt = now()->subDay()->roundSeconds())->format(DateTimeInterface::RFC3339),
                    ],
                    'contentDetails' => [
                        'duration' => 'PT4M13S',
                    ],
                ],
            ],
        ])]);

        /** @var Client $client */
        $client = $this->app->make(Client::class);

        $metadata = $client->getVideoMetadata($clipId);

        $this->assertEquals($clipId, $metadata->id);
        $this->assertEquals($title, $metadata->title);
        $this->assertEquals($description, $metadata->description);
        $this->assertEquals($publishedAt, $metadata->publishedAt);
        $this->assertEquals($sourceName, $metadata->channel->name);
        $this->assertEquals($sourceId, $metadata->channel->id);
        $this->assertSame(253, $metadata->durationSeconds);
    }

    #[Test]
    public function it_parses_multipart_durations()
    {
        Http::fake(['*' => Http::response([
            'items' => [
                [
                    'id' => 'leirjieljrg',
                    'snippet' => [
                        'title' => 'some video',
                        'description' => 'foo bar',
                        'channelId' => 'eiorjg90ej',
                        'channelTitle' => 'some channel',
                        'publishedAt' => now()->format(DateTimeInterface::RFC3339),
                    ],
                    'contentDetails' => [
                        'duration' => 'PT1H2M3S',
                    ],
                ],
            ],
        ])]);

        /** @var Client $client */
        $client = $this->app->make(Client::class);

        $this->assertSame(3723, $client->getVideoMetadata('leirjieljrg')->durationSeconds);
    }

    #[Test]
    public function it_returns_a_null_duration_when_content_details_are_missing()
    {
        Http::fake(['*' => Http::response([
            'items' => [
                [
                    'id' => 'leirjieljrg',
                    'snippet' => [
                        'title' => 'some video',
                        'description' => 'foo bar',
                        'channelId' => 'eiorjg90ej',
                        'channelTitle' => 'some channel',
                        'publishedAt' => now()->format(DateTimeInterface::RFC3339),
                    ],
                ],
            ],
        ])]);

        /** @var Client $client */
        $client = $this->app->make(Client::class);

        $this->assertNull($client->getVideoMetadata('leirjieljrg')->durationSeconds);
    }

    #[Test]
    public function it_returns_a_null_duration_for_a_malformed_duration()
    {
        Http::fake(['*' => Http::response([
            'items' => [
                [
                    'id' => 'leirjieljrg',
                    'snippet' => [
                        'title' => 'some video',
                        'description' => 'foo bar',
                        'channelId' => 'eiorjg90ej',
                        'channelTitle' => 'some channel',
                        'publishedAt' => now()->format(DateTimeInterface::RFC3339),
                    ],
                    'contentDetails' => [
                        'duration' => 'garbage',
                    ],
                ],
            ],
        ])]);

        /** @var Client $client */
        $client = $this->app->make(Client::class);

        $this->assertNull($client->getVideoMetadata('leirjieljrg')->durationSeconds);
    }

    #[Test]
    public function it_gets_channel_metadata()
    {
        Http::fake(['*' => Http::response([
            'items' => [
                [
                    'id' => $id = 'leirjieljrg',
                    'brandingSettings' => [
                        'channel' => [
                            'title' => $name = 'some channel',
                        ],
                    ],
                ],
            ],
        ])]);

        /** @var Client $client */
        $client = $this->app->make(Client::class);

        $metadata = $client->getChannelMetadataForId($id);

        $this->assertEquals($id, $metadata->id);
        $this->assertEquals($name, $metadata->name);

        $metadata = $client->getChannelMetadataForHandle($id);

        $this->assertEquals($id, $metadata->id);
        $this->assertEquals($name, $metadata->name);
    }

    #[Test]
    public function it_gets_video_ids_for_channel()
    {
        [$videoId1, $videoId2, $videoId3] = ['eilrijgegr', 'orjtriojtb', 'wtertntrnt'];

        $apiUrl1 = 'https://www.googleapis.com/youtube/v3/search?'.http_build_query([
            'maxResults' => 50,
            'type' => 'video',
            'part' => 'snippet',
            'order' => 'date',
            'channelId' => $channelId = 'wlifjlwjf',
            'publishedAfter' => ($publishedAfter = now()->subDay())->format(DateTimeInterface::RFC3339),
            'key' => config('services.youtube_data_api.key'),
        ]);

        $apiUrl2 = 'https://www.googleapis.com/youtube/v3/search?'.http_build_query([
            'maxResults' => 50,
            'type' => 'video',
            'part' => 'snippet',
            'order' => 'date',
            'channelId' => $channelId,
            'publishedAfter' => $publishedAfter->format(DateTimeInterface::RFC3339),
            'pageToken' => $nextPageToken = 'liwfljwifljw',
            'key' => config('services.youtube_data_api.key'),
        ]);

        Http::fake([
            $apiUrl1 => Http::response([
                'items' => [
                    [
                        'id' => ['videoId' => $videoId1],
                    ],
                    [
                        'id' => ['videoId' => $videoId2],
                    ],
                ],
                'nextPageToken' => $nextPageToken,
            ]),
            $apiUrl2 => Http::response([
                'items' => [
                    [
                        'id' => ['videoId' => $videoId3],
                    ],
                ],
                'nextPageToken' => null,
            ]),
        ]);

        /*
RuntimeException: Attempted request to [https://www.googleapis.com/youtube/v3/search?maxResults=50&type=video&part=snippet&order=date&channelId=wlifjlwjf&publishedAfter=2024-12-26T17%3A57%3A59%2B00%3A00&key=REDACTED*/

        /** @var Client $client */
        $client = $this->app->make(Client::class);

        $this->assertEquals([$videoId1, $videoId2, $videoId3], $client->getVideoIdsForChannel($channelId, $publishedAfter));
    }
}
