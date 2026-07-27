<?php

namespace Tests\Apis\YouTubeData;

use App\Apis\YouTubeData\ChannelMetadata;
use App\Apis\YouTubeData\Client;
use Carbon\CarbonImmutable;
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

    /**
     * One page of playlistItems. The two date/channel fields here are the ones
     * that matter: contentDetails.videoPublishedAt (when the video went up) is
     * NOT snippet.publishedAt (when it was added to the playlist), and
     * videoOwnerChannel* is the uploader rather than the playlist's owner.
     *
     * @param  array<int, array{id: string, title: string, videoPublishedAt: string, addedAt?: string, ownerId?: string, ownerTitle?: string}>  $videos
     * @return array<string, mixed>
     */
    private function playlistItemsPage(array $videos, ?string $nextPageToken = null): array
    {
        return array_filter([
            'items' => collect($videos)->map(fn (array $video) => [
                'snippet' => [
                    'title' => $video['title'],
                    'description' => 'about '.$video['title'],
                    'publishedAt' => $video['addedAt'] ?? $video['videoPublishedAt'],
                    'channelId' => 'UCplaylistowner',
                    'channelTitle' => 'Playlist Owner',
                    'videoOwnerChannelId' => $video['ownerId'] ?? 'UCuploader',
                    'videoOwnerChannelTitle' => $video['ownerTitle'] ?? 'The Uploader',
                ],
                'contentDetails' => [
                    'videoId' => $video['id'],
                    'videoPublishedAt' => $video['videoPublishedAt'],
                ],
            ])->all(),
            'nextPageToken' => $nextPageToken,
        ], fn ($value) => ! is_null($value));
    }

    #[Test]
    public function it_dates_a_playlist_video_by_when_it_was_published_not_when_it_was_added()
    {
        Http::fake(['*' => Http::response($this->playlistItemsPage([
            [
                'id' => 'v1',
                'title' => 'An old talk',
                'videoPublishedAt' => '2019-03-04T10:00:00Z',
                // Added to the playlist years later. Dating the clip by this
                // would push an old video to the top of a podcast app.
                'addedAt' => '2026-01-01T00:00:00Z',
            ],
        ]))]);

        /** @var Client $client */
        $client = $this->app->make(Client::class);

        $videos = $client->getAllVideoMetadataForPlaylist('PLabc');

        $this->assertCount(1, $videos);
        $this->assertEquals(CarbonImmutable::parse('2019-03-04T10:00:00Z'), $videos[0]->publishedAt);
    }

    #[Test]
    public function it_credits_a_playlist_video_to_its_uploader_not_the_playlist_owner()
    {
        Http::fake(['*' => Http::response($this->playlistItemsPage([
            [
                'id' => 'v1',
                'title' => 'A guest talk',
                'videoPublishedAt' => '2024-05-05T10:00:00Z',
                'ownerId' => 'UCguest',
                'ownerTitle' => 'The Guest',
            ],
        ]))]);

        /** @var Client $client */
        $client = $this->app->make(Client::class);

        $videos = $client->getAllVideoMetadataForPlaylist('PLabc');

        $this->assertEquals('UCguest', $videos[0]->channel->id);
        $this->assertEquals('The Guest', $videos[0]->channel->name);
    }

    #[Test]
    public function it_stops_paging_a_playlist_once_it_passes_the_cutoff()
    {
        // playlistItems has no server-side date filter — it accepts
        // publishedAfter and ignores it — so the cutoff is applied while
        // paging. Items come back newest-first, so the first one older than the
        // cutoff means every later page is older too.
        Http::fakeSequence()
            ->push($this->playlistItemsPage([
                ['id' => 'v1', 'title' => 'Recent', 'videoPublishedAt' => '2026-06-01T00:00:00Z'],
                ['id' => 'v2', 'title' => 'Too old', 'videoPublishedAt' => '2020-01-01T00:00:00Z'],
            ], nextPageToken: 'page2'))
            ->push($this->playlistItemsPage([
                ['id' => 'v3', 'title' => 'Older still', 'videoPublishedAt' => '2019-01-01T00:00:00Z'],
            ]));

        /** @var Client $client */
        $client = $this->app->make(Client::class);

        $videos = $client->getAllVideoMetadataForPlaylist('PLabc', CarbonImmutable::parse('2026-01-01T00:00:00Z'));

        $this->assertCount(1, $videos);
        $this->assertEquals('Recent', $videos[0]->title);

        // The second page was never requested: paging stopped at the first
        // item past the cutoff.
        Http::assertSentCount(1);
    }

    #[Test]
    public function it_skips_playlist_items_for_deleted_or_private_videos()
    {
        // A removed video keeps its slot in the playlist but loses its
        // publication date, and there's nothing to download.
        Http::fake(['*' => Http::response([
            'items' => [
                [
                    'snippet' => [
                        'title' => 'Private video',
                        'description' => '',
                        'publishedAt' => '2026-01-01T00:00:00Z',
                        'channelId' => 'UCowner',
                        'channelTitle' => 'Owner',
                    ],
                    'contentDetails' => ['videoId' => 'gone'],
                ],
                [
                    'snippet' => [
                        'title' => 'A real video',
                        'description' => '',
                        'publishedAt' => '2026-01-02T00:00:00Z',
                        'channelId' => 'UCowner',
                        'channelTitle' => 'Owner',
                        'videoOwnerChannelId' => 'UCowner',
                        'videoOwnerChannelTitle' => 'Owner',
                    ],
                    'contentDetails' => [
                        'videoId' => 'v1',
                        'videoPublishedAt' => '2026-01-02T00:00:00Z',
                    ],
                ],
            ],
        ])]);

        /** @var Client $client */
        $client = $this->app->make(Client::class);

        $videos = $client->getAllVideoMetadataForPlaylist('PLabc');

        $this->assertCount(1, $videos);
        $this->assertEquals('A real video', $videos[0]->title);
    }

    #[Test]
    public function it_reports_a_channels_uploads_playlist()
    {
        Http::fake(['*' => Http::response([
            'items' => [
                [
                    'id' => 'UCabc123',
                    'brandingSettings' => ['channel' => ['title' => 'Some Channel']],
                    'contentDetails' => ['relatedPlaylists' => ['uploads' => 'UUabc123']],
                ],
            ],
        ])]);

        /** @var Client $client */
        $client = $this->app->make(Client::class);

        $this->assertEquals('UUabc123', $client->getChannelMetadataForId('UCabc123')->uploadsPlaylistId());
    }

    #[Test]
    public function it_derives_the_uploads_playlist_from_the_channel_id_when_the_api_omits_it()
    {
        $channel = new ChannelMetadata(id: 'UCabc123', name: 'Some Channel');

        $this->assertEquals('UUabc123', $channel->uploadsPlaylistId());
    }

    #[Test]
    public function it_gets_playlist_metadata()
    {
        Http::fake(['*' => Http::response([
            'items' => [
                [
                    'id' => 'PLabc',
                    'snippet' => [
                        'title' => 'Select Lectures',
                        'channelId' => 'UCowner',
                        'channelTitle' => 'Lecture Channel',
                    ],
                    'contentDetails' => ['itemCount' => 42],
                ],
            ],
        ])]);

        /** @var Client $client */
        $client = $this->app->make(Client::class);

        $playlist = $client->getPlaylistMetadata('PLabc');

        $this->assertEquals('Select Lectures', $playlist->title);
        $this->assertEquals(42, $playlist->itemCount);
        // The owning channel is who the feed is "by": a playlist title is a
        // collection name, not an author.
        $this->assertEquals('Lecture Channel', $playlist->channel->name);
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
