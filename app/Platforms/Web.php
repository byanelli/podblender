<?php

namespace App\Platforms;

use App\Apis\ArticleExtractor\Client as ArticlesApi;
use App\Apis\Whisper\Client as WhisperApi;

readonly class Web
{
    public function __construct(
        private ArticlesApi $articlesApi,
        private WhisperApi $whisperApi
    ) {}

    public function downloadAudio(string $url): string {
        $article = $this->articlesApi->getArticle($url);

        return $this->whisperApi->convertTextToSpeech($article->text);
    }
}
