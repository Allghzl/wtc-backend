<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmailVerificationService
{
    /**
     * Generate a token, persist it, and send the verification email.
     */
    public function sendVerificationEmail(User $user): void
    {
        $token = $this->generateToken($user);

        $url = $this->buildVerificationUrl($token);

        $user->notify(new VerifyEmailNotification($url));
    }

    /**
     * Verify a token and mark the user's email as verified.
     */
    public function verify(string $token): User
    {
        $user = User::where('email_verification_token', hash('sha256', $token))->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'token' => 'Invalid or expired verification link.',
            ]);
        }

        if ($user->email_verified_at) {
            throw ValidationException::withMessages([
                'token' => 'Email is already verified.',
            ]);
        }

        if (
            $user->email_verification_expires_at &&
            now()->isAfter($user->email_verification_expires_at)
        ) {
            throw ValidationException::withMessages([
                'token' => 'Verification link has expired. Please request a new one.',
            ]);
        }

        $user->update([
            'email_verified_at'              => now(),
            'email_verification_token'       => null,
            'email_verification_expires_at'  => null,
        ]);

        return $user->fresh();
    }

    /**
     * Resend verification email — rate-limited to once per minute.
     */
    public function resend(User $user): void
    {
        if ($user->email_verified_at) {
            throw ValidationException::withMessages([
                'email' => 'Email is already verified.',
            ]);
        }

        // Throttle: don't resend if token is still fresh (< 1 minute old)
        if (
            $user->email_verification_expires_at &&
            now()->isBefore(
                $user->email_verification_expires_at
                    ->subMinutes(
                        config('auth.verification.expire', 60) - 1
                    )
            )
        ) {
            throw ValidationException::withMessages([
                'email' => 'Please wait before requesting another verification email.',
            ]);
        }

        $this->sendVerificationEmail($user);
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    private function generateToken(User $user): string
    {
        $plainToken = Str::random(64);

        // Store the hash — if the DB leaks, raw tokens cannot be used directly
        $user->update([
            'email_verification_token'      => hash('sha256', $plainToken),
            'email_verification_expires_at' => now()->addMinutes(
                config('auth.verification.expire', 60)
            ),
        ]);

        return $plainToken;
    }

    private function buildVerificationUrl(string $token): string
    {
        return rtrim(config('app.frontend_url'), '/') . '/verify-email?token=' . $token;
    }
}
