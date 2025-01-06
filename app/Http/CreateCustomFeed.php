<?php

namespace App\Http;

use Illuminate\Container\Attributes\CurrentUser;

class CreateCustomFeed
{
    public function __invoke(#[CurrentUser] $user)
    {
        return $user->feed()->create();
    }
}
