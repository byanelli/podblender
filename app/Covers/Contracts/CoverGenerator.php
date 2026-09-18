<?php

namespace App\Covers\Contracts;

interface CoverGenerator
{
    /**
     * Draw square show artwork carrying $title and return the path of a
     * temporary file holding it. The result is a JPEG large enough for Apple
     * Podcasts, which will not accept artwork under 1400 pixels square.
     *
     * The caller owns the file that comes back: it has to store the bytes
     * somewhere and then delete the temporary copy.
     *
     * $variant picks the background out of a small fixed set. Pass something
     * that doesn't change for a given feed — its id — so a feed drawn twice
     * keeps the background its owner has already seen.
     */
    public function generate(string $title, int $variant): string;
}
