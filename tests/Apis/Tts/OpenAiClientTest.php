<?php

namespace Tests\Apis\Tts;

use App\Apis\Tts\OpenAiClient;
use GuzzleHttp\Psr7\Response;
use OpenAI\Contracts\ClientContract as OpenAiClientContract;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Audio\SpeechStreamResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\FakesFfmpeg;
use Tests\TestCase;

class OpenAiClientTest extends TestCase
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
        // Short, single-segment input — segmentation itself is covered
        // separately by SegmentsTextTest.
        $text = 'Have a wonderful day!';

        $this->app->bind(OpenAiClientContract::class, fn () => OpenAI::fake([
            new SpeechStreamResponse(new Response(body: $this->convertStringToResource($text))),
        ]));

        $this->fakeFfmpeg();

        /** @var OpenAiClient $client */
        $client = app(OpenAiClient::class);

        $this->assertEquals($text, $client->convertTextToSpeech($text));
    }
}
