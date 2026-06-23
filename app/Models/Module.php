<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Module extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'title',
        'slug',
        'description',
        'order',
        'track_id'
    ];

    public function track()
    {
        return $this->belongsTo(Track::class);
    }
    public function lessons()
    {
        return $this->hasMany(Lesson::class);
    }
}
