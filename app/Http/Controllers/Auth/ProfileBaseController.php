<?php

namespace App\Http\Controllers\Auth;

use App\Http\Requests\Auth\ProfileUpdateRequest;
use App\Models\User;
use BYanelli\Roma\Request\ContextualBinding\Request as RomaRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProfileBaseController
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(#[RomaRequest] ProfileUpdateRequest $data, Request $request): RedirectResponse
    {
        $user = $request->user();

        // Email uniqueness ignoring the current user depends on the authenticated
        // user's id at runtime, so it can't be a static Roma rule attribute and is
        // validated here instead.
        Validator::make(
            ['email' => $data->email],
            ['email' => [Rule::unique(User::class)->ignore($user->id)]],
        )->validate();

        $user->fill([
            'name' => $data->name,
            'email' => $data->email,
        ]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
