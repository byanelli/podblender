<?php

namespace App\Support;

use Illuminate\Support\Facades\Config;

/**
 * Decides whether stored audio can be previewed in the browser.
 *
 * In-browser playback requires the file to be served to the browser directly,
 * which only holds for local disks. On S3 (or any disk behind signing) the
 * plain storage URL 403s, so preview is gated to those disks. This decides
 * AudioClip::$preview_url only; the RSS enclosure uses $audio_url, which is
 * always populated.
 */
final class AudioPreview
{
    public static function available(): bool
    {
        if (! Config::boolean('audio-preview.enabled')) {
            return false;
        }

        $disk = Config::get('filesystems.default');
        $driver = Config::get("filesystems.disks.{$disk}.driver");

        return in_array(
            $driver,
            Config::get('audio-preview.local_drivers', ['local']),
            true
        );
    }
}
