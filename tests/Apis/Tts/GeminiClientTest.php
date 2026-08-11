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
     * Build a streamed (server-sent event) body whose audio deltas carry the
     * given PCM bytes, so the fake ffmpeg hands the narration straight back.
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
        // Short, single-segment input — segmentation itself is covered
        // separately by SegmentsTextTest.
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
        // 500 three-to-four-character words cross the 1500-char segment budget
        // into multiple segments; none may be dropped or split.
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

        // Every word survives, in order, exactly once.
        $this->assertEquals(
            preg_split('/\s+/', $text),
            preg_split('/\s+/', $sentInputs->implode(' '))
        );
    }

    #[Test]
    public function it_reassembles_segments_in_order_across_pools()
    {
        // Enough distinct words to fill several 1500-char segments, so the
        // narration spans more than one concurrent pool. Pooled responses can
        // settle in any order, so this is what guards against the audio being
        // stitched back together shuffled.
        $text = collect(range(1, 2000))->map(fn (int $i) => "word$i")->implode(' ');

        config()->set('services.gemini.api_key', 'test-key');

        Http::fake([
            'generativelanguage.googleapis.com/*' => fn ($request) => Http::response(
                $this->sseBodyFor((string) $request['input'])
            ),
        ]);

        $this->fakeFfmpeg();

        $narrated = app(GeminiClient::class)->convertTextToSpeech($text);

        // The fake ffmpeg concatenates segment audio in the order it's handed
        // them, so the round trip reproduces the original text only if the
        // pooled responses were reassembled in the order they were sent.
        // Compare ignoring whitespace: the space that joined two segments
        // belongs to neither, so it's absent from the concatenation.
        $this->assertEquals(
            preg_replace('/\s+/', '', $text),
            preg_replace('/\s+/', '', $narrated),
        );

        // Guard the premise: this only tests ordering if there was more than
        // one pool's worth of segments.
        $this->assertGreaterThan(3, Http::recorded()->count());
    }

    #[Test]
    public function it_propagates_a_failure_from_any_segment_in_a_pool()
    {
        $text = collect(range(1, 500))->map(fn (int $i) => "word$i")->implode(' ');

        config()->set('services.gemini.api_key', 'test-key');

        // One particular segment always fails, however many times it's retried;
        // the whole narration should fail rather than quietly returning audio
        // with a hole in it.
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
