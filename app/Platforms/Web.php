<?php

namespace App\Platforms;

use App\Apis\ArticleExtractor\Client as ArticlesApi;
use App\Apis\Whisper\Contracts\Client as WhisperApi;
use App\Concerns\FixesUrls;
use App\Enums\PlatformType;
use App\Platforms\Contracts\ClipMetadata;
use App\Platforms\Contracts\Platform;
use App\Platforms\Contracts\SourceMetadata;
use App\Platforms\Exceptions\DownloadException;
use App\Platforms\Exceptions\MetadataException;
use Illuminate\Http\Client\Factory;
use League\Uri\Uri;
use Spatie\Regex\Regex;

readonly class Web implements Platform
{
    use FixesUrls;

    public function __construct(
        private ArticlesApi $articleExtractor,
        private WhisperApi $whisper,
        private Factory $http,
    ) {}

    public function getClipMetadata(string $clipUrl): ClipMetadata
    {
        try {
            $clipUrl = $this->removeUtmCodesFromUrl($this->fixUrlSchemeAndHost($clipUrl));

            $article = $this->articleExtractor->getArticle($clipUrl);

            return new ClipMetadata(
                title: $article->title,
                description: 'Article by '.collect($article->authors)->join(' and '),
                canonicalUrl: $clipUrl,
                source: new SourceMetadata(
                    name: $article->publisher,
                    canonicalUrl: 'https://'.Uri::fromBaseUri($clipUrl)->getHost()
                ),
            );
        } catch (\Exception $e) {
            throw new MetadataException(PlatformType::Web, $e);
        }
    }

    public function getSourceMetadata(string $sourceUrl): SourceMetadata
    {
        $sourceUrl = $this->removeUtmCodesFromUrl($this->fixUrlSchemeAndHost($sourceUrl));

        $page = $this->http->get($sourceUrl)->throw()->body();

        $name = html_entity_decode(trim(Regex::match('/>([^<]+)<\/title>/m', $page)->group(1)));

        return new SourceMetadata(
            name: $name,
            canonicalUrl: $sourceUrl,
        );
    }

    public function downloadAudio(string $clipUrl): string
    {
        try {
            $clipUrl = $this->fixUrlSchemeAndHost($clipUrl);

            $article = $this->articleExtractor->getArticle($clipUrl);

            return $this->whisper->convertTextToSpeech($article->text);
        } catch (\Exception $e) {
            throw new DownloadException(PlatformType::Web, $e);
        }
    }

    public function getClipUrlsPublishedSince(string $sourceUrl, \DateTimeInterface $publicationTime): array
    {
        throw new \RuntimeException('Not implemented');
    }
}
