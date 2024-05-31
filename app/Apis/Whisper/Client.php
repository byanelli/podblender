<?php

namespace App\Apis\Whisper;

use App\Apis\Ffmpeg\Client as FfmpegClient;
use App\Apis\Whisper\Contracts\Client as ClientContract;
use OpenAI\Contracts\ClientContract as OpenAiClient;
use OpenAI\Responses\Audio\SpeechStreamResponse;
use Ramsey\Uuid\Uuid;

readonly class Client implements ClientContract
{
    private const SEGMENT_LENGTH = 4096;

    public function __construct(
        private OpenAiClient $openAi,
        private FfmpegClient $ffmpeg,
    ) {}

    private function getSpeechStream(string $text): SpeechStreamResponse {
        return $this->openAi->audio()->speechStreamed([
            'model' => 'tts-1',
            'input' => $text,
            'voice' => 'alloy', // todo: make configurable
        ]);
    }

    private function writeStreamResponseToFile(string $file, SpeechStreamResponse $stream): void {
        (file_put_contents($file, '') !== false) || throw new \RuntimeException("Error initializing file: $file");

        $handle = fopen($file, 'w');

        is_resource($handle) || throw new \RuntimeException("Error opening file: $file");

        foreach ($stream as $chunk) {
            (fwrite($handle, $chunk) !== false) || throw new \RuntimeException("Error writing to file: $file");
        }

        fclose($handle) || throw new \RuntimeException("Error closing file: $file");
    }

    private function segmentText(string $text): \Generator {
        $currentSegment = '';
        $currentLength = 0;

        $words = preg_split('/\\s+/', $text);

        foreach ($words as $word) {
            $wordLength = strlen($word);

            if (($currentLength + $wordLength + 1) < self::SEGMENT_LENGTH) {
                $currentSegment .= ' '.$word;
                $currentLength += $wordLength + 1;
            } else {
                yield $currentSegment;
                $currentSegment = '';
                $currentLength = 0;
            }
        }

        if (!empty($currentSegment)) {
            yield $currentSegment;
        }
    }

    /**
     * @param string $text
     * @return string -- returns the path to an MP3 file
     */
    public function convertTextToSpeech(string $text): string {
        $mp3s = [];

        try {
            foreach ($this->segmentText($text) as $segment) {
                $outputPath = sys_get_temp_dir().'/'.Uuid::uuid4()->toString().'.mp3';

                $this->writeStreamResponseToFile($outputPath, $this->getSpeechStream($segment));

                $mp3s[] = $outputPath;
            }

            return $this->ffmpeg->combineMp3s($mp3s);
        } finally {
            collect($mp3s)->each(fn($mp3) => unlink($mp3));
        }
    }
}
