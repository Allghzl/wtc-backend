<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Models\ActivityLog;
use App\Models\AuditLog;

class AssignDefaultTrack
{
    public function handle(UserRegistered $event): void
    {
        ActivityLog::create([
            'user_id' => $event->user->id,
            'event' => 'user_registered',
            'subject_type' => $event->user::class,
            'subject_id' => $event->user->id,
        ]);

        AuditLog::create([
            'user_id' => $event->user->id,
            'action' => 'login.registered',
            'auditable_type' => $event->user::class,
            'auditable_id' => $event->user->id,
        ]);
    }
}