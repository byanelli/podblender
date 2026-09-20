<?php

namespace App\Apis\Tts;

use App\Apis\Ffmpeg\Contracts\Client as FfmpegClient;
use App\Apis\Tts\Concerns\SegmentsText;
use App\Apis\Tts\Contracts\Client as ClientContract;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
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
        private Config $config,
    ) {}

    /**
     * @return string -- returns the path to an MP3 file
     */
    public function convertTextToSpeech(string $text): string
    {
        $pcms = [];
        $mp3s = [];

        try {
            $segments = collect($this->segmentText($text, self::SEGMENT_LENGTH));

            // Transcode each batch before requesting the next, to limit the
            // decoded audio in memory.
            foreach ($segments->chunk(self::CONCURRENCY) as $chunk) {
                foreach ($this->requestAudioForSegments($chunk->values()->all()) as [$pcmBytes, $sampleRate]) {
                    $pcms[] = $pcm = $this->writePcmToFile($pcmBytes);
                    $mp3s[] = $this->ffmpeg->pcmToMp3($pcm, $sampleRate);
                }
            }

            $combined = $this->ffmpeg->combineMp3s($mp3s);

            // Delete the intermediates. With a single segment the combined
            // result is one of them, so it is excluded.
            collect($pcms)->merge($mp3s)
                ->reject(fn ($path) => $path === $combined)
                ->each(fn ($path) => @unlink($path));

            return $combined;
        } catch (\Throwable $e) {
            collect($pcms)->merge($mp3s)->each(fn ($path) => @unlink($path));

            throw $e;
        }
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
     * Narrate several segments concurrently, returning each one's decoded PCM
     * and sample rate in the order the segments were given.
     *
     * @param  array<int, string>  $segments
     * @return array<int, array{0: string, 1: int}>
     */
    private function requestAudioForSegments(array $segments): array
    {
        $apiKey = (string) $this->config->get('services.gemini.api_key');

        $responses = $this->http->pool(fn (Pool $pool) => collect($segments)
            ->map(fn (string $segment, int $index) => $pool->as((string) $index)
                ->timeout(300)
                ->connectTimeout(10)
                // Retry transient transport failures. The POST is idempotent.
                ->retry(3, 1000, throw: false)
                ->withHeaders(['x-goog-api-key' => $apiKey])
                ->post(self::ENDPOINT, $this->audioRequestBody($segment)))
            ->all(), concurrency: self::CONCURRENCY);

        // Pool results are keyed by segment index, so reading them in segment
        // order gives the narration in order regardless of completion order.
        return collect($segments)
            ->keys()
            ->map(function (int $index) use ($responses) {
                $response = $responses[$index] ?? null;

                // A segment that failed every retry is returned as the
                // exception. Rethrow it so the narration fails instead of
                // omitting that segment.
                if ($response instanceof \Throwable) {
                    throw $response;
                }

                ($response instanceof Response)
                    || throw new \RuntimeException("Gemini TTS returned no response for segment $index");

                return $this->decodePcmFromSse($response->throw()->body());
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
            'model'             => (string) $this->config->get('services.gemini.tts.model'),
            'input'             => $segment,
            'response_format'   => ['type' => 'audio'],
            'generation_config' => [
                'speech_config' => [
                    ['voice' => (string) $this->config->get('services.gemini.tts.voice')],
                ],
            ],
            'stream'            => true,
        ];
    }

    /**
     * Parse a server-sent event body and concatenate its audio deltas into one
     * PCM blob, returning [pcmBytes, sampleRate]. The audio is in the step.delta
     * events with an audio payload, one per "data: {json}" line.
     *
     * @return array{0: string, 1: int}
     */
    private function decodePcmFromSse(string $sse): array
    {
        $pcm = '';
        $sampleRate = self::DEFAULT_SAMPLE_RATE;

        foreach (preg_split('/\r?\n\r?\n/', $sse) ?: [] as $event) {
            foreach (preg_split('/\r?\n/', $event) ?: [] as $line) {
                if (! str_starts_with($line, 'data:')) {
                    continue;
                }

                $payload = json_decode(trim(substr($line, strlen('data:'))), true);

                if (! is_array($payload)) {
                    continue;
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

        return [$pcm, $sampleRate];
    }
}
