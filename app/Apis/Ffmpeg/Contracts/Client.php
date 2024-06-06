<?php

namespace App\Apis\Ffmpeg\Contracts;

interface Client
{
    public function combineMp3s(array $mp3s): string;

    public function getDuration(string $path): int;
}
