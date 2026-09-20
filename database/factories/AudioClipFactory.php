<?php

namespace Database\Factories;

use App\Concerns\FixesUrls;
use App\Enums\ClipProcessingState;
use App\Models\AudioClip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AudioClip>
 */
class AudioClipFactory extends Factory
{
    use FixesUrls;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'platform_url'     => $this->fixUrlSchemeAndHost($this->faker->url()),
            'guid'             => $this->faker->uuid,
            'title'            => $this->faker->name,
            'description'      => $this->faker->realText,
            'duration'         => 3_600,
            'size'             => 1_000_000,
            'storage_path'     => $this->faker->uuid,
            'processing_state' => ClipProcessingState::Processing,

            // Always set from the platform's metadata in production. Differs from created_at so tests can tell them apart.
            'published_at'     => $this->faker->dateTimeBetween('-2 years', '-1 day'),
        ];
    }
}
