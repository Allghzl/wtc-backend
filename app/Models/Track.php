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

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($track) {
            if (is_null($track->order)) {
                $track->order = static::max('order') + 1;
            }
        });
    }

    public function getRouteKeyName()
    {
        return 'slug';
    }

    public function modules()
    {
        return $this->hasMany(Module::class);
    }
}
