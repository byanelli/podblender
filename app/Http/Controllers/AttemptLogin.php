<?php

namespace App\Http\Controllers;

use App\Http\Routes\Web;
use App\Models\User;
use Illuminate\Auth\AuthManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

readonly class AttemptLogin
{
    public function __construct(private AuthManager $auth) {}

    public function __invoke(Request $request): RedirectResponse {
        return $this->auth->attempt($request->only(User::COL_EMAIL, User::COL_PASSWORD))
            ? redirect(Web::home())
            : redirect(Web::showLogin());
    }
}
