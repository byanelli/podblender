<?php

namespace App\Platforms;

use App\Apis\YtDlp\Client;
use App\Concerns\FixesUrls;
use App\Enums\PlatformType;
use App\Platforms\Contracts\Platform;
use App\Platforms\Exceptions\DownloadException;
use App\Platforms\Exceptions\MetadataException;

readonly class SoundCloud implements Platform
{
    use FixesUrls;

    public function __construct(private Client $ytDlp) {}

    public function getCanonicalUrl(string $url): string
    {
        $url = $this->fixUrlSchemeAndHost($url);

        return $this->ytDlp->getMetadata($url)['webpage_url'];
    }

    public function getMetadata(string $url): Metadata
    {
        try {
            $url = $this->fixUrlSchemeAndHost($url);

            $meta = $this->ytDlp->getMetadata($url);

            return new Metadata(
                id: $meta['webpage_url'],
                title: $meta['title'],
                description: $meta['description'] ?: '',
                sourceId: $meta['uploader_url'],
                sourceName: $meta['uploader'],
            );
        } catch (\Exception $e) {
            throw new MetadataException(PlatformType::SoundCloud, $e);
        }
    }

    public function downloadAudio(string $url): string
    {
        try {
            $url = $this->fixUrlSchemeAndHost($url);

            return $this->ytDlp->downloadAudio($url);
        } catch (\Exception $e) {
            throw new DownloadException(PlatformType::SoundCloud, $e);
        }
    }
}
