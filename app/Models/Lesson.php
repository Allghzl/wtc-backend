<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lesson extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'title',
        'content',
        'video_url',
        'slug',
        'order',
        'module_id'
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($lesson) {
            if (is_null($lesson->order)) {
                $maxOrder = static::where('module_id', $lesson->module_id)
                    ->max('order') ?? 0;
                $lesson->order = $maxOrder + 1;
            }
        });
    }

    public function getRouteKeyName()
    {
        return 'slug';
    }


    public function module()
    {
        return $this->belongsTo(Module::class);
    }
    public function challenges()
    {
        return $this->hasMany(Challenge::class);
    }
}
