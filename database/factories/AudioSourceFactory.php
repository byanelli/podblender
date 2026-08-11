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
            'name'          => $this->faker->name,
            'platform_url'  => $this->faker->uuid,
            'platform_type' => PlatformType::YouTube,
        ];
    }
}
