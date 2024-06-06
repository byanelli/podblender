<?php

namespace App\Http\Controllers;

use App\Http\Routes\Web;
use Illuminate\Auth\AuthManager;
use Illuminate\Http\RedirectResponse;

readonly class Logout
{
    public function __construct(private AuthManager $auth) {}

    public function __invoke(): RedirectResponse {
        $this->auth->logout();

        return redirect(Web::home());
    }
}
