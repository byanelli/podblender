<?php

namespace App\Apis\Tts;

/**
 * What one narration consumed, as billed by the TTS provider.
 */
readonly class Usage
{
    public function __construct(
        public string $model,
        public int $inputTokens,
        public int $outputTokens,
        // USD. Null when the model has no configured price.
        public ?float $cost,
    ) {}
}
