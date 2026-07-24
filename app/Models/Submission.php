<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Submission extends Model
{

    use SoftDeletes;

    protected $fillable = [
        'challenge_id',
        'user_id',
        'status',
        'submitted_content',
        'file_path',
        'auto_score',
        'manual_score',
        'feedback',
    ];

    protected function casts(): array
    {
        return [
            'submitted_content' => 'array',
        ];
    }

    public function challenge()
    {
        return $this->belongsTo(Challenge::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
