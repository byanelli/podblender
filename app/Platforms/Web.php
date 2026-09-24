<?php

namespace App\Platforms;

use App\Apis\Tts\Contracts\Client as TtsApi;
use App\Articles\Article;
use App\Articles\Contracts\Reader as ArticleReader;
use App\Concerns\FixesUrls;
use App\Enums\PlatformType;
use App\Platforms\Contracts\ClipMetadata;
use App\Platforms\Contracts\DownloadedAudio;
use App\Platforms\Contracts\Platform;
use App\Platforms\Contracts\SourceMetadata;
use App\Platforms\Exceptions\PlatformException;
use App\Platforms\Exceptions\PlatformOperation;
use Illuminate\Http\Client\Factory;
use League\Uri\Uri;
use Spatie\Regex\Regex;

readonly class Web implements Platform
{
    use FixesUrls;

    public function __construct(
        protected ArticleReader $reader,
        protected TtsApi $tts,
        protected Factory $http,
    ) {}

    /**
     * The platform type reported in this instance's exceptions. Rss overrides it.
     */
    protected function type(): PlatformType
    {
        return PlatformType::Web;
    }

    /**
     * A conservative estimate of one download's wall-clock time, in seconds.
     * Narration is nearly all of it, and its cost depends on how the TTS
     * backend splits and batches the text, so the estimate comes from there.
     * The overhead covers fetching the article again at download time.
     */
    private function estimateDownloadTime(Article $article): int
    {
        return $this->tts->estimateNarrationTime($article->text)
            + self::FETCH_OVERHEAD_SECONDS;
    }

    private const FETCH_OVERHEAD_SECONDS = 30;

    public function getClipMetadata(string $clipUrl): ClipMetadata
    {
        try {
            $clipUrl = $this->removeUtmCodesFromUrl($this->fixUrlSchemeAndHost($clipUrl));

            $article = $this->reader->read($clipUrl);

            return new ClipMetadata(
                title: $article->title,
                description: 'Article by '.collect($article->authors)->join(' and '),
                canonicalUrl: $clipUrl,
                publishedAt: $article->publicationDate,
                source: new SourceMetadata(
                    name: $article->publisher,
                    canonicalUrl: 'https://'.Uri::new($clipUrl)->getHost(),
                    authorName: $article->publisher,
                ),
                estimatedDownloadTime: $this->estimateDownloadTime($article),
            );
        } catch (\Exception $e) {
            throw new PlatformException($this->type(), PlatformOperation::Metadata, $e);
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
            authorName: $name,
        );
    }

    public function downloadAudio(string $clipUrl): DownloadedAudio
    {
        try {
            $clipUrl = $this->fixUrlSchemeAndHost($clipUrl);

            $article = $this->reader->read($clipUrl);

            $narration = $this->tts->convertTextToSpeech($article->text);

            return new DownloadedAudio($narration->path, $narration->usage);
        } catch (\Exception $e) {
            throw new PlatformException($this->type(), PlatformOperation::Download, $e);
        }
    }
}
