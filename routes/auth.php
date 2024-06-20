<?php

use App\Http\Controllers;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('register', [Controllers\Auth\RegisteredUserBaseController::class, 'create'])
                ->name('register');

    Route::post('register', [Controllers\Auth\RegisteredUserBaseController::class, 'store']);

    Route::get('login', [Controllers\Auth\AuthenticatedSessionBaseController::class, 'create'])
                ->name('login');

    Route::post('login', [Controllers\Auth\AuthenticatedSessionBaseController::class, 'store']);

    Route::get('forgot-password', [Controllers\Auth\PasswordResetLinkBaseController::class, 'create'])
                ->name('password.request');

    Route::post('forgot-password', [Controllers\Auth\PasswordResetLinkBaseController::class, 'store'])
                ->name('password.email');

    Route::get('reset-password/{token}', [Controllers\Auth\NewPasswordBaseController::class, 'create'])
                ->name('password.reset');

    Route::post('reset-password', [Controllers\Auth\NewPasswordBaseController::class, 'store'])
                ->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::get('verify-email', Controllers\Auth\EmailVerificationPromptBaseController::class)
                ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', Controllers\Auth\VerifyEmailBaseController::class)
                ->middleware(['signed', 'throttle:6,1'])
                ->name('verification.verify');

    Route::post('email/verification-notification', [Controllers\Auth\EmailVerificationNotificationBaseController::class, 'store'])
                ->middleware('throttle:6,1')
                ->name('verification.send');

    Route::get('confirm-password', [Controllers\Auth\ConfirmablePasswordBaseController::class, 'show'])
                ->name('password.confirm');

    Route::post('confirm-password', [Controllers\Auth\ConfirmablePasswordBaseController::class, 'store']);

    Route::put('password', [Controllers\Auth\PasswordBaseController::class, 'update'])->name('password.update');

    Route::post('logout', [Controllers\Auth\AuthenticatedSessionBaseController::class, 'destroy'])
                ->name('logout');

    Route::get('/profile', [Controllers\Auth\ProfileBaseController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [Controllers\Auth\ProfileBaseController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [Controllers\Auth\ProfileBaseController::class, 'destroy'])->name('profile.destroy');
});
