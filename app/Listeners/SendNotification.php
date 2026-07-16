<?php

namespace App\Listeners;

use App\Events\ScorePublished;
use App\Models\AppNotification;
use App\Models\NotificationReceiver;

class SendNotification
{
    public function handle(ScorePublished $event): void
    {
        $notification = AppNotification::create([
            'type' => 'score_published',
            'title' => 'Nilai sudah keluar',
            'body' => 'Nilai submission kamu sudah dipublikasikan.',
            'data' => ['submission_id' => $event->submission->id],
        ]);

        NotificationReceiver::create([
            'notification_id' => $notification->id,
            'user_id' => $event->submission->user_id,
            'channel' => 'in_app',
        ]);
    }
}