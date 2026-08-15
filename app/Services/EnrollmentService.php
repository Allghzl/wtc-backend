<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\Track;
use App\Models\TrackEnrollment;
use Illuminate\Validation\ValidationException;

class EnrollmentService
{
    /**
     * Enroll a profile in a track
     */
    public function enroll(Profile $profile, Track $track): TrackEnrollment
    {
        // Check if already enrolled
        $existing = TrackEnrollment::where('profile_id', $profile->id)
            ->where('track_id', $track->id)
            ->first();

        if ($existing && $existing->isActive()) {
            throw ValidationException::withMessages([
                'enrollment' => ['Anda sudah terdaftar dalam track ini.'],
            ]);
        }

        // If previously dropped or paused, reactivate
        if ($existing && in_array($existing->status, ['dropped', 'paused'])) {
            $existing->update([
                'status' => 'active',
                'enrolled_at' => now(),
                'completed_at' => null,
                'dropped_at' => null,
            ]);
            return $existing->fresh();
        }

        // If previously completed, do not reactivate - preserve historical state
        if ($existing && $existing->status === 'completed') {
            throw ValidationException::withMessages([
                'enrollment' => ['Anda sudah menyelesaikan track ini.'],
            ]);
        }

        // Create new enrollment
        return TrackEnrollment::create([
            'track_id' => $track->id,
            'profile_id' => $profile->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);
    }

    /**
     * Unenroll (drop) a profile from a track
     */
    public function unenroll(Profile $profile, Track $track): void
    {
        $enrollment = TrackEnrollment::where('profile_id', $profile->id)
            ->where('track_id', $track->id)
            ->where('status', 'active')
            ->first();

        if (!$enrollment) {
            throw ValidationException::withMessages([
                'enrollment' => ['Anda tidak terdaftar dalam track ini.'],
            ]);
        }

        $enrollment->drop();
    }

    /**
     * Get enrollment for profile and track
     */
    public function getEnrollment(Profile $profile, Track $track): ?TrackEnrollment
    {
        return TrackEnrollment::where('profile_id', $profile->id)
            ->where('track_id', $track->id)
            ->where('status', 'active')
            ->first();
    }
}
