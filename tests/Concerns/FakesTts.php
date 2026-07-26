<?php

namespace Tests\Concerns;

use App\Apis\Tts\Contracts\Client;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

/**
 * @mixin TestCase
 */
trait FakesTts
{
    /**
     * Bind a stand-in TTS backend. Returns the narration time it reports, so a
     * test asserting on a download estimate has a number to expect without
     * duplicating it.
     */
    protected function fakeTts(?string $mp3 = null, int $narrationSeconds = 480): int
    {
        $mp3 ??= sys_get_temp_dir().'/'.Uuid::uuid4().'.mp3';

        $this->app->bind(Client::class, fn () => new readonly class($mp3, $narrationSeconds) implements Client
        {
            public function __construct(private string $mp3, private int $narrationSeconds) {}

            public function convertTextToSpeech(string $text): string
            {
                file_put_contents($this->mp3, $text);

                return $this->mp3;
            }

            public function estimateNarrationTime(string $text): int
            {
                return $this->narrationSeconds;
            }
        });

        return $narrationSeconds;
    }
}
