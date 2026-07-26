<?php

namespace App\Apis\Tts\Contracts;

interface Client
{
    /**
     * @return string -- returns the path to an MP3 file
     */
    public function convertTextToSpeech(string $text): string;

    /**
     * A deliberately pessimistic guess at how long convertTextToSpeech() will
     * take for this text, in seconds.
     *
     * Only the backend can answer this: how long narration takes depends on how
     * it splits the text and how much of it it does at once, which is private to
     * each implementation. Callers use it to budget a timeout, so overshooting
     * is cheap and undershooting kills a job mid-narration — err high.
     */
    public function estimateNarrationTime(string $text): int;
}
