<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Profile extends Model
{
    use SoftDeletes, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'study_class_id',
        'nickname',
        'display_name',
        'points',
        'last_login_at',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'last_login_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function studyClass(): BelongsTo
    {
        return $this->belongsTo(StudyClass::class);
    }

    public function submissions()
    {
        return $this->hasMany(Submission::class, 'profile_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'profile_roles',
            'profile_id',
            'role_id',
            'id',
            'id',
        );
    }

    public function achievements(): BelongsToMany
    {
        return $this->belongsToMany(
            Achievement::class,
            'profile_achievements',
            'profile_id',
            'achievement_id',
            'id',
            'id',
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Enrollment Relationships
    |--------------------------------------------------------------------------
    */

    public function trackEnrollments(): HasMany
    {
        return $this->hasMany(TrackEnrollment::class);
    }

    public function enrolledTracks(): BelongsToMany
    {
        return $this->belongsToMany(
            Track::class,
            'track_enrollments'
        )
            ->withPivot([
                'status',
                'enrolled_at',
                'completed_at',
                'dropped_at',
            ])
            ->withTimestamps();
    }

    public function activeEnrollments(): HasMany
    {
        return $this->trackEnrollments()
            ->where('status', 'active');
    }

    public function isEnrolledIn(Track $track): bool
    {
        return $this->trackEnrollments()
            ->where('track_id', $track->id)
            ->where('status', 'active')
            ->exists();
    }
}
