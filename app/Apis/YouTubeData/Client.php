<?php

namespace App\Apis\YouTubeData;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

readonly class Client implements Contracts\Client
{
    private const string BASE_URL = 'https://www.googleapis.com/youtube/v3';

    public function __construct(
        private Factory $http,
        private Config $config,
    ) {}

    private function getApiKey(): string
    {
        return $this->config->get('services.youtube_data_api.key');
    }

    private function apiGet(string $url, array $params): array
    {
        return $this->http->get(
            url: self::BASE_URL.'/'.$url,
            query: array_merge($params, ['key' => $this->getApiKey()]),
        )->throw()->json();
    }

    private function apiGetAllPages(string $url, int $itemsLimit, array $queryParams): array
    {
        $pages = [];
        $count = 0;
        $nextPageToken = null;

        while (true) {
            if (! is_null($nextPageToken)) {
                $queryParams['pageToken'] = $nextPageToken;
            }

            $page = $this->apiGet($url, $queryParams);

            $pages[] = $page;
            $count += count($page['items']);
            $nextPageToken = $page['nextPageToken'] ?? null;

            if (($count >= $itemsLimit) || is_null($nextPageToken)) {
                break;
            }
        }

        return $pages;
    }

    private function apiGetAllPagesItems(string $url, int $itemsLimit, array $queryParams): array
    {
        $pages = $this->apiGetAllPages($url, $itemsLimit, $queryParams);

        return collect($pages)->reduce(function ($items, $nextPage) {
            return array_merge($items, $nextPage['items']);
        }, []);
    }

    private function getVideoIdsFromPages(array $pages): array
    {
        return collect($pages)->reduce(function (Collection $ids, array $nextPage) {
            $nextPageIds = collect($nextPage['items'])->pluck('id.videoId')->all();

            return $ids->merge($nextPageIds);
        }, collect())->all();
    }

    public function getVideoIdsForChannel(
        string $channelId,
        ?DateTimeInterface $publishedAfter = null,
        ?int $limit = null,
    ): array {
        $publishedAfter ??= CarbonImmutable::parse(0);
        $limit ??= PHP_INT_MAX;

        $pages = $this->apiGetAllPages('search', $limit, [
            // "maxResults" means results per page; if limit <= 50 we only get one page.
            'maxResults' => min($limit, 50),
            'type' => 'video',
            'part' => 'snippet',
            'order' => 'date',
            'channelId' => $channelId,
            'publishedAfter' => $publishedAfter->format(DateTimeInterface::RFC3339),
        ]);

        $videoIds = $this->getVideoIdsFromPages($pages);

        return Arr::take($videoIds, $limit);
    }

    private function getChannelMetadataFromResponse(array $response): ChannelMetadata
    {
        $channel = $response['items'][0];

        return new ChannelMetadata(
            id: $channel['id'],
            name: $channel['brandingSettings']['channel']['title'],
        );
    }

    public function getChannelMetadataForHandle(string $channelHandle): ChannelMetadata
    {
        $response = $this->apiGet('channels', [
            'forHandle' => $channelHandle,
            'part' => 'id,brandingSettings',
        ]);

        return $this->getChannelMetadataFromResponse($response);
    }

    public function getChannelMetadataForId(string $channelId): ChannelMetadata
    {
        $response = $this->apiGet('channels', [
            'id' => $channelId,
            'part' => 'id,brandingSettings,contentDetails,contentOwnerDetails,status,snippet',
        ]);

        return $this->getChannelMetadataFromResponse($response);
    }

    public function getVideoMetadata(string $videoId): VideoMetadata
    {
        $response = $this->apiGet('videos', [
            'id' => $videoId,
            'part' => 'id,snippet,status',
        ]);

        return $this->getVideoMetadataFromResponseObject($response['items'][0]);
    }

    public function getAllVideoMetadataForChannel(
        string $channelId,
        ?DateTimeInterface $publishedAfter = null
    ): array {
        $publishedAfter ??= CarbonImmutable::parse(0);

        $videos = $this->apiGetAllPagesItems('search', PHP_INT_MAX, [
            'maxResults' => 50,
            'type' => 'video',
            'order' => 'date',
            'channelId' => $channelId,
            'publishedAfter' => $publishedAfter->format(DateTimeInterface::RFC3339),
            'part' => 'id,snippet',
        ]);

        return collect($videos)
            ->map(fn ($response) => $this->getVideoMetadataFromResponseObject($response, true))
            ->all();
    }

    private function getVideoMetadataFromResponseObject(
        array $video,
        // YouTube HTML-encodes titles in some responses but not others!?
        bool $decodeTitle = false,
    ): VideoMetadata {
        // For single video responses, id is stored directly as a string; for search responses, it's inside an
        // object.
        $id = is_array($video['id']) ? $video['id']['videoId'] : $video['id'];

        $snippet = $video['snippet'];

        return new VideoMetadata(
            id: $id,
            title: $decodeTitle ? html_entity_decode($snippet['title']) : $snippet['title'],
            description: $snippet['description'],
            publishedAt: CarbonImmutable::parse($snippet['publishedAt']),
            channel: new ChannelMetadata(
                id: $snippet['channelId'],
                name: $snippet['channelTitle'],
            ),
        );
    }
}
