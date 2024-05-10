<?php

namespace App\Enums;

use RuntimeException;

enum Platform: int
{
    case YouTube = 1;

    public function formatUrl(string $id): string {
        return match ($this) {
            Platform::YouTube => "https://youtube.com/watch?v=$id",
            default => throw new RuntimeException('Unsupported platform'),
        };
    }
}
