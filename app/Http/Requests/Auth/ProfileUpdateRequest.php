<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use BYanelli\Roma\Request\Attributes\Rule;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Validation\Rule as ValidationRule;
use Illuminate\Validation\Rules\Unique;

readonly class ProfileUpdateRequest
{
    public function __construct(
        #[Rule('max:255')]
        public string $name,

        #[Rule('lowercase', 'email', 'max:255', self::uniqueEmail(...))]
        public string $email,
    ) {}

    public static function uniqueEmail(#[CurrentUser] User $user): Unique
    {
        return ValidationRule::unique(User::class, 'email')->ignore($user->id);
    }
}
