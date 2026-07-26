<?php

namespace App\Apis\Tts;

use App\Apis\Ffmpeg\Contracts\Client as FfmpegClient;
use App\Apis\Tts\Concerns\SegmentsText;
use App\Apis\Tts\Contracts\Client as TtsClientContract;
use OpenAI\Contracts\ClientContract as OpenAiClientContract;
use OpenAI\Responses\Audio\SpeechStreamResponse;
use Ramsey\Uuid\Uuid;

/**
 * Text-to-speech backed by OpenAI's tts-1 model. Retained behind the shared
 * Tts contract but currently unbound — GeminiClient is the active backend. Its
 * streamed responses are already MP3, so segments go straight to the concat.
 */
readonly class OpenAiClient implements TtsClientContract
{
    use SegmentsText;

    // tts-1 rejects input longer than 4096 characters.
    private const SEGMENT_LENGTH = 4096;

    public function __construct(
        private OpenAiClientContract $openAi,
        private FfmpegClient $ffmpeg,
    ) {}

    private function getSpeechStream(string $text): SpeechStreamResponse
    {
        return $this->openAi->audio()->speechStreamed([
            'model' => 'tts-1',
            'input' => $text,
            'voice' => 'alloy', // todo: make configurable
        ]);
    }

    private function writeStreamResponseToFile(string $file, SpeechStreamResponse $stream): void
    {
        (file_put_contents($file, '') !== false) || throw new \RuntimeException("Error initializing file: $file");

        $handle = fopen($file, 'w');

        is_resource($handle) || throw new \RuntimeException("Error opening file: $file");

        foreach ($stream as $chunk) {
            (fwrite($handle, $chunk) !== false) || throw new \RuntimeException("Error writing to file: $file");
        }

        fclose($handle) || throw new \RuntimeException("Error closing file: $file");
    }

    /**
     * @return string -- returns the path to an MP3 file
     */
    public function convertTextToSpeech(string $text): string
    {
        $mp3s = [];

        try {
            foreach ($this->segmentText($text, self::SEGMENT_LENGTH) as $segment) {
                $outputPath = sys_get_temp_dir().'/'.Uuid::uuid4()->toString().'.mp3';

                $this->writeStreamResponseToFile($outputPath, $this->getSpeechStream($segment));

                $mp3s[] = $outputPath;
            }

            $combined = $this->ffmpeg->combineMp3s($mp3s);

            // Clean up the intermediates — but never the combined result, which
            // with a single segment IS one of the segment files.
            collect($mp3s)
                ->reject(fn ($mp3) => $mp3 === $combined)
                ->each(fn ($mp3) => unlink($mp3));

            return $combined;
        } catch (\Throwable $e) {
            collect($mp3s)->each(fn ($mp3) => @unlink($mp3));

            throw $e;
        }
    }

    /**
     * Segments are narrated one after another here, so cost is simply the
     * segment count. This backend is currently unbound, so the per-segment
     * figure is an unmeasured guess held deliberately high; measure it before
     * relying on it.
     */
    private const SECONDS_PER_SEGMENT = 120;

    /**
     * Adding a segment to the concatenation. Unlike Gemini there's no transcode
     * — the API already returns MP3 — so this is only the concat's share.
     */
    private const FFMPEG_SECONDS_PER_SEGMENT = 1;

    public function estimateNarrationTime(string $text): int
    {
        $segments = iterator_count($this->segmentText($text, self::SEGMENT_LENGTH));

        return $segments * (self::SECONDS_PER_SEGMENT + self::FFMPEG_SECONDS_PER_SEGMENT);
    }
}
