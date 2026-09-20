<?php

namespace App\Apis\YtDlp;

use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Process\FakeProcessResult;

/**
 * YouTube refused the download with "Sign in to confirm you're not a bot", its response to a source address with a
 * poor reputation.
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
    public static function withoutRunningYtDlp(string $url): self
    {
        return new self(new FakeProcessResult(
            command: "./yt-dlp $url",
            exitCode: 1,
            errorOutput: "Not run: YouTube is already known to be refusing downloads from this host's address.",
        ));
    }
}
