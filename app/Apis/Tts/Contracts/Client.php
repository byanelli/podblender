<?php

namespace App\Apis\Tts\Contracts;

use App\Apis\Tts\Narration;

interface Client
{
    public function convertTextToSpeech(string $text): Narration;

    /**
     * A high estimate, in seconds, of how long convertTextToSpeech() will take
     * for this text.
     *
     * It depends on segment size and concurrency, which are private to each
     * implementation. Callers use it to set a timeout, and an estimate that is
     * too low ends a job mid-narration, so implementations round up.
     */
    public function estimateNarrationTime(string $text): int;
}
