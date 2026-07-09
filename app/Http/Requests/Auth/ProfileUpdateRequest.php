<?php

namespace App\Http\Requests\Auth;

use BYanelli\Roma\Request\Attributes\Rule;

/**
 * Profile fields for an update.
 *
 * Email uniqueness (ignoring the current user) is enforced in
 * ProfileBaseController::update, because it depends on the authenticated user's
 * id at runtime and so can't be expressed as a static Roma rule attribute.
 */
readonly class ProfileUpdateRequest
{
    public function __construct(
        #[Rule('max:255')]
        public string $name,

        #[Rule(['lowercase', 'email', 'max:255'])]
        public string $email,
    ) {}
}
