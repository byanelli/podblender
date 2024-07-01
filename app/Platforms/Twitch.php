<?php

namespace App\Platforms;

use App\Apis\YtDlp\Client;
use App\Concerns\FixesUrls;
use App\Enums\PlatformType;
use App\Platforms\Contracts\ClipMetadata;
use App\Platforms\Contracts\Platform;
use App\Platforms\Contracts\SourceMetadata;
use App\Platforms\Exceptions\DownloadException;
use App\Platforms\Exceptions\MetadataException;
use League\Uri\Uri;

readonly class Twitch implements Platform
{
    use FixesUrls;

    public function __construct(private Client $ytDlp) {}

    private function getCanonicalUrl2(array $meta): string
    {
        return match ($extractor = $meta['extractor'] ?? '(no extractor provided)') {
            'twitch:vod' => 'https://twitch.tv/videos/'.$meta['webpage_url_basename'],
            'twitch:clips' => 'https://twitch.tv/'.strtolower($meta['uploader']).'/clip/'.$meta['webpage_url_basename'],
            default => throw new \RuntimeException("Invalid extractor: {$extractor}"),
        };
    }

    public function getClipMetadata(string $clipUrl): ClipMetadata
    {
        try {
            $clipUrl = $this->fixUrlSchemeAndHost($clipUrl);

            $meta = $this->ytDlp->getMetadata($clipUrl);

            return new ClipMetadata(
                title: $meta['title'],
                description: '',
                canonicalUrl: $this->getCanonicalUrl2($meta),
                source: new SourceMetadata(
                    name: $meta['uploader'],
                    canonicalUrl: $meta['uploader_url'], // todo: ???
                ),
            );
        } catch (\Exception $e) {
            throw new MetadataException(PlatformType::Twitch, $e);
        }
    }

    public function getSourceMetadata(string $sourceUrl): SourceMetadata
    {
        $sourceUrl = $this->removeUtmCodesFromUrl($this->fixUrlSchemeAndHost($sourceUrl));

        $name = trim(str_replace('/', '', Uri::fromBaseUri($sourceUrl)->getPath()));

        return new SourceMetadata(
            name: $name,
            canonicalUrl: $sourceUrl,
        );
    }

    public function downloadAudio(string $clipUrl): string
    {
        try {
            $clipUrl = $this->fixUrlSchemeAndHost($clipUrl);

            return $this->ytDlp->downloadAudio($clipUrl);
        } catch (\Exception $e) {
            throw new DownloadException(PlatformType::Twitch, $e);
        }
    }

    public function getClipUrlsPublishedSince(string $sourceUrl, \DateTimeInterface $publicationTime): array
    {
        throw new \RuntimeException('Not implemented');
    }
}
