<?php

namespace App\Platforms;

use App\Apis\Tts\Contracts\Client as TtsApi;
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
