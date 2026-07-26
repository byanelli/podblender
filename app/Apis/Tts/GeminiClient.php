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
 * Two things distinguish Gemini from OpenAI here. First, it returns headerless
 * raw PCM (signed 16-bit little-endian, mono) rather than MP3, so each segment
 * is decoded and transcoded before the segments are concatenated. Second, a
 * long narration generates for over a minute, and if the API produces the whole
 * response before sending anything the connection sits idle long enough to be
 * closed mid-response (cURL 56, "unexpected eof while reading"). Asking for a
 * streamed response fixes that: the API emits audio-delta events continuously,
 * so bytes keep arriving and the connection is never idle.
 *
 * Note that it's the 'stream' flag in the request *body* that does this — the
 * server behaves differently, which is what matters. No special Guzzle handler
 * is needed, so segments can be narrated concurrently through a request pool.
 *
 * Segments are independent requests, so they're sent a poolful at a time rather
 * than one after another; a three-segment article measured 2.78x faster this
 * way. They're transcoded as each pool returns, which also bounds how much
 * decoded audio is held in memory at once.
 */
readonly class GeminiClient implements ClientContract
{
    use SegmentsText;

    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/interactions';

    // ~1.5 minutes of audio per segment. Gemini caps input by tokens rather than
    // characters, and warns that quality drifts past a few minutes of output, so
    // this trades a few extra requests for reliable, good-sounding segments.
    private const SEGMENT_LENGTH = 1500;

    /**
     * How many segments to narrate at once. Downloads are serialised elsewhere
     * (see DownloadAndStoreAudioClip's WithoutOverlapping middleware), so this
     * also bounds how many requests we ever have in flight with Gemini at once.
     * Three concurrent requests were verified to run in parallel without being
     * rate limited; the published limits are account-specific, so raise this
     * only against a real account's quota.
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

            // Narrate a poolful at a time, transcoding each batch before
            // requesting the next so the audio doesn't all pile up in memory.
            foreach ($segments->chunk(self::CONCURRENCY) as $chunk) {
                foreach ($this->requestAudioForSegments($chunk->values()->all()) as [$pcmBytes, $sampleRate]) {
                    $pcms[] = $pcm = $this->writePcmToFile($pcmBytes);
                    $mp3s[] = $this->ffmpeg->pcmToMp3($pcm, $sampleRate);
                }
            }

            $combined = $this->ffmpeg->combineMp3s($mp3s);

            // Clean up the intermediates — but never the combined result, which
            // with a single segment IS one of the segment files.
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
     * How long one poolful of segments takes to narrate, whatever their size.
     * Generation time tracks the segment budget far more than the text in it:
     * measured 36.1s/43.1s/33.4s for segments of 1494/1005/1499 characters, and
     * 40.6s and 50.3s for a one-pool and a two-pool article. Rounded up from
     * those, since this feeds a timeout.
     */
    private const SECONDS_PER_POOL = 60;

    /**
     * Transcoding a segment's PCM to MP3 and adding it to the concatenation.
     * This is per segment, not a fixed cost: measured at a steady ~1.4s to
     * transcode plus ~0.1s of concat per segment, from one segment up to twelve.
     * Rounded up, since this feeds a timeout.
     */
    private const FFMPEG_SECONDS_PER_SEGMENT = 3;

    public function estimateNarrationTime(string $text): int
    {
        // Count segments the way convertTextToSpeech() actually will, rather
        // than estimating from length, so this stays right if the segmenter
        // changes.
        $segments = iterator_count($this->segmentText($text, self::SEGMENT_LENGTH));

        // Narration is the dominant cost and runs CONCURRENCY segments at a
        // time, so it's the number of pools that matters there. Transcoding is
        // sequential, so it scales with every segment.
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
                // Transient transport drops are worth a retry; the POST is idempotent.
                ->retry(3, 1000, throw: false)
                ->withHeaders(['x-goog-api-key' => $apiKey])
                ->post(self::ENDPOINT, $this->audioRequestBody($segment)))
            ->all(), concurrency: self::CONCURRENCY);

        // Pool results are keyed by the index each request was registered under,
        // so walking the segments in order reassembles the narration in order
        // however the responses happened to settle.
        return collect($segments)
            ->keys()
            ->map(function (int $index) use ($responses) {
                $response = $responses[$index] ?? null;

                // A segment that failed every retry comes back as the exception
                // rather than a response; rethrow it so the whole narration
                // fails instead of silently losing that stretch of audio.
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
     * The request body for one segment. 'stream' asks the API to send audio
     * deltas as they're generated, so a long narration never leaves the
     * connection idle long enough to be dropped.
     *
     * @return array<string, mixed>
     */
    private function audioRequestBody(string $segment): array
    {
        return [
            'model' => (string) $this->config->get('services.gemini.tts.model'),
            'input' => $segment,
            'response_format' => ['type' => 'audio'],
            'generation_config' => [
                'speech_config' => [
                    ['voice' => (string) $this->config->get('services.gemini.tts.voice')],
                ],
            ],
            'stream' => true,
        ];
    }

    /**
     * Parse a server-sent event body and concatenate its audio deltas into one
     * PCM blob, returning [pcmBytes, sampleRate]. The narration is the sequence
     * of step.delta events carrying an audio payload, one per "data: {json}" line.
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
