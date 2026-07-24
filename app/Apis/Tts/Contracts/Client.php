<?php

namespace App\Apis\Tts\Contracts;

interface Client
{
    /**
     * @return string -- returns the path to an MP3 file
     */
    public function convertTextToSpeech(string $text): string;
}
