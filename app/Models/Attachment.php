<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class Attachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'lesson_id',
        'challenge_id',
        'title',
        'description',
        'type',
        'file_path',
        'file_name',
        'mime_type',
        'size',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($attachment) {
            // Ensure attachment belongs to either lesson OR challenge, not both or neither
            if (is_null($attachment->lesson_id) && is_null($attachment->challenge_id)) {
                throw ValidationException::withMessages([
                    'attachment' => ['Attachment must belong to either a lesson or a challenge.'],
                ]);
            }

            if (!is_null($attachment->lesson_id) && !is_null($attachment->challenge_id)) {
                throw ValidationException::withMessages([
                    'attachment' => ['Attachment cannot belong to both a lesson and a challenge.'],
                ]);
            }
        });
    }

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    public function challenge()
    {
        return $this->belongsTo(Challenge::class);
    }
}
