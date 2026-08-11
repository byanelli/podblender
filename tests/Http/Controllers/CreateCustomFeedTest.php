<?php

namespace Tests\Http\Controllers;

use App\Models\User;
use Tests\TestCase;

class CreateCustomFeedTest extends TestCase
{
    public function test_create_custom_feed()
    {
        $feedName = 'Test Feed';
        $user = User::factory()->create();
        $this->actingAs($user);

        $requestPayload = [
            'name' => $feedName,
        ];

        $this->postJson('/feeds', $requestPayload)
            ->assertOk();

        $this->assertDatabaseCount('feeds', 1);

        $this->assertDatabaseHas('feeds', [
            'name'            => $feedName,
            'user_id'         => $user->id,
            'subscription_id' => null,
        ]);
    }
}
