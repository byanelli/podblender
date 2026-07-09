<?php

namespace App\Http\Requests\Auth;

use BYanelli\Roma\Request\Attributes\Guard;
use BYanelli\Roma\Request\Attributes\Rule;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

readonly class LoginRequest
{
    public function __construct(
        #[Rule('email')]
        public string $email,

        public string $password,

        public bool $remember = false,
    ) {}

    /**
     * Rate-limit and authenticate the credentials.
     *
     * @throws ValidationException
     */
    #[Guard]
    public function authenticate(Request $request): void
    {
        $this->ensureIsNotRateLimited($request);

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($this->throttleKey($request));

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));
    }

    /**
     * @throws ValidationException
     */
    private function ensureIsNotRateLimited(Request $request): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), 5)) {
            return;
        }

        event(new Lockout($request));

        $seconds = RateLimiter::availableIn($this->throttleKey($request));

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    private function throttleKey(Request $request): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.$request->ip());
    }
}
