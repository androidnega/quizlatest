<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\StaffSessionController;
use App\Http\Controllers\Auth\StudentLoginController;
use App\Http\Controllers\Auth\StudentOnboardingController;
use App\Http\Controllers\Auth\StudentPasswordResetController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [StudentLoginController::class, 'create'])
        ->name('login');

    Route::post('login', [StudentLoginController::class, 'loginWithPassword'])
        ->middleware('throttle:12,1');

    Route::get('login/first-time', [StudentLoginController::class, 'createFirstTime'])
        ->name('login.first-time');

    Route::post('login/first-time', [StudentLoginController::class, 'storeFirstTime'])
        ->middleware('throttle:12,1')
        ->name('login.first-time.store');

    Route::get('login/first-time/phone', [StudentLoginController::class, 'createFirstTimePhone'])
        ->name('login.first-time.phone');

    Route::post('login/first-time/phone', [StudentLoginController::class, 'storeFirstTimePhone'])
        ->middleware('throttle:12,1')
        ->name('login.first-time.phone.store');

    Route::get('login/otp', [StudentLoginController::class, 'showOtp'])
        ->name('login.otp');

    Route::post('login/otp', [StudentLoginController::class, 'verifyOtp'])
        ->middleware('throttle:12,1');

    Route::get('student/onboarding', [StudentOnboardingController::class, 'create'])
        ->name('student.onboarding');

    Route::post('student/onboarding', [StudentOnboardingController::class, 'store'])
        ->middleware('throttle:12,1');

    Route::get('student/forgot-password', [StudentPasswordResetController::class, 'create'])
        ->name('student.password-reset.request');

    Route::post('student/forgot-password', [StudentPasswordResetController::class, 'store'])
        ->middleware('throttle:6,1');

    Route::get('student/forgot-password/otp', [StudentPasswordResetController::class, 'showOtp'])
        ->name('student.password-reset.otp');

    Route::post('student/forgot-password/otp', [StudentPasswordResetController::class, 'verifyOtp'])
        ->middleware('throttle:12,1');

    Route::get('student/reset-password', [StudentPasswordResetController::class, 'editPassword'])
        ->name('student.password-reset.form');

    Route::post('student/reset-password', [StudentPasswordResetController::class, 'updatePassword'])
        ->middleware('throttle:12,1');

    Route::get('staff/login', [StaffSessionController::class, 'create'])
        ->name('staff.login');

    Route::post('staff/login', [StaffSessionController::class, 'store'])
        ->middleware('throttle:12,1');
});

Route::middleware('auth')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
