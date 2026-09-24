<?php

namespace Tests\Apis\Tts;

use App\Apis\Tts\GeminiClient;
use App\Apis\Tts\SegmentCache;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\FakesFfmpeg;
use Tests\TestCase;

class GeminiClientTest extends TestCase
{
    use FakesFfmpeg;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(SegmentCache::DISK);
    }

    /**
     * Build a server-sent event body with $pcm as its audio delta. The tests
     * pass the input text as $pcm, so with the fake ffmpeg the returned
     * narration equals the text sent.
     */
    private function sseBodyFor(string $pcm, int $sampleRate = 24000, ?array $usage = null): string
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

        $completed = $usage === null ? '' : 'event: interaction.completed'."\n".'data: '.json_encode([
            'interaction' => [
                'status' => 'completed',
                'usage'  => ['total_input_tokens' => $usage[0], 'total_output_tokens' => $usage[1]],
            ],
            'event_type'  => 'interaction.completed',
        ])."\n\n";

        return "event: step.delta\ndata: $delta\n\n"
            .$completed
            ."event: done\ndata: {}\n\n";
    }

    /**
     * Fake a response to every segment that reports 100 input and 1,000 output tokens.
     */
    private function fakeSegmentsWithUsage(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => fn ($request) => Http::response(
                $this->sseBodyFor((string) $request['input'], usage: [100, 1000])
            ),
        ]);

        $this->fakeFfmpeg();
    }

    #[Test]
    public function it_totals_usage_across_segments_and_prices_it()
    {
        $text = collect(range(1, 500))->map(fn (int $i) => "word$i")->implode(' ');

        config()->set('services.gemini.tts.model', 'gemini-3.8-flash-lite-tts');
        config()->set('services.gemini.tts.prices', ['gemini-3.8-flash-lite-tts' => [
            '2026-01-01' => ['input' => 0.50, 'output' => 6.00],
            '2027-01-01' => ['input' => 1.00, 'output' => 12.00],
        ]]);

        $this->fakeSegmentsWithUsage();

        $this->travelTo(CarbonImmutable::parse('2026-12-31 23:00'));

        $usage = app(GeminiClient::class)->convertTextToSpeech($text)->usage;

        $segments = Http::recorded()->count();
        $this->assertGreaterThan(1, $segments);
        $this->assertEquals('gemini-3.8-flash-lite-tts', $usage->model);
        $this->assertEquals($segments * 100, $usage->inputTokens);
        $this->assertEquals($segments * 1000, $usage->outputTokens);
        $this->assertEqualsWithDelta($segments * (100 * 0.50 + 1000 * 6.00) / 1_000_000, $usage->cost, 1e-9);

        $this->travelTo(CarbonImmutable::parse('2027-01-01 01:00'));

        $this->assertEqualsWithDelta(
            $segments * (100 * 1.00 + 1000 * 12.00) / 1_000_000,
            app(GeminiClient::class)->convertTextToSpeech($text)->usage->cost,
            1e-9,
        );
    }

    #[Test]
    public function it_reports_tokens_without_a_cost_for_a_model_with_no_price()
    {
        config()->set('services.gemini.tts.model', 'gemini-9-unpriced-tts');

        $this->fakeSegmentsWithUsage();

        $usage = app(GeminiClient::class)->convertTextToSpeech('hello world')->usage;

        $this->assertEquals(1000, $usage->outputTokens);
        $this->assertNull($usage->cost);
    }

    #[Test]
    public function it_reports_no_usage_if_any_segment_omits_it()
    {
        $text = collect(range(1, 500))->map(fn (int $i) => "word$i")->implode(' ');

        // A total missing a segment would understate the cost.
        Http::fake([
            'generativelanguage.googleapis.com/*' => fn ($request) => Http::response($this->sseBodyFor(
                (string) $request['input'],
                usage: str_starts_with((string) $request['input'], 'word1 ') ? [100, 1000] : null,
            )),
        ]);

        $this->fakeFfmpeg();

        $this->assertNull(app(GeminiClient::class)->convertTextToSpeech($text)->usage);
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

        $this->assertEquals($text, $client->convertTextToSpeech($text)->path);

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
            app(GeminiClient::class)->convertTextToSpeech('short')->path
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

        $narrated = app(GeminiClient::class)->convertTextToSpeech($text)->path;

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

    #[Test]
    public function it_reports_an_error_event_in_the_stream()
    {
        config()->set('services.gemini.api_key', 'test-key');

        // A refused request arrives as an error event with HTTP 200.
        $error = json_encode(['error' => [
            'message' => 'Rate limit exceeded for model gemini-3.1-flash-tts (limit: 10 requests per day on Free Tier).',
            'code'    => 'rate_limit_exceeded',
        ], 'event_type' => 'error']);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(
                "event: interaction.created\ndata: {\"interaction\":{\"status\":\"in_progress\"}}\n\n"
                ."event: error\ndata: $error\n\n"
            ),
        ]);

        $this->fakeFfmpeg();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Rate limit exceeded');
        $this->expectExceptionMessage('rate_limit_exceeded');

        app(GeminiClient::class)->convertTextToSpeech('hello world');
    }

    #[Test]
    public function it_narrates_only_the_segments_a_failed_attempt_did_not_finish()
    {
        // Three segments, all in one batch.
        $text = collect(range(1, 500))->map(fn (int $i) => "word$i")->implode(' ');

        config()->set('services.gemini.tts.model', 'gemini-3.8-flash-lite-tts');

        $failing = true;

        Http::fake([
            'generativelanguage.googleapis.com/*' => function ($request) use (&$failing) {
                return $failing && str_contains((string) $request['input'], 'word2 ')
                    ? Http::response('nope', 500)
                    : Http::response($this->sseBodyFor((string) $request['input'], usage: [100, 1000]));
            },
        ]);

        $this->fakeFfmpeg();

        try {
            app(GeminiClient::class)->convertTextToSpeech($text);
            $this->fail('The first attempt should fail.');
        } catch (\Throwable) {
        }

        $failing = false;
        $requestsBefore = Http::recorded()->count();

        $narration = app(GeminiClient::class)->convertTextToSpeech($text);

        $this->assertEquals(1, Http::recorded()->count() - $requestsBefore);
        $this->assertEquals(preg_replace('/\s+/', '', $text), preg_replace('/\s+/', '', $narration->path));

        // The total includes the segments paid for in the first attempt.
        $this->assertEquals(3000, $narration->usage->outputTokens);
    }

    #[Test]
    public function it_clears_its_cached_segments_once_the_narration_succeeds()
    {
        $text = collect(range(1, 500))->map(fn (int $i) => "word$i")->implode(' ');

        $this->fakeSegmentsWithUsage();

        app(GeminiClient::class)->convertTextToSpeech($text);

        $this->assertEmpty(Storage::disk(SegmentCache::DISK)->files());
    }
}
