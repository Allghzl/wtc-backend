<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentComponent extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'rubric' => 'array',
        ];
    }
}