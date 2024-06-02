<?php

namespace App\Platforms;

use App\Apis\YtDlp\Client;
use App\Helpers;
use App\Platforms\Contracts\Platform;
use Illuminate\Support\Collection;
use League\Uri\Uri;

readonly class YouTube implements Platform
{
    public function __construct(
        private Client $ytDlp,
    ) {}

    public function getCanonicalUrl(string $url): string {
        return 'https://youtube.com/watch?v='.$this->getIdFromUrl($url);
    }

    public function getMetadata(string $url): Metadata {
        $meta = $this->ytDlp->getMetadata($url);

        return new Metadata(
            id: $meta['id'],
            title: $meta['title'],
            description: $meta['description'],
            sourceId: $meta['channel_id'],
            sourceName: $meta['channel'],
        );
    }

    private function getIdFromUrl(string $url): string {
        $uri = Uri::fromBaseUri($url);

        $host = Helpers::removeWwwFromHost($uri->getHost());

        if (!collect(['youtube.com', 'm.youtube.com', 'youtu.be', 'youtube-nocookie.com'])->contains($host)) {
            throw new \RuntimeException("Invalid host for YouTube URL: {$host}");
        }

        parse_str($uri->getQuery(), $query);

        if (isset($query['v'])) {
            return $query['v'];
        }

        $splitPathPiece = fn(string $piece): string => explode('&', $piece)[0];

        /** @var Collection $pathPieces */
        $pathPieces =  collect(explode('/', $uri->getPath()))->filter()->values();

        if ($pathPieces->count() == 2 && collect(['watch', 'v', 'embed', 'e', 'shorts', 'live'])->contains($pathPieces->first())) {
            return $splitPathPiece($pathPieces[1]);
        }

        if ($pathPieces->first() == 'oembed' && isset($query['url'])) {
            return $this->getIdFromUrl($query['url']);
        }

        if ($pathPieces->first() == 'attribution_link' && isset($query['u'])) {
            return $this->getIdFromUrl('https://youtube.com'.$query['u']);
        }

        if ($pathPieces->count() == 1) {
            return $splitPathPiece($pathPieces->first());
        }

        throw new \RuntimeException("Cannot parse URL: $url");
    }

    public function downloadAudio(string $url): string {
        return $this->ytDlp->downloadAudio($url);
    }
}
