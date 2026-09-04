<?php

namespace App\Apis\YtDlp;

use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Process\FakeProcessResult;

/**
 * YouTube refused the download with "Sign in to confirm you're not a bot", which is what it says when it doesn't
 * believe the address the request came from belongs to a person.
 *
 * It extends ProcessFailedException so that everything already catching a failed yt-dlp run keeps catching this, and
 * so that the output that identified the wall travels with the exception.
 */
class BotWallException extends ProcessFailedException
{
    /**
     * For the one case where we know the wall is up and don't run yt-dlp at all. The parent needs a ProcessResult to
     * build its message from, and FakeProcessResult is the framework's implementation that doesn't need a process
     * behind it, so it's the honest way to say "this is what the run we skipped would have told you".
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
