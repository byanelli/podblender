<?php

namespace App\Auth;

use App\Models\User;
use Illuminate\Auth\AuthManager;

readonly class AuthUserResolver
{
    public function __construct(private AuthManager $auth) {}

    public function get(): User
    {
        return $this->auth->user();
    }
}
