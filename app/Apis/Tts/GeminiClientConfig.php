<?php

namespace App\Apis\Tts;

use Illuminate\Contracts\Config\Repository as Config;

readonly class GeminiClientConfig
{
    public string $apiKey;

    public string $model;

    public string $voice;

    public function __construct(Config $config)
    {
        $this->apiKey = (string) $config->get('services.gemini.api_key');
        $this->model = (string) $config->get('services.gemini.tts.model');
        $this->voice = (string) $config->get('services.gemini.tts.voice');
    }
}
