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
use Illuminate\Http\Client\Factory;
use Spatie\Regex\Regex;

readonly class SoundCloud implements Platform
{
    use FixesUrls;

    public function __construct(
        private Client $ytDlp,
        private Factory $http,
    ) {}

    public function getClipMetadata(string $clipUrl): ClipMetadata
    {
        try {
            $clipUrl = $this->fixUrlSchemeAndHost($clipUrl);

            $meta = $this->ytDlp->getMetadata($clipUrl);

            return new ClipMetadata(
                title: $meta['title'],
                description: $meta['description'] ?: '',
                canonicalUrl: $meta['webpage_url'],
                source: new SourceMetadata(
                    name: $meta['uploader'],
                    canonicalUrl: $meta['uploader_url'],
                ),
            );
        } catch (\Exception $e) {
            throw new MetadataException(PlatformType::SoundCloud, $e);
        }
    }

    public function getSourceMetadata(string $sourceUrl): SourceMetadata
    {
        $page = $this->http->get($sourceUrl)->throw()->body();

        $name = html_entity_decode(trim(Regex::match('/<meta\s+property="twitter:title"\s+content="([^"]+)"/m', $page)->group(1)));
        $url = trim(Regex::match('/<meta\s+property="twitter:url"\s+content="([^"]+)"/m', $page)->group(1));

        return new SourceMetadata(name: $name, canonicalUrl: $url);
    }

    public function downloadAudio(string $clipUrl): string
    {
        try {
            $clipUrl = $this->fixUrlSchemeAndHost($clipUrl);

            return $this->ytDlp->downloadAudio($clipUrl);
        } catch (\Exception $e) {
            throw new DownloadException(PlatformType::SoundCloud, $e);
        }
    }

    public function getClipUrlsPublishedSince(string $sourceUrl, \DateTimeInterface $publicationTime): array
    {
        throw new \RuntimeException('Not implemented');
    }
}
