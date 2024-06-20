<?php

namespace Database\Factories;

use App\Concerns\FixesUrls;
use App\Models\AudioClip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AudioClip>
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
            AudioClip::COL_PLATFORM_URL => $this->fixUrlSchemeAndHost($this->faker->url()),
            AudioClip::COL_GUID => $this->faker->uuid,
            AudioClip::COL_TITLE => $this->faker->name,
            AudioClip::COL_DESCRIPTION => $this->faker->realText,
            AudioClip::COL_DURATION => 3_600,
            AudioClip::COL_SIZE => 1_000_000,
            AudioClip::COL_STORAGE_PATH => $this->faker->uuid,
            AudioClip::COL_PROCESSING => false,
        ];
    }
}
