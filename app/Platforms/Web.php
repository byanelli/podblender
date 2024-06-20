<?php

namespace App\Platforms;

use App\Apis\ArticleExtractor\Client as ArticlesApi;
use App\Apis\Whisper\Contracts\Client as WhisperApi;
use App\Concerns\FixesUrls;
use App\Enums\PlatformType;
use App\Platforms\Contracts\Platform;
use App\Platforms\Exceptions\DownloadException;
use App\Platforms\Exceptions\MetadataException;
use League\Uri\Uri;

readonly class Web implements Platform
{
    use FixesUrls;

    public function __construct(
        private ArticlesApi $articleExtractor,
        private WhisperApi $whisper,
    ) {}

    public function getCanonicalUrl(string $url): string {
        return $this->removeUtmCodesFromUrl($this->fixUrlSchemeAndHost($url));
    }

    public function getMetadata(string $url): Metadata {
        try {
            $url = $this->fixUrlSchemeAndHost($url);

            $article = $this->articleExtractor->getArticle($url);

            return new Metadata(
                id: $url,
                title: $article->title,
                description: 'Article by ' . collect($article->authors)->join(' and '),
                sourceId: Uri::fromBaseUri($url)->getHost(),
                sourceName: $article->publisher,
            );
        } catch (\Exception $e) {
            throw new MetadataException(PlatformType::Web, $e);
        }
    }

    public function downloadAudio(string $url): string {
        try {
            $url = $this->fixUrlSchemeAndHost($url);

            $article = $this->articleExtractor->getArticle($url);

            return $this->whisper->convertTextToSpeech($article->text);
        } catch (\Exception $e) {
            throw new DownloadException(PlatformType::Web, $e);
        }
    }
}
