<?php

namespace Tests\Http\Controllers\Auth;

use App\Models\User;
use Tests\TestCase;

class EmailVerificationPromptControllerTest extends TestCase
{
    public function test_email_verification_screen_can_be_rendered(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get('/verify-email');

        $response->assertStatus(200);
    }
}
