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
        $this->assertEquals($sourceName, $metadata->channel->name);
        $this->assertEquals($sourceId, $metadata->channel->id);
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
            'order' => 'date',
            'channelId' => $channelId = 'wlifjlwjf',
            'publishedAfter' => ($publishedAfter = now()->subDay())->format(DateTimeInterface::RFC3339),
            'key' => config('services.youtube_data_api.key'),
        ]);

        $apiUrl2 = 'https://www.googleapis.com/youtube/v3/search?'.http_build_query([
            'maxResults' => 50,
            'type' => 'video',
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

        /** @var Client $client */
        $client = $this->app->make(Client::class);

        $this->assertEquals([$videoId1, $videoId2, $videoId3], $client->getVideoIdsForChannel($channelId, $publishedAfter));
    }
}
