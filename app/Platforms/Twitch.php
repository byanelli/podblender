<?php

namespace App\Platforms;

use App\Apis\YtDlp\Client;
use App\Concerns\FixesUrls;
use App\Platforms\Contracts\Platform;

readonly class Twitch implements Platform
{
    use FixesUrls;

    public function __construct(private Client $ytDlp) {}

    public function getCanonicalUrl(string $url): string {
        $url = $this->fixUrlSchemeAndHost($url);

        $meta = $this->ytDlp->getMetadata($url);

        return match ($meta['extractor'] ?? null) {
            'twitch:vod' => 'https://twitch.tv/videos/'.$meta['webpage_url_basename'],
            'twitch:clips' => 'https://twitch.tv/'.strtolower($meta['uploader']).'/clip/'.$meta['webpage_url_basename'],
            default => throw new \RuntimeException('zzz'),
        };
    }

    public function getMetadata(string $url): Metadata {
        $url = $this->fixUrlSchemeAndHost($url);

        $meta = $this->ytDlp->getMetadata($url);

        return new Metadata(
            id: $meta['id'],
            title: $meta['title'],
            description: '',
            sourceId: $meta['uploader_id'],
            sourceName: $meta['uploader'],
        );
    }

    public function downloadAudio(string $url): string {
        $url = $this->fixUrlSchemeAndHost($url);

        return $this->ytDlp->downloadAudio($url);
    }
}
