<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class Challenge extends Model implements Auditable
{
    use HasFactory, SoftDeletes, \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'module_id',
        'lesson_id',
        'title',
        'slug',
        'type',
        'difficulty',
        'order',
        'content',
        'settings',
        'metadata',
        'max_score',
        'allowed_attempts',
        'points',
        'created_by',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($challenge) {
            if (is_null($challenge->order)) {
                if ($challenge->lesson_id) {
                    $maxOrder = static::where('lesson_id', $challenge->lesson_id)
                        ->max('order') ?? 0;
                } else {
                    $maxOrder = static::where('module_id', $challenge->module_id)
                        ->whereNull('lesson_id')
                        ->max('order') ?? 0;
                }
                $challenge->order = $maxOrder + 1;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'metadata' => 'array',
            'allowed_attempts' => 'integer',
            'max_score' => 'integer',
            'points' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    public function submissions()
    {
        return $this->hasMany(Submission::class);
    }

    public function attachments()
    {
        return $this->hasMany(Attachment::class);
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
