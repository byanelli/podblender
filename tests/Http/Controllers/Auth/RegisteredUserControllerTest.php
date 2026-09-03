<?php

namespace Tests\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisteredUserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name'                  => 'Test User',
            'email'                 => 'test@example.com',
            'password'              => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_anyone_can_register_when_the_allowlist_is_empty(): void
    {
        config()->set('auth.allowed_registration_emails', []);

        $response = $this->post('/register', [
            'name'                  => 'Test User',
            'email'                 => 'stranger@example.com',
            'password'              => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertDatabaseHas(User::class, ['email' => 'stranger@example.com']);
    }

    public function test_a_listed_address_can_register_whatever_its_casing(): void
    {
        config()->set('auth.allowed_registration_emails', ['Allowed@Example.com']);

        $response = $this->post('/register', [
            'name'                  => 'Test User',
            'email'                 => 'allowed@example.com',
            'password'              => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertDatabaseHas(User::class, ['email' => 'allowed@example.com']);
    }

    public function test_an_unlisted_address_cannot_register_when_the_allowlist_is_set(): void
    {
        $this->withExceptionHandling();

        config()->set('auth.allowed_registration_emails', ['allowed@example.com']);

        $response = $this->post('/register', [
            'name'                  => 'Test User',
            'email'                 => 'stranger@example.com',
            'password'              => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertDatabaseMissing(User::class, ['email' => 'stranger@example.com']);
    }
}
