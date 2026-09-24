<?php

namespace Tests\Concerns;

use App\Apis\Tts\Contracts\Client;
use App\Apis\Tts\Narration;
use App\Apis\Tts\Usage;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

/**
 * @mixin TestCase
 */
trait FakesTts
{
    /**
     * Bind a fake TTS backend. Returns the narration time it reports, for
     * tests that assert on a download estimate.
     */
    protected function fakeTts(?string $mp3 = null, int $narrationSeconds = 480, ?Usage $usage = null): int
    {
        $mp3 ??= sys_get_temp_dir().'/'.Uuid::uuid4().'.mp3';

        $this->app->bind(Client::class, fn () => new readonly class($mp3, $narrationSeconds, $usage) implements Client
        {
            public function __construct(private string $mp3, private int $narrationSeconds, private ?Usage $usage) {}

            public function convertTextToSpeech(string $text): Narration
            {
                file_put_contents($this->mp3, $text);

                return new Narration($this->mp3, $this->usage);
            }

            public function estimateNarrationTime(string $text): int
            {
                return $this->narrationSeconds;
            }
        });

        return $narrationSeconds;
    }
}
