<?php

namespace Tests\Apis\Whisper;

use App\Apis\Whisper\Client as WhisperClient;
use Faker\Factory as FakerFactory;
use GuzzleHttp\Psr7\Response;
use OpenAI\Contracts\ClientContract as OpenAiClient;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Audio\SpeechStreamResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\FakesFfmpeg;
use Tests\TestCase;

class ClientTest extends TestCase
{
    use FakesFfmpeg;

    /**
     * @return resource
     */
    private function convertStringToResource(string $str): mixed
    {
        return fopen('data://text/plain;base64,'.base64_encode($str), 'r');
    }

    /** @noinspection PhpParamsInspection */
    #[Test]
    public function it_converts_text_to_speech()
    {
        $text = FakerFactory::create()->realText(8000);

        [$firstSegment, $secondSegment] = [substr($text, 0, 4096), substr($text, 4096)];

        $this->app->bind(OpenAiClient::class, fn () => OpenAI::fake([
            new SpeechStreamResponse(new Response(body: $this->convertStringToResource($firstSegment))),
            new SpeechStreamResponse(new Response(body: $this->convertStringToResource($secondSegment))),
        ]));

        $this->fakeFfmpeg();

        /** @var WhisperClient $client */
        $client = app(WhisperClient::class);

        $this->assertEquals($text, $client->convertTextToSpeech($text));
    }
}
