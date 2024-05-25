<?php

namespace Tests\Apis\Whisper;

use App\Apis\Ffmpeg\Client as FfmpegClient;
use App\Apis\Whisper\Client as WhisperClient;
use Faker\Factory;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Process;
use OpenAI\Contracts\ClientContract as OpenAiClient;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Audio\SpeechStreamResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClientTest extends TestCase
{
    /**
     * @param string $str
     * @return resource
     */
    private function convertStringToResource(string $str): mixed {
        return fopen('data://text/plain;base64,'.base64_encode($str), 'r');
    }

    /** @noinspection PhpParamsInspection */
    #[Test]
    public function it_converts_text_to_speech() {
        $faker = Factory::create();

        $text = $faker->realText(8000);

        [$firstSegment, $secondSegment] = [substr($text, 0, 4096), substr($text, 4096)];

        $this->app->bind(OpenAiClient::class, fn() => OpenAI::fake([
           new SpeechStreamResponse(new Response(body: $this->convertStringToResource($firstSegment))),
           new SpeechStreamResponse(new Response(body: $this->convertStringToResource($secondSegment))),
        ]));

        $this->app->bind(FfmpegClient::class, fn() => new readonly class ($this->app, Process::fake()) extends FfmpegClient {
            public function combineMp3s(array $mp3s): string {
                return collect($mp3s)->map(fn($mp3) => file_get_contents($mp3))->implode('');
            }
        });

        /** @var WhisperClient $client */
        $client = app(WhisperClient::class);

        $this->assertEquals($text, $client->convertTextToSpeech($text));
    }
}
