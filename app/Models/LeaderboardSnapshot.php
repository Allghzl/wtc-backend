<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaderboardSnapshot extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'rankings' => 'array',
            'captured_at' => 'datetime',
        ];
    }
}