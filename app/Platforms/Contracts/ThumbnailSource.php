<?php

namespace App\Platforms\Contracts;

/**
 * Where a clip's artwork comes from. Each platform says this in its own terms —
 * YouTube hands us a URL to fetch, and a narrated article will want a cover we
 * draw ourselves — so the metadata carries the source rather than a finished
 * image, and DownloadAndStoreThumbnail decides what to do with each type.
 *
 * A source is put on a queued job's payload, so keep every implementation to
 * plain values that survive PHP serialisation.
 */
interface ThumbnailSource {}
