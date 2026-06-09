<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Track extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'description',
        'order',
        'image_url'
    ];

    public function modules()
    {
        return $this->hasMany(Module::class);
    }
}
