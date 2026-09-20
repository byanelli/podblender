<?php

namespace App\Platforms\Contracts;

/**
 * The source of a clip's artwork, which differs by platform: YouTube gives a
 * URL to fetch. DownloadAndStoreThumbnail handles each type.
 *
 * A source is serialized into a queued job's payload, so implementations
 * contain only plain values.
 */
interface ThumbnailSource {}
