<?php

namespace App\Support;

use Illuminate\Support\Facades\Config;

/**
 * Decides whether stored audio can be previewed in the browser.
 *
 * In-browser playback needs a storage URL the browser can fetch directly. On
 * S3, or any disk that requires signed URLs, the plain storage URL returns
 * 403, so preview is limited to local disks. This affects only
 * AudioClip::$preview_url; the RSS enclosure uses $audio_url, which is always
 * populated.
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
