<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class Track extends Model implements Auditable
{
    use HasFactory, SoftDeletes, \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'order',
        'image_url',
        'created_by'
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($track) {
            if (is_null($track->order)) {
                $track->order = static::max('order') + 1;
            }
        });
    }

    public function getRouteKeyName()
    {
        return 'slug';
    }

    public function modules()
    {
        return $this->hasMany(Module::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Enrollment Relationships
    |--------------------------------------------------------------------------
    */

    public function enrollments()
    {
        return $this->hasMany(TrackEnrollment::class);
    }

    public function enrolledProfiles()
    {
        return $this->belongsToMany(
            Profile::class,
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

    public function activeEnrollments()
    {
        return $this->enrollments()
            ->where('status', 'active');
    }

    /*
    |--------------------------------------------------------------------------
    | Creator Relationship
    |--------------------------------------------------------------------------
    */

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'created_by');
    }
}
