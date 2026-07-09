<?php

namespace App\Http\Requests\Auth;

use BYanelli\Roma\Request\Attributes\Rule;

/**
 * Credentials for an authentication attempt.
 *
 * The imperative side of authentication — rate limiting, the credential check,
 * and session regeneration — now lives in AuthenticatedSessionBaseController,
 * since a Roma request is a plain data object with no access to the session or
 * auth guard.
 */
readonly class LoginRequest
{
    public function __construct(
        #[Rule('email')]
        public string $email,

        public string $password,

        public bool $remember = false,
    ) {}
}
