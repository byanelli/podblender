<?php

namespace App\Apis\Tts;

use App\Apis\Ffmpeg\Contracts\Client as FfmpegClient;
use App\Apis\Tts\Concerns\SegmentsText;
use App\Apis\Tts\Contracts\Client as ClientContract;
use GuzzleHttp\Handler\StreamHandler;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\Factory as Http;
use Ramsey\Uuid\Uuid;

/**
 * Text-to-speech backed by Gemini's TTS models over the Interactions API.
 *
 * Two things distinguish Gemini from OpenAI here. First, it returns headerless
 * raw PCM (signed 16-bit little-endian, mono) rather than MP3, so each segment
 * is decoded and transcoded before the segments are concatenated. Second, a
 * long narration generates for over a minute with no bytes on the wire, and an
 * idle connection like that gets closed mid-response (cURL 56, "unexpected eof
 * while reading"). We therefore request a streamed response: the API emits
 * keepalive and audio-delta events continuously, so the connection never sits
 * idle long enough to be dropped.
 *
 * Streaming needs Guzzle's StreamHandler with the 'stream' option — the default
 * CurlHandler buffers the whole response, which both reintroduces the idle
 * timeout and (with a never-ending SSE body) hangs outright. Setting the inner
 * handler via setHandler() keeps Laravel's own handler stack, so Http::fake()
 * and retry() still work.
 */
readonly class GeminiClient implements ClientContract
{
    use SegmentsText;

    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/interactions';

    // ~1.5 minutes of audio per segment. Gemini caps input by tokens rather than
    // characters, and warns that quality drifts past a few minutes of output, so
    // this trades a few extra requests for reliable, good-sounding segments.
    private const SEGMENT_LENGTH = 1500;

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
            foreach ($this->segmentText($text, self::SEGMENT_LENGTH) as $segment) {
                [$pcm, $sampleRate] = $this->synthesizeSegment($segment);
                $pcms[] = $pcm;
                $mp3s[] = $this->ffmpeg->pcmToMp3($pcm, $sampleRate);
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
     * Stream one segment's narration, decode the PCM to a temp file, and return
     * [path, sampleRate].
     *
     * @return array{0: string, 1: int}
     */
    private function synthesizeSegment(string $segment): array
    {
        [$pcm, $sampleRate] = $this->requestAudio($segment);

        $outputPath = sys_get_temp_dir().'/'.Uuid::uuid4()->toString().'.pcm';

        (file_put_contents($outputPath, $pcm) !== false)
            || throw new \RuntimeException("Error writing PCM to file: $outputPath");

        return [$outputPath, $sampleRate];
    }

    /**
     * POST one segment with streaming enabled and return its decoded PCM plus
     * the detected sample rate.
     *
     * @return array{0: string, 1: int}
     */
    private function requestAudio(string $segment): array
    {
        $model = (string) $this->config->get('services.gemini.tts.model');
        $voice = (string) $this->config->get('services.gemini.tts.voice');
        $apiKey = (string) $this->config->get('services.gemini.api_key');

        // Transient transport drops (the very error this streaming call exists
        // to avoid, on a bad day) are worth a retry; the POST is idempotent.
        $response = $this->http
            ->setHandler(new StreamHandler)
            ->timeout(300)
            ->connectTimeout(10)
            ->retry(3, 1000, throw: false)
            ->withHeaders(['x-goog-api-key' => $apiKey])
            ->withOptions(['stream' => true])
            ->post(self::ENDPOINT, [
                'model' => $model,
                'input' => $segment,
                'response_format' => ['type' => 'audio'],
                'generation_config' => [
                    'speech_config' => [
                        ['voice' => $voice],
                    ],
                ],
                'stream' => true,
            ])
            ->throw();

        return $this->decodePcmFromSse($response->body());
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
