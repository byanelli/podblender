<?php

namespace App\Platforms;

use App\Apis\ArticleExtractor\Client as ArticlesApi;
use App\Apis\Whisper\Contracts\Client as WhisperApi;
use App\Helpers;
use App\Platforms\Contracts\Platform;
use League\Uri\Uri;

readonly class Web implements Platform
{
    public function __construct(
        private ArticlesApi $articleExtractor,
        private WhisperApi $whisper,
    ) {}

    public function getCanonicalUrl(string $url): string {
        // todo remove UTM codes etc.
        return $url;
    }

    public function getMetadata(string $url): Metadata {
        $article = $this->articleExtractor->getArticle($url);

        $domain = Helpers::removeWwwFromHost(Uri::fromBaseUri($url)->getHost());

        return new Metadata(
            id: $url,
            title: $article->title,
            description: 'Article by '.collect($article->authors)->join(' and '),
            sourceId: $domain,
            sourceName: $article->publisher,
        );
    }

    public function downloadAudio(string $url): string {
        $article = $this->articleExtractor->getArticle($url);

        return $this->whisper->convertTextToSpeech($article->text);
    }
}
