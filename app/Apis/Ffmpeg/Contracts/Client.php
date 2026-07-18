<?php

namespace App\Apis\Ffmpeg\Contracts;

interface Client
{
    /**
     * @param  array<int, string>  $mp3s
     */
    public function combineMp3s(array $mp3s): string;

    public function getDuration(string $path): int;
}
