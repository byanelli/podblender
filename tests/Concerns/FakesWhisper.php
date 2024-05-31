<?php

namespace Tests\Concerns;

use App\Apis\Whisper\Contracts\Client;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

/**
 * @mixin TestCase
 */
trait FakesWhisper
{
    protected function fakeWhisper(?string $mp3=null): void {
        $mp3 ??= sys_get_temp_dir().'/'.Uuid::uuid4().'.mp3';

        $this->app->bind(Client::class, fn() => new readonly class ($mp3) implements Client {
            public function __construct(private string $mp3) {}

            public function convertTextToSpeech(string $text): string {
                file_put_contents($this->mp3, $text);

                return $this->mp3;
            }
        });
    }
}
