<?php

namespace Database\Factories;

use App\Enums\InboundEmailStatus;
use App\Models\InboundEmail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InboundEmail>
 */
class InboundEmailFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'resend_email_id' => $this->faker->uuid,
            'sender'          => $this->faker->safeEmail,
            'subject'         => $this->faker->sentence,
            'status'          => InboundEmailStatus::Pending,
        ];
    }
}
