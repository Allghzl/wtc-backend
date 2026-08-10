<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Submission extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'challenge_id',
        'profile_id',
        'attempt_number',
        'status',
        'submitted_at',
        'submitted_content',
        'file_path',
        'auto_score',
        'manual_score',
        'feedback',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'submitted_content' => 'array',
        ];
    }

    public function challenge()
    {
        return $this->belongsTo(Challenge::class);
    }

    public function profile()
    {
        return $this->belongsTo(Profile::class, 'profile_id');
    }
}
