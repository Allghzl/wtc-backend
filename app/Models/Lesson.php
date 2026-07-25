<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lesson extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'title',
        'content',
        'video_url',
        'slug',
        'order',
        'module_id'
    ];

    public function module()
    {
        return $this->belongsTo(Module::class);
    }
    public function challenges()
    {
        return $this->hasMany(Challenge::class);
    }
}
