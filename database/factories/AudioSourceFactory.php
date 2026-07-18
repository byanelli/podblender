<?php

namespace Database\Factories;

use App\Enums\PlatformType;
use App\Models\AudioSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AudioSource>
 */
class AudioSourceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            AudioSource::COL_NAME => $this->faker->name,
            AudioSource::COL_PLATFORM_URL => $this->faker->uuid,
            AudioSource::COL_PLATFORM_TYPE => PlatformType::YouTube,
        ];
    }
}
