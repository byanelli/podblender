<?php

namespace App\Apis\Whisper\Contracts;

interface Client
{
    /**
     * @param string $text
     * @return string -- returns the path to an MP3 file
     */
    public function convertTextToSpeech(string $text): string;
}
