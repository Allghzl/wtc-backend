<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Challenge extends Model
{
    protected $fillable = [
        'module_id',
        'lesson_id',
        'title',
        'slug',
        'type',
        'difficulty',
        'order',
        'content',
        'metadata',
        'max_score',
        'points',
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
            'metadata' => 'array',
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
}
