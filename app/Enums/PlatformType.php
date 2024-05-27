<?php

namespace App\Enums;

use RuntimeException;

enum PlatformType: int
{
    case YouTube = 1;

    public function formatUrl(string $id): string {
        return match ($this) {
            PlatformType::YouTube => "https://youtube.com/watch?v=$id",
            default => throw new RuntimeException('Unsupported platform'),
        };
    }
}
