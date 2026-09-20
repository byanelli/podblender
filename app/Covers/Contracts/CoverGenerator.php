<?php

namespace App\Covers\Contracts;

interface CoverGenerator
{
    /**
     * Draw square show artwork with $title on it and return the path of a
     * temporary JPEG. Apple Podcasts rejects artwork under 1400 pixels square,
     * so the image is at least that size.
     *
     * The caller must store the file's contents and delete the temporary file.
     *
     * $variant selects the background from a small fixed set. Pass a value
     * that is stable for a feed, such as its id, so a redrawn cover keeps its
     * background.
     */
    public function generate(string $title, int $variant): string;
}
