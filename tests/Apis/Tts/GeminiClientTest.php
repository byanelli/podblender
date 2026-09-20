<?php

namespace Tests\Apis\Tts;

use App\Apis\Tts\GeminiClient;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\FakesFfmpeg;
use Tests\TestCase;

class GeminiClientTest extends TestCase
{
    use FakesFfmpeg;

    /**
     * Build a server-sent event body with $pcm as its audio delta. The tests
     * pass the input text as $pcm, so with the fake ffmpeg the returned
     * narration equals the text sent.
     */
    private function sseBodyFor(string $pcm, int $sampleRate = 24000): string
    {
        $delta = json_encode([
            'index' => 0,
            'delta' => [
                'type'        => 'audio',
                'mime_type'   => 'audio/l16',
                'sample_rate' => $sampleRate,
                'channels'    => 1,
                'data'        => base64_encode($pcm),
            ],
        ]);

        return "event: step.delta\ndata: $delta\n\n"
            ."event: done\ndata: {}\n\n";
    }

    #[Test]
    public function it_converts_text_to_speech()
    {
        // A single segment. SegmentsTextTest covers segmentation.
        $text = 'Have a wonderful day!';

        config()->set('services.gemini.api_key', 'test-key');
        config()->set('services.gemini.tts.model', 'gemini-3.1-flash-tts-preview');
        config()->set('services.gemini.tts.voice', 'Aoede');

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($this->sseBodyFor($text)),
        ]);

        $this->fakeFfmpeg();

        /** @var GeminiClient $client */
        $client = app(GeminiClient::class);

        $this->assertEquals($text, $client->convertTextToSpeech($text));

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->url() === 'https://generativelanguage.googleapis.com/v1beta/interactions'
            && $request->hasHeader('x-goog-api-key', 'test-key')
            && $request['model'] === 'gemini-3.1-flash-tts-preview'
            && $request['input'] === $text
            && $request['response_format'] === ['type' => 'audio']
            && $request['stream'] === true
            && $request['generation_config']['speech_config'][0]['voice'] === 'Aoede');
    }

    #[Test]
    public function it_concatenates_audio_across_multiple_stream_deltas()
    {
        config()->set('services.gemini.api_key', 'test-key');

        $deltas = collect(['Hello ', 'there, ', 'world!'])
            ->map(fn (string $chunk) => 'data: '.json_encode([
                'delta' => ['type' => 'audio', 'data' => base64_encode($chunk)],
            ]))
            ->implode("\n\n");

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($deltas."\n\n"),
        ]);

        $this->fakeFfmpeg();

        $this->assertEquals(
            'Hello there, world!',
            app(GeminiClient::class)->convertTextToSpeech('short')
        );
    }

    #[Test]
    public function it_splits_long_text_into_word_boundary_segments()
    {
        // About 2,400 characters, which exceeds the 1500-char segment budget.
        $text = collect(range(1, 500))->map(fn ($i) => "w$i")->implode(' ');

        config()->set('services.gemini.api_key', 'test-key');

        Http::fake([
            'generativelanguage.googleapis.com/*' => fn ($request) => Http::response(
                $this->sseBodyFor((string) $request['input'])
            ),
        ]);

        $this->fakeFfmpeg();

        app(GeminiClient::class)->convertTextToSpeech($text);

        $sentInputs = collect(Http::recorded())->map(fn ($pair) => (string) $pair[0]['input'])->values();

        $this->assertGreaterThan(1, $sentInputs->count());
        $sentInputs->each(fn (string $input) => $this->assertLessThanOrEqual(1500, strlen($input)));

        // Every word is sent once, in order, and none is split.
        $this->assertEquals(
            preg_split('/\s+/', $text),
            preg_split('/\s+/', $sentInputs->implode(' '))
        );
    }

    #[Test]
    public function it_reassembles_segments_in_order_across_pools()
    {
        // Enough distinct words to need more than one pool of concurrent
        // requests. Pooled responses can complete in any order.
        $text = collect(range(1, 2000))->map(fn (int $i) => "word$i")->implode(' ');

        config()->set('services.gemini.api_key', 'test-key');

        Http::fake([
            'generativelanguage.googleapis.com/*' => fn ($request) => Http::response(
                $this->sseBodyFor((string) $request['input'])
            ),
        ]);

        $this->fakeFfmpeg();

        $narrated = app(GeminiClient::class)->convertTextToSpeech($text);

        // The fake ffmpeg concatenates segments in the order given, so the
        // output equals the input only if the responses were reassembled in
        // request order. Whitespace is ignored because the space between two
        // segments is in neither.
        $this->assertEquals(
            preg_replace('/\s+/', '', $text),
            preg_replace('/\s+/', '', $narrated),
        );

        // A pool is 3 requests, so more than 3 means more than one pool ran.
        $this->assertGreaterThan(3, Http::recorded()->count());
    }

    #[Test]
    public function it_propagates_a_failure_from_any_segment_in_a_pool()
    {
        $text = collect(range(1, 500))->map(fn (int $i) => "word$i")->implode(' ');

        config()->set('services.gemini.api_key', 'test-key');

        // One segment fails on every attempt. The narration must fail, because
        // succeeding would return audio with that segment missing.
        Http::fake([
            'generativelanguage.googleapis.com/*' => function ($request) {
                return str_contains((string) $request['input'], 'word2 ')
                    ? Http::response('nope', 500)
                    : Http::response($this->sseBodyFor((string) $request['input']));
            },
        ]);

        $this->fakeFfmpeg();

        $this->expectException(\Throwable::class);

        app(GeminiClient::class)->convertTextToSpeech($text);
    }

    #[Test]
    public function it_throws_when_the_response_has_no_audio()
    {
        config()->set('services.gemini.api_key', 'test-key');

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response("event: done\ndata: {}\n\n"),
        ]);

        $this->fakeFfmpeg();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no audio');

        app(GeminiClient::class)->convertTextToSpeech('hello world');
    }
}
