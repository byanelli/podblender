<?php

namespace App\Http\Controllers\Auth;

use App\Http\Requests\Auth\LoginRequest;
use BYanelli\Roma\Request\ContextualBinding\Request as RomaRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionBaseController
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status'           => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     *
     * Resolving the LoginRequest validates the input and runs its #[Guard],
     * which rate-limits and authenticates the credentials — so by the time this
     * body runs the user is authenticated.
     */
    public function store(#[RomaRequest] LoginRequest $request, Request $httpRequest): RedirectResponse
    {
        $httpRequest->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
