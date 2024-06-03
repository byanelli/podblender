<?php

namespace App\Platforms;

use App\Apis\ArticleExtractor\Client as ArticlesApi;
use App\Apis\Whisper\Contracts\Client as WhisperApi;
use App\Concerns\FixesUrls;
use App\Platforms\Contracts\Platform;
use League\Uri\Uri;

readonly class Web implements Platform
{
    use FixesUrls;

    public function __construct(
        private ArticlesApi $articleExtractor,
        private WhisperApi $whisper,
    ) {}

    public function getCanonicalUrl(string $url): string {
        $url = $this->fixUrlSchemeAndHost($url);

        // todo remove UTM codes etc.
        return $url;
    }

    public function getMetadata(string $url): Metadata {
        $url = $this->fixUrlSchemeAndHost($url);

        $article = $this->articleExtractor->getArticle($url);

        return new Metadata(
            id: $url,
            title: $article->title,
            description: 'Article by '.collect($article->authors)->join(' and '),
            sourceId: Uri::fromBaseUri($url)->getHost(),
            sourceName: $article->publisher,
        );
    }

    public function downloadAudio(string $url): string {
        $url = $this->fixUrlSchemeAndHost($url);

        $article = $this->articleExtractor->getArticle($url);

        return $this->whisper->convertTextToSpeech($article->text);
    }
}
