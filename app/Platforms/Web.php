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

    public function getMetadata(string $id): Metadata {
        $article = $this->articleExtractor->getArticle($id);

        $domain = Helpers::removeWwwFromHost(Uri::fromBaseUri($id)->getHost());

        return new Metadata(
            id: $id,
            title: $article->title,
            description: 'Article by '.collect($article->authors)->join(' and '),
            sourceId: $domain,
            sourceName: $article->publisher,
        );
    }

    public function getIdFromUrl(string $url): string {
        return $url;
    }

    public function getUrlFromId(string $id): string {
        return $id;
    }

    public function downloadAudio(string $id): string {
        $article = $this->articleExtractor->getArticle($id);

        return $this->whisper->convertTextToSpeech($article->text);
    }
}
