<?php

namespace App\Apis\YtDlp;

use DateTimeImmutable;

/**
 * The fields of yt-dlp's info JSON that the platforms use, for a single item or a playlist. Most fields are optional
 * and depend on the extractor.
 */
final readonly class Info
{
    /**
     * @param  array<string, string>  $thumbnails  Thumbnail URLs by yt-dlp's thumbnail id, e.g. "t500x500".
     */
    public function __construct(
        public bool $isPlaylist,
        public string $title,
        public string $webpageUrl,
        public ?string $description = null,
        public ?DateTimeImmutable $timestamp = null,
        public ?float $durationSeconds = null,
        public ?string $thumbnail = null,
        public array $thumbnails = [],
        public ?string $uploader = null,
        public ?string $uploaderUrl = null,
        public ?int $playlistCount = null,
    ) {}

    /**
     * @param  array<mixed>  $json
     */
    public static function fromJson(array $json): self
    {
        $string = fn (string $key): ?string => is_string($json[$key] ?? null) && $json[$key] !== ''
            ? $json[$key]
            : null;

        $int = fn (string $key): ?int => is_int($json[$key] ?? null) ? $json[$key] : null;

        $webpageUrl = $string('webpage_url')
            ?? throw new \UnexpectedValueException('yt-dlp returned info without a webpage_url');

        $timestamp = $int('timestamp');
        $duration = $json['duration'] ?? null;

        return new self(
            isPlaylist: ($json['_type'] ?? null) === 'playlist',
            title: $string('title') ?? '',
            webpageUrl: $webpageUrl,
            description: $string('description'),
            timestamp: $timestamp === null ? null : new DateTimeImmutable("@$timestamp"),
            durationSeconds: is_int($duration) || is_float($duration) ? (float) $duration : null,
            thumbnail: $string('thumbnail'),
            thumbnails: self::thumbnailsFromJson($json['thumbnails'] ?? null),
            uploader: $string('uploader'),
            uploaderUrl: $string('uploader_url'),
            playlistCount: $int('playlist_count'),
        );
    }

    /**
     * @return array<string, string>
     */
    private static function thumbnailsFromJson(mixed $thumbnails): array
    {
        if (! is_array($thumbnails)) {
            return [];
        }

        return collect($thumbnails)
            ->filter(fn (mixed $t) => is_array($t) && is_string($t['id'] ?? null) && is_string($t['url'] ?? null))
            ->mapWithKeys(fn (array $t) => [$t['id'] => $t['url']])
            ->all();
    }
}
