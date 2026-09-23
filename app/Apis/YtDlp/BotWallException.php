<?php

namespace App\Apis\YtDlp;

use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Process\FakeProcessResult;

/**
 * The site refused the request because of the source address's reputation, e.g. YouTube's "Sign in to confirm you're
 * not a bot".
 *
 * Extends ProcessFailedException so that code catching a failed yt-dlp run also catches this, and so the exception
 * includes the output that identified the refusal.
 */
class BotWallException extends ProcessFailedException
{
    /**
     * For when the block is already known and yt-dlp isn't run. The parent requires a ProcessResult, and
     * FakeProcessResult is the framework's implementation that needs no process.
     */
    public static function withoutRunningYtDlp(string $url, Site $site): self
    {
        return new self(new FakeProcessResult(
            command: "./yt-dlp $url",
            exitCode: 1,
            errorOutput: "Not run: $site->name is already known to be refusing downloads from this host's address.",
        ));
    }
}
