<?php

namespace App\Platforms;

use App\Apis\YtDlp\Client as YtDlpClient;
use App\Apis\YtDlp\Info;
use App\Apis\YtDlp\Site;
use App\Apis\YtDlp\UnavailableContentException;
use App\Concerns\FixesUrls;
use App\Enums\PlatformType;
use App\Platforms\Contracts\ClipMetadata;
use App\Platforms\Contracts\DownloadedAudio;
use App\Platforms\Contracts\Platform;
use App\Platforms\Contracts\RemoteImageThumbnail;
use App\Platforms\Contracts\SourceMetadata;
use App\Platforms\Exceptions\ContentUnavailableException;
use App\Platforms\Exceptions\PlatformException;
use App\Platforms\Exceptions\PlatformOperation;

/**
 * A platform whose audio is downloaded with yt-dlp. Listing a source's clips is left to each platform, since yt-dlp's
 * playlists often have no publication dates and some platforms have an API that is cheaper to poll.
 */
abstract readonly class YtDlpPlatform implements Platform
{
    use FixesUrls;

    public function __construct(protected YtDlpClient $ytDlp) {}

    abstract protected function type(): PlatformType;

    abstract protected function site(): Site;

    public function downloadAudio(string $clipUrl): DownloadedAudio
    {
        try {
            return new DownloadedAudio($this->ytDlp->downloadAudio($this->fixUrlSchemeAndHost($clipUrl), $this->site()));
        } catch (UnavailableContentException $e) {
            throw new ContentUnavailableException;
        } catch (\Exception $e) {
            throw new PlatformException($this->type(), PlatformOperation::Download, $e);
        }
    }

    /**
     * A conservative estimate of one download's wall-clock time, in seconds. yt-dlp downloads an audio track of up to
     * ~160 kbps and throttles its requests, so this assumes 2 Mbps plus fixed overhead. Null when the duration is
     * unknown.
     */
    protected function estimateDownloadTime(int|float|null $durationSeconds): ?int
    {
        if ($durationSeconds === null) {
            return null;
        }

        $audioBytes = $durationSeconds * self::AUDIO_BITRATE_BYTES_PER_SECOND;

        return (int) ceil($audioBytes / self::ASSUMED_DOWNLOAD_BYTES_PER_SECOND)
            + self::DOWNLOAD_OVERHEAD_SECONDS;
    }

    private const AUDIO_BITRATE_BYTES_PER_SECOND = 20_000; // ~160 kbps

    private const ASSUMED_DOWNLOAD_BYTES_PER_SECOND = 250_000; // ~2 Mbps

    private const DOWNLOAD_OVERHEAD_SECONDS = 60;

    protected function clipMetadataFromInfo(Info $info, SourceMetadata $source): ClipMetadata
    {
        $thumbnailUrl = $this->thumbnailUrl($info);

        return new ClipMetadata(
            title: $info->title,
            description: $this->description($info),
            canonicalUrl: $this->canonicalClipUrl($info),
            publishedAt: $info->timestamp
                ?? throw new \UnexpectedValueException("yt-dlp reported no publication time for $info->webpageUrl"),
            source: $source,
            estimatedDownloadTime: $this->estimateDownloadTime($info->durationSeconds),
            thumbnail: $thumbnailUrl === null ? null : new RemoteImageThumbnail($thumbnailUrl),
        );
    }

    protected function description(Info $info): string
    {
        return $info->description ?? '';
    }

    protected function canonicalClipUrl(Info $info): string
    {
        return $this->fixUrl($info->webpageUrl);
    }

    protected function thumbnailUrl(Info $info): ?string
    {
        return $info->thumbnail;
    }
}
