<?php

namespace App\Platforms\Contracts;

/**
 * Where a clip's artwork comes from. Each platform says this in its own terms —
 * YouTube hands us a URL to fetch, and a narrated article will want a cover we
 * draw ourselves — so the metadata carries the source rather than a finished
 * image, and DownloadAndStoreThumbnail decides what to do with each type.
 *
 * This is a marker with no behaviour of its own, and would be an interface but
 * for one thing: ClipMetadata is a roma response body, and roma's TypeScript
 * generator can't build a definition for an interface-typed property. A base
 * class it can, so a subclass is what a source is.
 *
 * A source is put on a queued job's payload, so keep any subclass to plain
 * values that survive PHP serialisation.
 */
abstract readonly class ThumbnailSource {}
