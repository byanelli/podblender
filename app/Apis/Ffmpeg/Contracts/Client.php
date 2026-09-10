<?php

namespace App\Apis\Ffmpeg\Contracts;

interface Client
{
    /**
     * @param  array<int, string>  $mp3s
     */
    public function combineMp3s(array $mp3s): string;

    /**
     * Encode a raw, headerless PCM file (signed 16-bit little-endian, mono) to
     * MP3, returning the path to the new file.
     */
    public function pcmToMp3(string $pcm, int $sampleRate): string;

    /**
     * Crop an image to a centred square and re-encode it as JPEG at most
     * $maxSide on a side, returning the path to the new file.
     */
    public function imageToSquareJpeg(string $inputPath, int $maxSide = 1400): string;

    public function getDuration(string $path): int;
}
