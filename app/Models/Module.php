<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class Module extends Model implements Auditable
{
    use HasFactory, SoftDeletes, \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'metadata',
        'order',
        'track_id',
        'created_by'
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    // ========================================
    // ✨ TAMBAHIN INI - Auto-populate order
    // ========================================
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($module) {
            if (is_null($module->order)) {
                $maxOrder = static::where('track_id', $module->track_id)
                    ->max('order') ?? 0;
                $module->order = $maxOrder + 1;
            }
        });
    }

    public function getRouteKeyName()
    {
        return 'slug';
    }

    public function track()
    {
        return $this->belongsTo(Track::class);
    }

    public function lessons()
    {
        return $this->hasMany(Lesson::class);
    }

    public function challenges()
    {
        return $this->hasMany(Challenge::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Creator Relationship
    |--------------------------------------------------------------------------
    */

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'created_by');
    }
}
