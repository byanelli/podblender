<?php

namespace App\Platforms;

use App\Apis\Tts\Contracts\Client as TtsApi;
use App\Articles\Article;
use App\Articles\Contracts\Reader as ArticleReader;
use App\Concerns\FixesUrls;
use App\Enums\PlatformType;
use App\Platforms\Contracts\ClipMetadata;
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
     * Which platform this instance reports itself as in errors. Rss extends
     * this class (a feed item is a web article), and its failures should be
     * attributed to Rss, not Web.
     */
    protected function type(): PlatformType
    {
        return PlatformType::Web;
    }

    /**
     * A conservative guess at one download's wall-clock time. Narration runs
     * text-to-speech a segment at a time, each taking far longer than the audio
     * it yields, so scale the article's spoken length by a pessimistic
     * wall-clock-per-audio-second factor and add fixed overhead for fetching.
     */
    private function estimateDownloadTime(Article $article): int
    {
        $spokenSeconds = str_word_count($article->text) / self::WORDS_PER_MINUTE * 60;

        return (int) ceil($spokenSeconds * self::WALL_CLOCK_PER_AUDIO_SECOND)
            + self::DOWNLOAD_OVERHEAD_SECONDS;
    }

    private const WORDS_PER_MINUTE = 190; // Gemini's measured narration rate

    private const WALL_CLOCK_PER_AUDIO_SECOND = 0.4; // TTS is ~2.5x real-time

    private const DOWNLOAD_OVERHEAD_SECONDS = 30;

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
                    canonicalUrl: 'https://'.Uri::new($clipUrl)->getHost()
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
        );
    }

    public function downloadAudio(string $clipUrl): string
    {
        try {
            $clipUrl = $this->fixUrlSchemeAndHost($clipUrl);

            $article = $this->reader->read($clipUrl);

            return $this->tts->convertTextToSpeech($article->text);
        } catch (\Exception $e) {
            throw new PlatformException($this->type(), PlatformOperation::Download, $e);
        }
    }
}
