<?php

namespace App\Platforms;

use App\Apis\YtDlp\Client as YtDlpClient;
use App\Apis\YtDlp\Info;
use App\Apis\YtDlp\Site;
use App\Apis\YtDlp\UnavailableContentException;
use App\Enums\AudioSourceType;
use App\Enums\PlatformType;
use App\Platforms\Contracts\ClipMetadata;
use App\Platforms\Contracts\SourceMetadata;
use App\Platforms\Contracts\SubscribablePlatform;
use App\Platforms\Exceptions\PlatformException;
use App\Platforms\Exceptions\PlatformOperation;
use App\Platforms\Exceptions\UnusableLinkException;
use Illuminate\Http\Client\Factory;
use League\Uri\Uri;

/**
 * SoundCloud tracks, and subscriptions to a user's tracks or to a set (a playlist or album).
 */
readonly class SoundCloud extends YtDlpPlatform implements SubscribablePlatform
{
    /**
     * Prefers the progressive MP3, which yt-dlp saves without re-encoding. Excludes the 30-second previews that
     * SoundCloud serves for Go+ tracks, so those fail to download instead of producing a preview.
     */
    private const string FORMAT = 'ba[acodec=mp3][protocol=http][format_id!*=preview]'
        .'/ba[acodec=mp3][format_id!*=preview]'
        .'/ba[format_id!*=preview]';

    /**
     * Skips each track's format list when listing a source, which saves several API requests per track. SoundCloud
     * allows about 600 requests per 10 minutes.
     */
    private const array WITHOUT_FORMATS = [
        '--extractor-args', 'soundcloud:formats=none',
        '--ignore-no-formats-error',
    ];

    /**
     * Second path segments of a user's pages, as in soundcloud.com/<user>/<page>. Any other second segment is a track.
     */
    private const array USER_PAGES = [
        'tracks', 'albums', 'sets', 'popular-tracks', 'reposts', 'likes', 'followers', 'following', 'comments',
        'spotlight',
    ];

    private const int MAX_SHORT_LINK_REDIRECTS = 5;

    public function __construct(YtDlpClient $ytDlp, private Factory $http)
    {
        parent::__construct($ytDlp);
    }

    protected function type(): PlatformType
    {
        return PlatformType::SoundCloud;
    }

    protected function site(): Site
    {
        return new Site(
            name: 'SoundCloud',
            format: self::FORMAT,
            // SoundCloud's bot protection answers with these statuses.
            botWallMarkers: ['HTTP Error 403', 'HTTP Error 429'],
            // "Requested format" is a Go+ track with only a preview. A private track without its token is a 404.
            unavailableMarkers: [
                'Requested format is not available',
                'DRM protected',
                'not available from your location',
                'HTTP Error 404',
            ],
        );
    }

    public function getClipMetadata(string $clipUrl): ClipMetadata
    {
        try {
            $clipUrl = $this->normalizeUrl($clipUrl);

            if (! $this->isTrackUrl($clipUrl)) {
                throw new UnusableLinkException(
                    'That SoundCloud link is to a profile or playlist, not a track. '
                    .'To get all of its tracks, subscribe to it from a new feed.'
                );
            }

            try {
                // The format selector makes a preview-only track fail here rather than at download.
                $info = $this->ytDlp->getInfo($clipUrl, $this->site(), ['--flat-playlist', '--format', self::FORMAT]);
            } catch (UnavailableContentException $e) {
                throw new UnusableLinkException(
                    "This SoundCloud track can't be downloaded. It may be private or deleted, or only available as a "
                    .'preview to SoundCloud Go+ subscribers.',
                    previous: $e,
                );
            }

            return $this->clipMetadataFromInfo($info, $this->uploaderSource($info));
        } catch (\Exception $e) {
            throw new PlatformException($this->type(), PlatformOperation::Metadata, $e);
        }
    }

    public function getSourceMetadata(string $sourceUrl): SourceMetadata
    {
        try {
            $sourceUrl = $this->normalizeUrl($sourceUrl);

            if ($this->isTrackUrl($sourceUrl)) {
                throw new UnusableLinkException(
                    "That SoundCloud link is to a single track. To subscribe, use the link to the artist's profile or "
                    .'to a playlist.'
                );
            }

            if ($this->isSetUrl($sourceUrl)) {
                $info = $this->ytDlp->getInfo($sourceUrl, $this->site(), ['--flat-playlist']);

                return new SourceMetadata(
                    name: $info->title,
                    canonicalUrl: $this->fixUrl($info->webpageUrl),
                    authorName: $info->uploader ?? $info->title,
                    type: AudioSourceType::Playlist,
                    clipCount: $info->playlistCount,
                );
            }

            $tracksUrl = $this->userTracksUrl($sourceUrl);

            // Lists no tracks. The user's name is only in the playlist title, "<name> (Tracks)".
            $info = $this->ytDlp->getInfo($tracksUrl, $this->site(), ['--flat-playlist', '--playlist-items', '0']);

            $name = (string) preg_replace('/ \(Tracks\)$/', '', $info->title);

            return new SourceMetadata(
                name: $name,
                canonicalUrl: $tracksUrl,
                authorName: $name,
                type: AudioSourceType::Channel,
            );
        } catch (\Exception $e) {
            throw new PlatformException($this->type(), PlatformOperation::Metadata, $e);
        }
    }

    /**
     * A user's tracks are listed newest first, so the listing stops at the first older track. A set is in the owner's
     * order, so every track in it is fetched. Either way each track costs one request, since yt-dlp's flat listing
     * has no dates.
     */
    public function getMetadataForAllClipsPublishedSince(string $sourceUrl, \DateTimeInterface $publicationTime): array
    {
        $filter = "timestamp>={$publicationTime->getTimestamp()}";

        $args = $this->isSetUrl($sourceUrl)
            ? ['--match-filters', $filter]
            : ['--lazy-playlist', '--break-match-filters', $filter];

        $entries = $this->ytDlp->getEntries($sourceUrl, $this->site(), array_merge($args, self::WITHOUT_FORMATS));

        return collect($entries)
            ->map(fn (Info $info) => $this->clipMetadataFromInfo($info, $this->uploaderSource($info)))
            ->all();
    }

    /**
     * SoundCloud allows some HTML in descriptions, such as links and entities.
     */
    protected function description(Info $info): string
    {
        $text = html_entity_decode(strip_tags($info->description ?? ''), ENT_QUOTES | ENT_HTML5);

        return trim(str_replace("\u{00A0}", ' ', $text));
    }

    /**
     * A 500px square. The original can be a multi-megabyte PNG.
     */
    protected function thumbnailUrl(Info $info): ?string
    {
        return $info->thumbnails['t500x500'] ?? $info->thumbnail;
    }

    /**
     * A clip's source is its uploader, as with a YouTube video's channel, and is the same source as a subscription to
     * the uploader's profile.
     */
    private function uploaderSource(Info $info): SourceMetadata
    {
        $name = $info->uploader
            ?? throw new \UnexpectedValueException("yt-dlp reported no uploader for $info->webpageUrl");

        $uploaderUrl = $info->uploaderUrl
            ?? throw new \UnexpectedValueException("yt-dlp reported no uploader URL for $info->webpageUrl");

        return new SourceMetadata(
            name: $name,
            canonicalUrl: $this->userTracksUrl($this->normalizeUrl($uploaderUrl)),
            authorName: $name,
            type: AudioSourceType::Channel,
        );
    }

    /**
     * The https://soundcloud.com form of the URL, with no query or fragment. A private track's token is part of the
     * path, so it's kept.
     */
    private function normalizeUrl(string $url): string
    {
        $uri = Uri::new($this->fixUrlSchemeAndHost($url));

        if ($uri->getHost() === 'on.soundcloud.com') {
            $uri = Uri::new($this->fixUrlSchemeAndHost($this->resolveShortLink($uri->toString())));
        }

        $path = rtrim($uri->getPath(), '/');

        return "https://soundcloud.com$path";
    }

    /**
     * yt-dlp has no extractor for short links, so follow their redirects to the full URL.
     */
    private function resolveShortLink(string $url): string
    {
        for ($i = 0; $i < self::MAX_SHORT_LINK_REDIRECTS; $i++) {
            $location = $this->http->withoutRedirecting()->timeout(30)->get($url)->header('Location');

            if ($location === '') {
                break;
            }

            $url = $location;

            if (Uri::new($url)->getHost() !== 'on.soundcloud.com') {
                return $url;
            }
        }

        throw new UnusableLinkException("That SoundCloud short link doesn't lead to a track or profile.");
    }

    /**
     * @return array<int, string>
     */
    private function pathSegments(string $normalizedUrl): array
    {
        return array_values(array_filter(explode('/', Uri::new($normalizedUrl)->getPath())));
    }

    /**
     * /<user>/<track>, or /<user>/<track>/s-<token> for a private track.
     */
    private function isTrackUrl(string $normalizedUrl): bool
    {
        $segments = $this->pathSegments($normalizedUrl);

        return match (count($segments)) {
            2       => ! in_array($segments[1], self::USER_PAGES),
            3       => ! in_array($segments[1], self::USER_PAGES) && str_starts_with($segments[2], 's-'),
            default => false,
        };
    }

    /**
     * /<user>/sets/<set>, or /<user>/sets/<set>/s-<token> for a private set.
     */
    private function isSetUrl(string $normalizedUrl): bool
    {
        $segments = $this->pathSegments($normalizedUrl);

        return count($segments) >= 3 && $segments[1] === 'sets';
    }

    /**
     * A user's own tracks. The profile page itself also lists reposts, which belong to other users.
     */
    private function userTracksUrl(string $normalizedUrl): string
    {
        $user = $this->pathSegments($normalizedUrl)[0]
            ?? throw new UnusableLinkException("That SoundCloud link isn't to a track, profile or playlist.");

        return 'https://soundcloud.com/'.strtolower($user).'/tracks';
    }
}
