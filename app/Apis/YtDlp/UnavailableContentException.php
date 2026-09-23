<?php

namespace App\Apis\YtDlp;

/**
 * yt-dlp's output matched one of the site's unavailable-content markers, such as a members-only video. Retrying won't
 * help.
 */
class UnavailableContentException extends \Exception {}
