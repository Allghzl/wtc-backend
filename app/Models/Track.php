<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Track extends Model
{

    use SoftDeletes;

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
