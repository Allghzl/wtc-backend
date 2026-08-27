<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class Lesson extends Model implements Auditable
{
    use HasFactory, SoftDeletes, \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'content',
        'video_url',
        'duration',
        'order',
        'module_id',
        'created_by'
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($lesson) {
            if (is_null($lesson->order)) {
                $maxOrder = static::where('module_id', $lesson->module_id)
                    ->max('order') ?? 0;
                $lesson->order = $maxOrder + 1;
            }
        });
    }

    public function getRouteKeyName()
    {
        return 'slug';
    }


    public function module()
    {
        return $this->belongsTo(Module::class);
    }
    public function challenges()
    {
        return $this->hasMany(Challenge::class);
    }

    public function attachments()
    {
        return $this->hasMany(Attachment::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Completion Relationships
    |--------------------------------------------------------------------------
    */

    public function completions()
    {
        return $this->hasMany(LessonCompletion::class);
    }

    public function completedBy()
    {
        return $this->belongsToMany(
            Profile::class,
            'lesson_completions'
        )
            ->withPivot('completed_at')
            ->withTimestamps();
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
