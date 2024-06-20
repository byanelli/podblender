<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailBaseController
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
        }

        $user->email_verified_at = CarbonImmutable::now();

        if ($user->save()) {
            event(new Verified($user));
        }

        return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
    }
}
