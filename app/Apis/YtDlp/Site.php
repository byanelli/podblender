<?php

namespace App\Apis\YtDlp;

/**
 * The settings for one site that the client downloads from.
 */
final readonly class Site
{
    /**
     * @param  string  $name  Used in logs and in the key of the cached direct-download block, so a block by one site
     *                        doesn't send another site's downloads through the proxy.
     * @param  string|null  $format  yt-dlp's -f selector for downloads. Null uses yt-dlp's default, which is the best
     *                               audio when extracting audio.
     * @param  array<int, string>  $botWallMarkers  Phrases in yt-dlp's error output that mean the site refused this
     *                                              host's address. Matched case-insensitively.
     * @param  array<int, string>  $unavailableMarkers  Phrases in yt-dlp's error output that mean the content can't be
     *                                                  downloaded at all. Matched case-insensitively.
     * @param  float|null  $secondsBetweenRequests  How long yt-dlp waits between the requests of one run. Pacing
     *                                              between downloads is done by App\Jobs\DownloadAndStoreAudioClip.
     */
    public function __construct(
        public string $name,
        public ?string $format = null,
        public array $botWallMarkers = [],
        public array $unavailableMarkers = [],
        public ?float $secondsBetweenRequests = null,
    ) {}
}
