<?php

namespace App\Apis\Tts;

use App\Apis\Ffmpeg\Contracts\Client as FfmpegClient;
use App\Apis\Tts\Concerns\SegmentsText;
use App\Apis\Tts\Contracts\Client as ClientContract;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Ramsey\Uuid\Uuid;

/**
 * Text-to-speech backed by Gemini's TTS models over the Interactions API.
 *
 * Gemini returns headerless raw PCM (signed 16-bit little-endian, mono), so
 * each segment is transcoded to MP3 before the segments are concatenated.
 *
 * Requests set 'stream' in the body. A long narration takes over a minute to
 * generate, and an unstreamed response leaves the connection idle long enough
 * to be closed mid-response (cURL 56, "unexpected eof while reading"). With
 * streaming, the API sends audio-delta events continuously. The flag only
 * changes the server's behavior, so no streaming Guzzle handler is needed and
 * the requests can go through a pool.
 *
 * Each narrated segment is cached until the whole narration succeeds, so a
 * retry after a failure requests only the segments that are missing.
 *
 * Segments are sent CONCURRENCY at a time; a three-segment article measured
 * 2.78x faster than sequential requests. Each batch is transcoded before the
 * next is requested, which limits how much decoded audio is in memory.
 */
readonly class GeminiClient implements ClientContract
{
    use SegmentsText;

    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/interactions';

    // Characters per segment, about 1.5 minutes of audio. Gemini limits input by
    // tokens and warns that quality drifts past a few minutes of output.
    private const SEGMENT_LENGTH = 1500;

    /**
     * How many segments to narrate at once. Downloads run one at a time (see
     * DownloadAndStoreAudioClip's WithoutOverlapping middleware), so this is
     * also the most requests in flight with Gemini at any time. Three ran in
     * parallel without being rate limited. Published limits are per account,
     * so check the account's quota before raising this.
     */
    private const CONCURRENCY = 3;

    // Fallback sample rate if a delta omits it (24kHz is Gemini's TTS default).
    private const DEFAULT_SAMPLE_RATE = 24000;

    public function __construct(
        private Http $http,
        private FfmpegClient $ffmpeg,
        private GeminiClientConfig $config,
        private SegmentCache $cache,
    ) {}

    public function convertTextToSpeech(string $text): Narration
    {
        $segments = iterator_to_array($this->segmentText($text, self::SEGMENT_LENGTH), preserve_keys: false);
        $ids = array_map($this->cacheId(...), $segments);

        // Each segment's MP3 and usage, by segment index.
        $mp3s = [];
        $usages = [];

        // Local files to delete once the segments are combined.
        $temporary = [];

        try {
            foreach ($ids as $index => $id) {
                if (($cached = $this->cache->get($id)) !== null) {
                    [$mp3s[$index], $usages[$index]] = $cached;
                    $temporary[] = $mp3s[$index];
                }
            }

            // Segments with no cached audio, keyed by their index in $segments.
            $uncached = array_diff_key($segments, $mp3s);

            // Transcode each batch before requesting the next, to limit the
            // decoded audio in memory.
            foreach (array_chunk($uncached, self::CONCURRENCY, preserve_keys: true) as $batch) {
                $failure = null;

                foreach ($this->requestAudioForSegments($batch) as $index => $result) {
                    if ($result instanceof \Throwable) {
                        $failure ??= $result;

                        continue;
                    }

                    [$pcmBytes, $sampleRate, $usages[$index]] = $result;

                    $temporary[] = $pcm = $this->writePcmToFile($pcmBytes);
                    $temporary[] = $mp3s[$index] = $this->ffmpeg->pcmToMp3($pcm, $sampleRate);

                    $this->cache->put($ids[$index], $mp3s[$index], $usages[$index]);
                }

                // Fail rather than omit a segment. The batch's other segments
                // are cached, so a retry doesn't pay for them again.
                if ($failure !== null) {
                    throw $failure;
                }
            }

            ksort($mp3s);

            $combined = $this->ffmpeg->combineMp3s(array_values($mp3s));

            foreach ($ids as $id) {
                $this->cache->forget($id);
            }

            // With a single segment the combined result is that segment's
            // file, so it is kept.
            collect($temporary)
                ->reject(fn ($path) => $path === $combined)
                ->each(fn ($path) => @unlink($path));

            return new Narration($combined, $this->totalUsage($usages));
        } catch (\Throwable $e) {
            collect($temporary)->each(fn ($path) => @unlink($path));

            throw $e;
        }
    }

    /**
     * Identifies a segment's audio in the cache. The model and voice are part
     * of it because they change the audio.
     */
    private function cacheId(string $segment): string
    {
        return implode("\n", [$this->config->model, $this->config->voice, $segment]);
    }

    /**
     * @param  array<int, array{0: int, 1: int}|null>  $usages
     */
    private function totalUsage(array $usages): ?Usage
    {
        // A total that omits a segment would understate the cost.
        if (in_array(null, $usages, strict: true)) {
            return null;
        }

        /** @var array<int, array{0: int, 1: int}> $usages */
        $inputTokens = array_sum(array_column($usages, 0));
        $outputTokens = array_sum(array_column($usages, 1));

        return new Usage(
            model: $this->config->model,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            cost: $this->cost($inputTokens, $outputTokens),
        );
    }

    /**
     * Seconds to narrate one batch of segments. Generation time varies little
     * with segment length: measured 36.1s/43.1s/33.4s for segments of
     * 1494/1005/1499 characters, and 40.6s and 50.3s for a one-batch and a
     * two-batch article. Rounded up because it is used for a timeout.
     */
    private const SECONDS_PER_POOL = 60;

    /**
     * Seconds to transcode one segment's PCM to MP3 and concatenate it.
     * Measured at ~1.4s to transcode plus ~0.1s of concat per segment, for one
     * to twelve segments. Rounded up because it is used for a timeout.
     */
    private const FFMPEG_SECONDS_PER_SEGMENT = 3;

    /**
     * USD for the given tokens at the current price, or null if the model has none configured.
     */
    private function cost(int $inputTokens, int $outputTokens): ?float
    {
        $today = Carbon::today()->toDateString();

        $price = collect($this->config->prices[$this->config->model] ?? [])
            ->filter(fn (array $price, string $from) => $from <= $today)
            ->sortKeys()
            ->last();

        return $price === null
            ? null
            : ($inputTokens * $price['input'] + $outputTokens * $price['output']) / 1_000_000;
    }

    public function estimateNarrationTime(string $text): int
    {
        // Same segmentation as convertTextToSpeech().
        $segments = iterator_count($this->segmentText($text, self::SEGMENT_LENGTH));

        // Narration runs CONCURRENCY segments at a time, so its cost is per
        // batch. Transcoding is sequential, so its cost is per segment.
        $pools = (int) ceil($segments / self::CONCURRENCY);

        return ($pools * self::SECONDS_PER_POOL)
            + ($segments * self::FFMPEG_SECONDS_PER_SEGMENT);
    }

    /**
     * Write a segment's decoded PCM to a temp file for ffmpeg to transcode.
     */
    private function writePcmToFile(string $pcm): string
    {
        $outputPath = sys_get_temp_dir().'/'.Uuid::uuid4()->toString().'.pcm';

        (file_put_contents($outputPath, $pcm) !== false)
            || throw new \RuntimeException("Error writing PCM to file: $outputPath");

        return $outputPath;
    }

    /**
     * Narrate several segments concurrently. Returns each one's decoded PCM,
     * sample rate and usage, or the exception it failed with, keyed like
     * $segments.
     *
     * @param  array<int, string>  $segments
     * @return array<int, array{0: string, 1: int, 2: array{0: int, 1: int}|null}|\Throwable>
     */
    private function requestAudioForSegments(array $segments): array
    {
        $responses = $this->http->pool(fn (Pool $pool) => collect($segments)
            ->map(fn (string $segment, int $index) => $pool->as((string) $index)
                ->timeout(300)
                ->connectTimeout(10)
                // Retry transient transport failures. The POST is idempotent.
                ->retry(3, 1000, throw: false)
                ->withHeaders(['x-goog-api-key' => $this->config->apiKey])
                ->post(self::ENDPOINT, $this->audioRequestBody($segment)))
            ->all(), concurrency: self::CONCURRENCY);

        // Pool results are keyed by segment index, so reading them in segment
        // order gives the narration in order regardless of completion order.
        return collect($segments)
            ->map(function (string $segment, int $index) use ($responses) {
                $response = $responses[$index] ?? null;

                // A segment that failed every retry is returned as the exception.
                if ($response instanceof \Throwable) {
                    return $response;
                }

                try {
                    ($response instanceof Response)
                        || throw new \RuntimeException("Gemini TTS returned no response for segment $index");

                    return $this->decodePcmFromSse($response->throw()->body());
                } catch (\Throwable $e) {
                    return $e;
                }
            })
            ->all();
    }

    /**
     * The request body for one segment. See the class comment for 'stream'.
     *
     * @return array<string, mixed>
     */
    private function audioRequestBody(string $segment): array
    {
        return [
            'model'             => $this->config->model,
            'input'             => $segment,
            'response_format'   => ['type' => 'audio'],
            'generation_config' => [
                'speech_config' => [
                    ['voice' => $this->config->voice],
                ],
            ],
            'stream'            => true,
        ];
    }

    /**
     * Parse a server-sent event body and concatenate its audio deltas into one
     * PCM blob, returning [pcmBytes, sampleRate, usage]. The audio is in the
     * step.delta events with an audio payload, one per "data: {json}" line.
     * Usage is [inputTokens, outputTokens] from the interaction.completed
     * event, or null if the stream didn't include it.
     *
     * @return array{0: string, 1: int, 2: array{0: int, 1: int}|null}
     */
    private function decodePcmFromSse(string $sse): array
    {
        $pcm = '';
        $sampleRate = self::DEFAULT_SAMPLE_RATE;
        $usage = null;

        foreach (preg_split('/\r?\n\r?\n/', $sse) ?: [] as $event) {
            foreach (preg_split('/\r?\n/', $event) ?: [] as $line) {
                if (! str_starts_with($line, 'data:')) {
                    continue;
                }

                $payload = json_decode(trim(substr($line, strlen('data:'))), true);

                if (! is_array($payload)) {
                    continue;
                }

                // A refused request, e.g. a rate limit, is an error event in
                // the stream with HTTP 200.
                if (is_array($payload['error'] ?? null)) {
                    throw new \RuntimeException(sprintf(
                        'Gemini TTS returned an error: %s (%s)',
                        $payload['error']['message'] ?? 'no message',
                        $payload['error']['code'] ?? 'no code',
                    ));
                }

                $reported = $payload['interaction']['usage'] ?? null;

                if (is_array($reported)) {
                    $usage = [
                        (int) ($reported['total_input_tokens'] ?? 0),
                        (int) ($reported['total_output_tokens'] ?? 0),
                    ];
                }

                $delta = $payload['delta'] ?? null;

                if (! is_array($delta)) {
                    continue;
                }

                $isAudio = ($delta['type'] ?? null) === 'audio'
                    || str_starts_with((string) ($delta['mime_type'] ?? ''), 'audio/');

                if (! $isAudio || ! isset($delta['data']) || ! is_string($delta['data'])) {
                    continue;
                }

                $chunk = base64_decode($delta['data'], strict: true);
                ($chunk !== false) || throw new \RuntimeException('Gemini TTS returned undecodable audio data');

                $pcm .= $chunk;
                $sampleRate = (int) ($delta['sample_rate'] ?? $sampleRate);
            }
        }

        ($pcm !== '') || throw new \RuntimeException('Gemini TTS response contained no audio data');

        return [$pcm, $sampleRate, $usage];
    }
}
