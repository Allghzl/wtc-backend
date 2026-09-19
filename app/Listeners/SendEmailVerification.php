<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Services\EmailVerificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendEmailVerification implements ShouldQueue
{
    public function __construct(
        protected EmailVerificationService $verification,
    ) {}

    public function handle(UserRegistered $event): void
    {
        // Pinat SSO users are already verified — skip
        if ($event->user->isPinat()) {
            return;
        }

        $this->verification->sendVerificationEmail($event->user);
    }
}
