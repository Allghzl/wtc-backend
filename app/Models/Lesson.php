<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lesson extends Model
{
    protected $fillable = [
        'title',
        'content',
        'video_url',
        'order',
        'module_id'
    ];

    public function module()
    {
        return $this->belongsTo(Module::class);
    }
}
